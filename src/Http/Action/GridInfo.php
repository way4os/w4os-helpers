<?php

declare(strict_types=1);

namespace W4OS\Helpers\Http\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /get_grid_info: what the grid says of itself, in the XML the viewers read. */
final class GridInfo
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $xml = new \SimpleXMLElement('<gridinfo/>');
        foreach ($request->getAttribute('grid')->gridInfo() as $key => $value) {
            $xml->addChild(preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $key), htmlspecialchars((string) $value));
        }
        $response->getBody()->write((string) $xml->asXML());

        return $response->withHeader('Content-Type', 'text/xml; charset=utf-8');
    }
}
