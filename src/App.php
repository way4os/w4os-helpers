<?php

declare(strict_types=1);

namespace OpenSim\Helpers;

use DI\Bridge\Slim\Bridge;
use OpenSim\Config\Layout;
use OpenSim\Helpers\Http\Action\Engine;
use OpenSim\Helpers\Http\Action\Site;
use OpenSim\Helpers\Http\Middleware\GridMiddleware;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use OpenSim\Helpers\View\View;
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
            $environment = Environment::system(Layout::load());
            if ($nick === '') {
                $all = $environment->layout->etcRoot() . '/grids';
                $found = array_values(array_filter(scandir($all) ?: [], static fn($n) => $n[0] !== '.'));
                $nick = count($found) === 1 ? $found[0] : '';
            }

            return Grid::find($environment, $nick);
        };

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
        $app->addErrorMiddleware(false, true, true);

        // The API of the engine
        $app->any('/api[/{path:.*}]', static fn($request) => Engine::handler($request, '/api')->handle($request));

        // The services the grid is configured with (see Engine::paths()): whatever no other route took
        $app->map(['GET', 'POST'], '/{path:.*}', Site::class);

        return $app;
    }
}
