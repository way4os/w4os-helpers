<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /health: the helpers answer for the grid they serve. */
final class Health
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $grid = $request->getAttribute('grid');
        $response->getBody()->write((string) json_encode(['status' => 'ok', 'grid' => $grid->nick()]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
