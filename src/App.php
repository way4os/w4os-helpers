<?php

declare(strict_types=1);

namespace W4OS\Helpers;

use DI\Bridge\Slim\Bridge;
use W4OS\Helpers\Http\Action\Engine;
use W4OS\Helpers\Http\Action\Site;
use W4OS\Helpers\Http\Middleware\GridMiddleware;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Runtime\Environment;
use W4OS\Helpers\View\View;
use Slim\App as SlimApp;

/** The helpers as a Slim application: the grid is found by the middleware, every endpoint is an action. */
final class App
{
    /**
     * @param ?\Closure(string):?Grid $grids finds a grid by its nick (none: the grids of this machine, through the engine)
     * @return SlimApp<\Psr\Container\ContainerInterface|null>
     */
    public static function create(?\Closure $grids = null): SlimApp
    {
        $grids ??= static function (string $nick): ?Grid {
            // The settings the web server gives, and folders it can write for the tasks and the cache (HelpersLayout)
            $environment = Environment::system(HelpersLayout::resolve($_SERVER));
            if ($nick === '') {
                $all = $environment->layout->etcRoot() . '/grids';
                $found = array_values(array_filter(scandir($all) ?: [], static fn($n) => $n[0] !== '.'));
                $nick = count($found) === 1 ? $found[0] : '';
            }

            return Grid::find($environment, $nick);
        };

        // The answer held to be sent whole, its client freed before what waits for it (Engine::finish())
        \W4OS\Engine\Api\Server::hold();
        $container = new \DI\Container([
            View::class => static fn() => new View(dirname(__DIR__) . '/templates'),
        ]);
        $app = Bridge::create($container);
        $app->addRoutingMiddleware();
        // The grid served, for what waits for the answer (Engine::finish(), see public/index.php); inside GridMiddleware, which finds it
        $app->add(function ($request, $handler) {
            Engine::serving($request->getAttribute('grid'));

            return $handler->handle($request);
        });
        $app->add(new GridMiddleware($grids));
        $errors = $app->addErrorMiddleware(false, true, true);
        $errors->setDefaultErrorHandler(self::errors($errors->getDefaultErrorHandler()));

        // The services the grid is configured with (see Engine::paths()): whatever no other route took
        $app->map(['GET', 'POST'], '/{path:.*}', Site::class);

        return $app;
    }

    /**
     * What an error answers: the ones of HTTP as Slim says them (404, 405...), any other `500 Internal Server Error`, then the one
     * fact that helps when there is one: the file of the grid the helpers cannot read, and the account they run as.
     */
    private static function errors(callable $slim): \Closure
    {
        return static function ($request, \Throwable $error, bool $display, bool $log, bool $details) use ($slim) {
            if ($error instanceof \Slim\Exception\HttpException) {
                return $slim($request, $error, $display, $log, $details);
            }
            error_log(sprintf('w4os-helpers: %s: %s (%s:%d)', $error::class, $error->getMessage(), $error->getFile(), $error->getLine()));
            $fact = $error instanceof \W4OS\Engine\Exceptions\SetupFailedException && preg_match('/^cannot read (\S+)/', $error->getMessage(), $m) === 1
                ? sprintf('%s: not readable by %s', $m[1], \W4OS\Engine\Health\Health::account())
                : '';
            $response = (new \Slim\Psr7\Factory\ResponseFactory())->createResponse(500)->withHeader('Content-Type', 'text/html; charset=utf-8');
            $response->getBody()->write(sprintf(
                "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"><title>500 Internal Server Error</title></head><body>\n<h1>500 Internal Server Error</h1>\n%s</body></html>\n",
                $fact === '' ? '' : '<p>' . htmlspecialchars($fact) . "</p>\n",
            ));

            return $response;
        };
    }
}
