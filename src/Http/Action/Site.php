<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Action;

use OpenSim\Config\Layout;
use OpenSim\Helpers\Block\Blocks;
use OpenSim\Helpers\View\View;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use OpenSim\Security\ApiKeys;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Whatever is asked of the site: first the services of the simulators and the viewers (the engine's, see Engine), then the pages, at
 * the addresses the grid gives them (`search`, `welcome`, `guide`, `GridStatus`... of its config), the home page, the blocks alone.
 */
final class Site
{
    /** The page of each address the grid gives (the key of its service), by the name of the page. */
    private const PAGES = [
        'search' => 'search',
        'welcome' => 'splash',
        'guide' => 'destination_guide',
        'grid-status' => 'grid_status',
        'web-profile' => 'profile',
    ];

    public function __construct(private View $view, private Engine $engine) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $answer = $this->engine->answer($request);
        if ($answer !== null) {
            return $answer;
        }
        /** @var Grid $grid */
        $grid = $request->getAttribute('grid');
        $path = '/' . trim($request->getUri()->getPath(), '/');
        if (preg_match('#^/blocks/([\w-]+)$#', $path, $m) === 1) {
            return $this->block($response, $grid, $m[1], $request->getQueryParams());
        }
        $page = $this->page($grid, $path);
        if ($page === null) {
            return $response->withStatus(404);
        }
        // The health is for who manages the grid
        if ($page === 'health') {
            $environment = Environment::system(Layout::load());
            if (!ApiKeys::for($environment)->allows(ApiKeys::token($request->getHeaderLine('Authorization')))) {
                return $response->withStatus(401)->withHeader('WWW-Authenticate', 'Bearer');
            }
        }
        $html = $this->view->page($page, $grid, $request->getHeaderLine('User-Agent'), $request->getQueryParams());
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** The page a path asks: the home page, the health, or the one the grid has an address for. */
    private function page(Grid $grid, string $path): ?string
    {
        if ($path === '/') {
            return 'home';
        }
        if ($path === '/health') {
            return 'health';
        }
        $prefix = (string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH);
        foreach (self::PAGES as $service => $page) {
            $address = (string) parse_url((string) $grid->address($service), PHP_URL_PATH);
            // The address of a profile has the name in it, which the path adds
            $address = (string) preg_replace('#/?\[AGENT_NAME\].*$#', '', $address);
            $address = $prefix !== '' && str_starts_with($address, "$prefix/") ? substr($address, strlen($prefix)) : $address;
            if ($address === '' || $address === '/') {
                continue;
            }
            $address = '/' . trim($address, '/');
            if ($path === $address || ($page === 'profile' && str_starts_with($path, "$address/"))) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $query the arguments of the block; `format=json` gives its data
     */
    private function block(ResponseInterface $response, Grid $grid, string $name, array $query): ResponseInterface
    {
        $name = Blocks::name($name);
        if ($name === null) {
            return $response->withStatus(404);
        }
        if (($query['format'] ?? '') === 'json') {
            $response->getBody()->write((string) json_encode(Blocks::data($name, $grid, $query)));

            return $response->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write($this->view->block($name, $grid, $query));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
