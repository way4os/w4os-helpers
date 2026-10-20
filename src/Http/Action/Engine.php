<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Action;

use OpenSim\Api\Api;
use OpenSim\Api\Handler;
use OpenSim\Config\Layout;
use OpenSim\Runtime\Environment;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * The services whose format the simulators and the viewers fix (offline messages, search query and register, currency, land tool,
 * message of the day, text images) are served by the engine. This action gives the engine the request under its address.
 */
final class Engine
{
    /** The addresses the simulators and the viewers know, by the address the engine has for them; `{m}` is the method. */
    public const ROUTES = [
        '#^/(?:offline|offline-messages)(?:\.php)?/(?<m>SaveMessage|RetrieveMessages)/?$#' => '/offline-messages/{m}',
        '#^/(?:query|search/query)(?:\.php)?$#' => '/search/query',
        '#^/(?:register|search/register)(?:\.php)?$#' => '/search/register',
        '#^/(currency|landtool)\.php$#' => '/$1.php',
        '#^/motd(?:\.php)?$#' => '/motd',
        '#^/(?:textgen\.php|text-images)$#' => '/text-images',
    ];

    /** The engine, as a PSR-15 handler for the grid of the request. */
    public static function handler(ServerRequestInterface $request, string $base = ''): Handler
    {
        return new Handler(
            new Api(Environment::system(Layout::load())),
            new ResponseFactory(),
            new StreamFactory(),
            $base,
            grid: $request->getAttribute('grid')->nick(),
        );
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        foreach (self::ROUTES as $pattern => $target) {
            if (preg_match($pattern, $path, $m) === 1) {
                $target = str_replace(['{m}', '$1'], [$m['m'] ?? '', $m[1] ?? ''], $target);
                $query = $request->getQueryParams();
                // The former arguments of the text images
                if ($target === '/text-images') {
                    $query += ['text' => $query['string'] ?? null, 'size' => $query['fontsize'] ?? null];
                    $query = array_filter($query, static fn($v) => $v !== null);
                }

                return self::handler($request)->handle(
                    $request->withUri($request->getUri()->withPath($target))->withQueryParams($query),
                );
            }
        }

        return $response->withStatus(404);
    }
}
