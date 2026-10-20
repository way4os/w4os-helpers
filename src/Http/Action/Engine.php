<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Action;

use OpenSim\Api\Api;
use OpenSim\Api\Handler;
use OpenSim\Config\Layout;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use OpenSim\Security\ApiKeys;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * The services of the simulators and the viewers (offline messages, search query and register, currency, land tool, message of the
 * day) are served by the engine, at the addresses the grid is configured with (Robust.ini, OpenSim.ini, the economy it gives its
 * viewers). Only `currency.php` and `landtool.php` have a fixed name, under an economy address the grid chooses.
 */
final class Engine
{
    /** @var array<string,string> the path of each service in the engine, by the way the grid names it */
    private const TARGETS = [
        'query' => '/search/query',
        'register' => '/search/register',
        'offline' => '/offline-messages',
        'motd' => '/motd',
        'currency' => '/currency.php',
        'landtool' => '/landtool.php',
    ];

    /** The engine, as a PSR-15 handler for the grid of the request. */
    public static function handler(ServerRequestInterface $request, string $base = ''): Handler
    {
        $environment = Environment::system(Layout::load());

        return new Handler(
            new Api($environment),
            new ResponseFactory(),
            new StreamFactory(),
            $base,
            grid: $request->getAttribute('grid')->nick(),
            authenticator: ApiKeys::for($environment),
        );
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
        foreach ($paths as $service => $known) {
            $known = '/' . trim($known, '/');
            if ($path === $known) {
                return self::TARGETS[$service];
            }
            // The offline messages: the method follows the address (`/SaveMessage/`)
            if ($service === 'offline' && str_starts_with($path, "$known/")) {
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
