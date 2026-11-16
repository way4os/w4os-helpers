<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Middleware;

use OpenSim\Models\Grid;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/** Finds the grid served (OPENSIM_GRID, else the only one) and gives it to the actions as the `grid` attribute. */
final class GridMiddleware implements MiddlewareInterface
{
    /** @param \Closure(string):?Grid $grids */
    public function __construct(private \Closure $grids) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $nick = (string) ($request->getServerParams()['OPENSIM_GRID'] ?? (getenv('OPENSIM_GRID') ?: ''));
        $grid = ($this->grids)($nick);
        if ($grid === null) {
            $response = new Response(503);
            $response->getBody()->write('Not properly configured');

            return $response;
        }

        // The helpers are served under their base path (/helpers by default): the routes do not know it. What is outside it is the
        // site's, never the helpers'
        $prefix = rtrim((string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH), '/');
        $uri = $request->getUri();
        if ($prefix !== '') {
            if ($uri->getPath() !== $prefix && !str_starts_with($uri->getPath(), "$prefix/")) {
                return new Response(404);
            }
            $request = $request->withUri($uri->withPath(substr($uri->getPath(), strlen($prefix)) ?: '/'));
        }

        return $handler->handle($request->withAttribute('grid', $grid));
    }
}
