<?php

declare(strict_types=1);

use W4OS\Helpers\App;
use W4OS\Helpers\View\View;
use W4OS\Engine\Models\Grid;
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
    $grid = Grid::at(helpers_environment(), $dir);

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

        expect(W4OS\Engine\Hypergrid\Destination::isViewer('Mozilla/5.0 (X11) Firestorm-Releasex64 7.1.11'))->toBeTrue()
            ->and(W4OS\Engine\Hypergrid\Destination::isViewer('Mozilla/5.0 (Macintosh) Firefox/140.0'))->toBeFalse()
            ->and($viewer)->not->toContain('class="topbar"')
            ->and($browser)->toContain('class="topbar"');
    });
});

describe('a service the grid does not provide here', function () {
    /** A grid whose economy and search are served elsewhere, its helpers under https://example.com/helpers. */
    function elsewhere_app(): Slim\App
    {
        $dir = sys_get_temp_dir() . '/helpers-elsewhere-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[Const]\nWebURL = \"https://example.com\"\n[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\neconomy = \"https://shop.example.org/money/\"\n");
        file_put_contents("$dir/OpenSim.ini", "[Search]\nSearchURL = \"https://search.example.org/query.php\"\n");
        file_put_contents("$dir/Helpers.ini", "[Helpers]\nHelpersURL = \"https://example.com/helpers\"\n");
        $grid = Grid::at(helpers_environment(), $dir);

        return App::create(static fn(string $nick) => $grid);
    }

    test('is refused with a fault of XML-RPC for the currency and the search, which the simulator logs and never says to the avatar', function () {
        $app = elsewhere_app();
        // The log of the web server, in a file of the test
        $log = tempnam(sys_get_temp_dir(), 'helpers-log-');
        $previous = ini_set('error_log', $log);
        // A caller of its own (an address of the documentation), whatever an earlier run logged
        $caller = '2001:db8::' . bin2hex(random_bytes(2));
        $post = static fn(string $path): Psr\Http\Message\ResponseInterface => $app->handle(
            (new ServerRequestFactory())->createServerRequest('POST', $path, ['REMOTE_ADDR' => $caller])->withHeader('Content-Type', 'text/xml')
                ->withBody((new Slim\Psr7\Factory\StreamFactory())->createStream(W4OS\Engine\Services\XmlRpc::request('getCurrencyQuote', [['agentId' => '00000000-0000-0000-0000-000000000000', 'currencyBuy' => 1000]]))),
        );

        foreach (['/helpers/currency.php', '/helpers/landtool.php', '/helpers/query.php'] as $path) {
            $answer = $post($path);
            // A fault (faultCode, faultString), not an answer whose errorMessage the search module says to the avatar
            $body = (string) $answer->getBody();
            expect($answer->getStatusCode())->toBe(200)
                ->and($answer->getHeaderLine('Content-Type'))->toContain('xml')
                ->and(W4OS\Engine\Services\XmlRpc::answerOf($body))->toMatchArray(['faultCode' => 404])
                ->and($body)->not->toContain('errorMessage')
                ->and($body)->toContain('not provided here');
        }
        // Asked again: logged once for each caller and path
        $post('/helpers/query.php');
        $lines = file($log, FILE_IGNORE_NEW_LINES) ?: [];
        ini_set('error_log', (string) $previous);
        unlink($log);

        expect(count(array_filter($lines, static fn(string $line): bool => str_contains($line, '/query.php is not provided here'))))->toBe(1)
            ->and(count(array_filter($lines, static fn(string $line): bool => str_contains($line, 'not provided here'))))->toBe(3);
    });

    test('is a 404 that says so, never an empty answer', function () {
        $app = elsewhere_app();

        foreach (['/helpers/register.php', '/helpers/offline.php', '/helpers/guide', '/helpers/nothing'] as $path) {
            $answer = base_get($app, $path);
            expect($answer->getStatusCode())->toBe(404)
                ->and($answer->getHeaderLine('Content-Type'))->toContain('text/plain')
                ->and((string) $answer->getBody())->toContain('not provided here');
        }
    });
});

describe('a block of the helpers', function () {
    test('says which it is, for the health to find it on a page of the site', function () {
        $dir = sys_get_temp_dir() . '/helpers-blocks-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        // Its guide an empty list of its own: never the default one, read online
        file_put_contents("$dir/Robust.ini", "[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\nlogin = \"http://grid.example.org:8002/\"\n[Helpers]\nGuideSource = \"destinations.txt\"\n");
        file_put_contents("$dir/destinations.txt", '');
        $grid = Grid::at(helpers_environment(), $dir);
        $view = new View(dirname(__DIR__, 2) . '/templates');

        foreach (['grid-info', 'grid-status', 'search', 'profile', 'motd', 'new_users', 'directory_info'] as $block) {
            expect($view->block($block, $grid, ['client' => 'browser']))->toContain('data-helpers-block="' . $block . '"');
        }
        // The guide and the popular places of a grid that has none: no block at all
        foreach (['destination_guide', 'popular_places'] as $block) {
            expect($view->block($block, $grid, ['client' => 'browser']))->toBe('');
        }
    });
});
