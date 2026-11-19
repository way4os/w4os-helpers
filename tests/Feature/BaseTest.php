<?php

declare(strict_types=1);

use OpenSim\Config\Layout;
use OpenSim\Helpers\App;
use OpenSim\Helpers\View\View;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * A grid whose web site is https://example.com, its helpers under $base (the path of its economy), its pages at the addresses given.
 *
 * @param array<string,string> $info the keys of [GridInfoService]
 */
function base_app(string $base, array $info, string $guide = ''): Slim\App
{
    $dir = sys_get_temp_dir() . '/helpers-base-' . md5($base . serialize($info) . $guide);
    is_dir($dir) || mkdir($dir, 0o755, true);
    $lines = ['[Const]', 'WebURL = "https://example.com"', '[GridInfoService]', 'gridname = "Example"', 'gridnick = "example"',
        'economy = "https://example.com' . rtrim($base, '/') . '/"'];
    foreach ($info as $key => $value) {
        $lines[] = "$key = \"$value\"";
    }
    $guide === '' || array_push($lines, '[LoginService]', "DestinationGuide = \"$guide\"");
    file_put_contents("$dir/Robust.ini", implode("\n", $lines) . "\n");
    $grid = Grid::at(Environment::system(Layout::load()), $dir);

    return App::create(static fn(string $nick) => $grid);
}

function base_get(Slim\App $app, string $path, string $agent = 'Mozilla/5.0'): Psr\Http\Message\ResponseInterface
{
    return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('User-Agent', $agent));
}

describe('the helpers serve only under their base', function () {
    test('their base: the pages the grid sets there, nothing at the address a page of the site would have there, nothing outside', function () {
        $app = base_app('/helpers', ['search' => 'https://example.com/helpers/search'], 'https://example.com/guide');

        expect(base_get($app, '/helpers/search')->getStatusCode())->toBe(200)
            ->and(base_get($app, '/helpers/')->getStatusCode())->toBe(200)
            ->and(base_get($app, '/search')->getStatusCode())->toBe(404)
            ->and(base_get($app, '/guide')->getStatusCode())->toBe(404);
        // The guide is the site's: /helpers/guide is no address of the grid, the helpers do not take it
        expect(base_get($app, '/helpers/guide')->getStatusCode())->toBe(404);
    });

    test('another base: /helpers is nobody\'s', function () {
        $app = base_app('/helperz', ['search' => 'https://example.com/helperz/search'], 'https://example.com/guide');

        expect(base_get($app, '/helperz/search')->getStatusCode())->toBe(200)
            ->and(base_get($app, '/helpers/search')->getStatusCode())->toBe(404)
            ->and(base_get($app, '/helpers/')->getStatusCode())->toBe(404)
            ->and(base_get($app, '/helperz/guide')->getStatusCode())->toBe(404);
    });

    test('the base at the root: every page the grid gives, the minimal site', function () {
        $app = base_app('/', ['search' => 'https://example.com/search', 'web_profile_url' => 'https://example.com/profile/[AGENT_NAME]'],
            'https://example.com/guide');

        expect(base_get($app, '/')->getStatusCode())->toBe(200)
            ->and(base_get($app, '/search')->getStatusCode())->toBe(200)
            ->and(base_get($app, '/guide')->getStatusCode())->toBe(200)
            ->and(base_get($app, '/profile/Nobody.Here')->getStatusCode())->toBe(404);
    });

    test('a profile on the site: the address it would have under the helpers is nobody\'s', function () {
        $app = base_app('/helpers', ['web_profile_url' => 'https://example.com/profile/[AGENT_NAME]']);

        expect(base_get($app, '/helpers/profile/Ada.Love?lang=fr')->getStatusCode())->toBe(404);
    });

    test('a viewer gets the content alone', function () {
        $app = base_app('/helpers', ['search' => 'https://example.com/helpers/search']);
        $viewer = (string) base_get($app, '/helpers/search', 'Mozilla/5.0 SecondLife/7.1 (Firestorm)')->getBody();
        $browser = (string) base_get($app, '/helpers/search')->getBody();

        expect(OpenSim\Hypergrid\Destination::isViewer('Mozilla/5.0 (X11) Firestorm-Releasex64 7.1.11'))->toBeTrue()
            ->and(OpenSim\Hypergrid\Destination::isViewer('Mozilla/5.0 (Macintosh) Firefox/140.0'))->toBeFalse()
            ->and($viewer)->not->toContain('class="topbar"')
            ->and($browser)->toContain('class="topbar"');
    });
});
