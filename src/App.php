<?php

declare(strict_types=1);

namespace OpenSim\Helpers;

use DI\Bridge\Slim\Bridge;
use OpenSim\Config\Layout;
use OpenSim\Helpers\Http\Action\GridInfo;
use OpenSim\Helpers\Http\Action\Health;
use OpenSim\Helpers\Http\Action\Page;
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
        $app->add(new GridMiddleware($grids));
        $app->addErrorMiddleware(false, true, true);

        $app->get('/', Page::class);
        $app->get('/welcome', Page::class);
        $app->get('/get_grid_info', GridInfo::class);
        $app->get('/health', Health::class);

        return $app;
    }
}
