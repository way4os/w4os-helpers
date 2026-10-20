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

        return $handler->handle($request->withAttribute('grid', $grid));
    }
}
