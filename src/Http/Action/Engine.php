<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Action;

use OpenSim\Api\Api;
use OpenSim\Api\Handler;
use OpenSim\Config\GridConfig;
use OpenSim\Models\Grid;
use OpenSim\Scheduling\Scheduler;
use OpenSim\Security\Locked;
use OpenSim\Services\SearchTasks;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * The services of the simulators and the viewers (offline messages, search query and register, currency, land tool, message of the
 * day) are served by the engine, at the addresses the grid is configured with (Robust.ini, OpenSim.ini, the economy it gives its
 * viewers). Only `currency.php` and `landtool.php` have a fixed name, under an economy address the grid chooses.
 *
 * What waits for the answer is done once it is sent (`public/index.php` calls finish()): a simulator that registered in the search
 * is read, and the tasks of the grid that are due run (its search tasks: the pseudo cron of the engine, on every visit).
 */
final class Engine
{
    /** @var list<Api> the APIs of this request, whose work waits for the answer */
    private static array $apis = [];

    /** The grid of this request, whose tasks are due even when it asked no API */
    private static ?Grid $served = null;

    /** @var array<string,string> the path of each service in the engine, by the way the grid names it */
    private const TARGETS = [
        'query' => '/search/query',
        'register' => '/search/register',
        'offline' => '/offline-messages',
        'motd' => '/motd',
        'currency' => '/currency.php',
        'landtool' => '/landtool.php',
    ];

    /**
     * The engine, as a PSR-15 handler for the services of the grid of the request: locked, it answers only what the engine says is
     * public (Router::PUBLIC: what the viewers and the simulators call, the pictures); the API is the engine's, never the helpers'.
     */
    public static function handler(ServerRequestInterface $request): Handler
    {
        $grid = $request->getAttribute('grid');

        return new Handler(
            self::api($grid),
            new ResponseFactory(),
            new StreamFactory(),
            grid: $grid->nick(),
            authenticator: new Locked(),
        );
    }

    /** The API of the engine for a grid, with its tasks; what it leaves for after the answer is done by finish(). */
    public static function api(Grid $grid): Api
    {
        $api = self::apiOf($grid);
        self::$apis[] = $api;

        return $api;
    }

    /**
     * The API of the engine for the grid served: the grid and its config as the helpers have them (its folder on the machine, or
     * the settings a site gives), the other grids the ones of the machine.
     */
    private static function apiOf(Grid $grid): Api
    {
        return new Api(
            $grid->environment(),
            self::scheduler($grid),
            static fn(Grid $asked): ?GridConfig => $asked->config(),
            grids: static fn(string $nick): ?Grid => $nick === $grid->nick() ? $grid : Grid::find($grid->environment(), $nick),
        );
    }

    /** The tasks of a grid: its search tasks (the snapshots of the simulators, the events), when it has a search database. */
    public static function scheduler(Grid $grid): Scheduler
    {
        $scheduler = Scheduler::for($grid->environment());
        if (($config = $grid->config()) !== null) {
            SearchTasks::add($scheduler, $grid->environment(), $config);
        }

        return $scheduler;
    }

    /** The grid this request serves, whose tasks finish() runs when they are due. */
    public static function serving(?Grid $grid): void
    {
        self::$served = $grid;
    }

    /**
     * What waits for the answer, once it is sent: what the APIs of the request left (a simulator that registered is read), then the
     * tasks that are due, the ones of the grid served when the request asked no API.
     */
    public static function finish(): void
    {
        $apis = self::$apis;
        self::$apis = [];
        if ($apis === [] && self::$served !== null) {
            $apis = [self::apiOf(self::$served)];
        }
        foreach ($apis as $api) {
            $api->finish();
        }
    }

    /**
     * The addresses of the services of a grid, as paths of the site.
     *
     * @return array<string,string> service => path
     */
    public static function paths(Grid $grid): array
    {
        $economy = $grid->economy();
        $urls = [
            'query' => $grid->searchApi(),
            'register' => $grid->registerUrl(),
            'offline' => $grid->offlineUrl(),
            'motd' => $grid->motdUrl(),
            'currency' => $economy === null ? null : "$economy/currency.php",
            'landtool' => $economy === null ? null : "$economy/landtool.php",
        ];
        $prefix = (string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH);
        $paths = [];
        foreach ($urls as $service => $url) {
            // An address of another server (the service is provided elsewhere) is not routed here, whatever its path
            $url !== null && $grid->helpersUrl() !== null && !$grid->servedByHelpers($url) && ($url = null);
            $path = $url === null ? '' : (string) parse_url($url, PHP_URL_PATH);
            if ($path !== '') {
                $paths[$service] = $prefix !== '' && str_starts_with($path, "$prefix/") ? substr($path, strlen($prefix)) : $path;
            }
        }

        return $paths;
    }

    /**
     * The address in the engine of what a path asks, null when it is not a service of the grid.
     *
     * @param array<string,string> $paths see paths()
     */
    public static function target(array $paths, string $path): ?string
    {
        $path = '/' . trim($path, '/');
        // The pictures of the grid (a texture, a profile picture, a map tile), at a path of their own: the asset of the engine
        if (preg_match('#^/pictures/([0-9a-fA-F-]{36}\.(?:' . implode('|', array_keys(\OpenSim\Models\Image::FORMATS)) . '))$#', $path, $m) === 1) {
            return "/assets/{$m[1]}";
        }
        foreach ($paths as $service => $known) {
            $known = '/' . trim($known, '/');
            if ($path === $known) {
                return self::TARGETS[$service];
            }
            // The offline messages: the method follows the address (`/SaveMessage/`), one of the two the simulators call
            if ($service === 'offline' && in_array(substr($path, strlen($known)), ['/SaveMessage', '/RetrieveMessages'], true)) {
                return self::TARGETS[$service] . substr($path, strlen($known));
            }
        }

        return null;
    }

    /** The answer of the engine to a service of the grid, null when the path is none. */
    public function answer(ServerRequestInterface $request): ?ResponseInterface
    {
        $target = self::target(self::paths($request->getAttribute('grid')), $request->getUri()->getPath());

        return $target === null
            ? null
            : self::handler($request)->handle($request->withUri($request->getUri()->withPath($target)));
    }
}
