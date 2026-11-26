<?php

declare(strict_types=1);

use W4OS\Helpers\App;
use W4OS\Helpers\View\View;
use W4OS\Engine\Models\Grid;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * A grid whose web site is https://example.com, its helpers under $base (HelpersURL of its Helpers.ini; its economy follows them),
 * its pages at the addresses given.
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
    file_put_contents(W4OS\Engine\Provisioning\Web\HelpersConfig::path($dir), "[Helpers]\nHelpersURL = \"https://example.com$base\"\n");
    $grid = Grid::at(helpers_environment(), $dir);

    return App::create(static fn(string $nick) => $grid);
}

function base_get(Slim\App $app, string $path, string $agent = 'Mozilla/5.0'): Psr\Http\Message\ResponseInterface
{
    return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('User-Agent', $agent));
}

describe('the helpers answer the addresses of the grid on its site', function () {
    test('wherever its config names them (the paths an install of before kept), never a path nobody set', function () {
        $dir = sys_get_temp_dir() . '/helpers-paths-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[Const]\nWebURL = \"https://example.com\"\n[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\neconomy = \"https://example.com/opensim/economy/\"\n");
        file_put_contents("$dir/OpenSim.ini", "[Messaging]\nOfflineMessageModule = OfflineMessageModule\nOfflineMessageURL = \"https://example.com/helpers/helper/offline.php\"\n");
        $grid = Grid::at(helpers_environment(), $dir);
        $app = App::create(static fn(string $nick) => $grid);
        $post = static fn(string $path) => $app->handle((new ServerRequestFactory())->createServerRequest('POST', $path));

        // The module V1 at the address of before: the engine answers it (no database here: unavailable, never "not found")
        expect($post('/helpers/helper/offline.php/SaveMessage/')->getStatusCode())->toBe(503)
            ->and($post('/helpers/helper/other.php')->getStatusCode())->toBe(404)
            ->and($post('/helpers/helper/offline.phpx')->getStatusCode())->toBe(404);
    });
});

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

    test('without HelpersURL, /helpers for a grid that has helpers (an economy on its site), never the path of its economy', function () {
        $dir = sys_get_temp_dir() . '/helpers-default-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[Const]\nWebURL = \"https://example.com\"\n[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\neconomy = \"https://example.com/opensim/\"\nsearch = \"https://example.com/helpers/search\"\n");
        $app = App::create(static fn(string $nick) => Grid::at(helpers_environment(), $dir));

        expect(base_get($app, '/helpers/search')->getStatusCode())->toBe(200)
            ->and(base_get($app, '/opensim/search')->getStatusCode())->toBe(404);
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

    test('a viewer gets the styles of the viewer alone, a browser the ones of the web alone', function () {
        $app = base_app('/helpers', ['search' => 'https://example.com/helpers/search']);
        $styles = fn(string $html) => preg_match_all('#<link rel="stylesheet" href="/helpers/assets/(css/[^"?]+)#', $html, $m) ? $m[1] : [];
        $viewer = $styles((string) base_get($app, '/helpers/search', 'Mozilla/5.0 SecondLife/7.1 (Firestorm)')->getBody());
        $browser = $styles((string) base_get($app, '/helpers/search')->getBody());

        expect($viewer)->toContain('css/page.css', 'css/page.viewer.css', 'css/search.css')
            ->and(preg_grep('/\.web\.css$/', $viewer))->toBe([])
            ->and($browser)->toContain('css/page.css', 'css/page.web.css', 'css/search.css', 'css/search.web.css')
            ->and(preg_grep('/\.viewer\.css$/', $browser))->toBe([]);
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
                // Nothing about addresses for the caller: the log of the server says why
                ->and($body)->toContain('Not found')->not->toContain('not provided here');
        }
        // Asked again: logged once for each caller and path
        $post('/helpers/query.php');
        $lines = file($log, FILE_IGNORE_NEW_LINES) ?: [];
        ini_set('error_log', (string) $previous);
        unlink($log);

        // The log names the address asked, its base with it
        expect(count(array_filter($lines, static fn(string $line): bool => str_contains($line, '/helpers/query.php is not provided here'))))->toBe(1)
            ->and(count(array_filter($lines, static fn(string $line): bool => str_contains($line, 'not provided here'))))->toBe(3);
    });

    test('is a plain 404 Not found in the language of the visitor, never about addresses, never an empty answer, never logged', function () {
        $app = elsewhere_app();
        $log = tempnam(sys_get_temp_dir(), 'helpers-log-');
        $previous = ini_set('error_log', $log);
        $french = (W4OS\Engine\I18n\Catalog::messages('fr_FR') + W4OS\Engine\I18n\Catalog::MESSAGES)['error_not_found'];

        foreach (['/helpers/register.php', '/helpers/offline.php', '/helpers/nothing', '/helpers/something/'] as $path) {
            $answer = base_get($app, $path);
            expect($answer->getStatusCode())->toBe(404)
                ->and($answer->getHeaderLine('Content-Type'))->toContain('text/plain')
                ->and(trim((string) $answer->getBody()))->toBe('404 Not found');
        }
        $answer = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/helpers/nothing')->withHeader('Accept-Language', 'fr'));
        $logged = (string) file_get_contents($log);
        ini_set('error_log', (string) $previous);
        unlink($log);
        expect(trim((string) $answer->getBody()))->toBe("404 $french")->not->toBe('404 Not found')
            // A page nobody provides here is the access log's alone
            ->and($logged)->toBe('');
    });

    test('take the language of the cookie of the suite, then of the lang cookie (saved into it), then of the browser', function () {
        $app = base_app('/helpers/', []);
        $text = static fn(string $locale): string => '404 ' . ((W4OS\Engine\I18n\Catalog::messages($locale) + W4OS\Engine\I18n\Catalog::MESSAGES)['error_not_found']);
        $ask = static fn(array $cookies, string $query = '') => $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/helpers/nothing' . $query)->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
                ->withCookieParams($cookies)->withQueryParams($query === '' ? [] : ['lang' => substr($query, 6)]),
        );

        $suite = $ask(['w4os_lang' => 'en']);
        $asked = $ask(['w4os_lang' => 'en'], '?lang=de');
        $other = $ask(['lang' => 'de']);
        $unknown = $ask(['lang' => 'xx']);
        $wrong = $ask(['w4os_lang' => 'xx']);

        expect(trim((string) $suite->getBody()))->toBe('404 Not found')
            ->and(trim((string) $asked->getBody()))->toBe($text('de_DE'))
            ->and(trim((string) $other->getBody()))->toBe($text('de_DE'))
            ->and($other->getHeaderLine('Set-Cookie'))->toBe('w4os_lang=de; Path=/; Max-Age=31536000; SameSite=Lax')
            ->and(trim((string) $unknown->getBody()))->toBe($text('fr_FR'))
            ->and($unknown->getHeaderLine('Set-Cookie'))->toBe('')
            ->and(trim((string) $wrong->getBody()))->toBe($text('fr_FR'))
            ->and($suite->getHeaderLine('Set-Cookie'))->toBe('');
    });

    test('say on their page the language of its text', function () {
        $page = static fn(string $languages): string => (string) base_app('/helpers/', [])->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/helpers/')->withHeader('Accept-Language', $languages)->withHeader('User-Agent', 'Mozilla/5.0'),
        )->getBody();

        expect($page('de-DE,de;q=0.9'))->toContain('<html lang="de-DE">')
            ->and($page('en'))->toContain('<html lang="en">');
    });

    test('a page the grid gives no address of is served at its default path under the base, where the menu links it', function () {
        $dir = sys_get_temp_dir() . '/helpers-default-pages-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[Const]\nWebURL = \"https://example.com\"\n[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\neconomy = \"https://example.com/charm/\"\n");
        file_put_contents("$dir/Helpers.ini", "[Helpers]\nHelpersURL = \"https://example.com/charm\"\nGuideSource = \"destinations.txt\"\n");
        file_put_contents("$dir/destinations.txt", "Visit\nFar beach|far.example.net:8002:Far\nShop\nFar mall|far.example.net:8002:Mall\n");
        $grid = Grid::at(helpers_environment(), $dir);
        $app = App::create(static fn(string $nick) => $grid);
        $back = 'Back to categories';

        $guide = base_get($app, '/charm/guide');
        expect($guide->getStatusCode())->toBe(200)
            ->and((string) $guide->getBody())->toContain('Visit', 'Shop', '?category=Visit')
            // A category, from the guide and from the home page that shows it: its destinations
            ->and((string) base_get($app, '/charm/guide?category=Visit')->getBody())->toContain($back)
            ->and((string) base_get($app, '/charm/?category=Visit')->getBody())->toContain($back)
            ->and(base_get($app, '/charm/nothing')->getStatusCode())->toBe(404);
    });

    test('the home page shows the blocks Helpers.ini gives, in its order: the search first by default', function () {
        $dir = sys_get_temp_dir() . '/helpers-home-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[Const]\nWebURL = \"https://example.com\"\n[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\neconomy = \"https://example.com/charm/\"\n");
        file_put_contents("$dir/destinations.txt", "Visit\nFar beach|far.example.net:8002:Far\n");
        $home = static function (string $helpers) use ($dir): string {
            file_put_contents("$dir/Helpers.ini", "[Helpers]\nHelpersURL = \"https://example.com/charm\"\nGuideSource = \"destinations.txt\"\n$helpers");
            $grid = Grid::at(helpers_environment(), $dir);

            return (string) base_get(App::create(static fn(string $nick) => $grid), '/charm/')->getBody();
        };
        $default = $home('');
        $chosen = $home("HomeBlocks = \"search, popular_places\"\n");

        expect(strpos($default, 'data-helpers-block="search"'))->toBeLessThan((int) strpos($default, 'data-helpers-block="destination_guide"'))
            ->and($chosen)->toContain('data-helpers-block="search"')->not->toContain('data-helpers-block="destination_guide"')
            // No grid here (no database of a grid): no info, no status, no message of the day of one; the guide and the search stay
            ->and($default)->not->toContain('data-helpers-block="grid-info"')->not->toContain('data-helpers-block="grid-status"')
            ->not->toContain('data-helpers-block="motd"')->toContain('/charm/guide');
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

describe('the brand of the pages', function () {
    test('is the one Helpers.ini gives: the icon of the site, its accent color, its font; nothing said keeps the look of the helpers', function () {
        $dir = sys_get_temp_dir() . '/helpers-brand-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[Const]\nWebURL = \"https://example.com\"\n[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\neconomy = \"https://example.com/charm/\"\n");
        $home = static function (string $helpers) use ($dir): string {
            file_put_contents("$dir/Helpers.ini", "[Helpers]\nHelpersURL = \"https://example.com/charm\"\n$helpers");
            $grid = Grid::at(helpers_environment(), $dir);

            return (string) base_get(App::create(static fn(string $nick) => $grid), '/charm/')->getBody();
        };
        $icon = 'https://example.com/w/wp-content/uploads/2019/09/2do-logo-square-80x80-bordertransp.png';
        $branded = $home("IconURL = \"$icon\"\nAccentColor = \"#7e3c68\"\nFont = \"Roboto\"\nFontURL = \"https://fonts.example.net/css?family=Roboto\"\n");
        $plain = $home('');

        expect($branded)->toContain('<link rel="icon" href="' . $icon . '">', '<link rel="apple-touch-icon" href="' . $icon . '">')
            ->toContain('--accent: #7e3c68', '--font: Roboto', '<link rel="stylesheet" href="https://fonts.example.net/css?family=Roboto">')
            ->and($plain)->not->toContain('rel="icon"')->not->toContain('--accent: #')->not->toContain('--font:');
    });
});

