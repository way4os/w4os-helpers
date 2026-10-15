<?php
/**
 * classes/class-view.php: the one layout of the pages, which adapts to who asks (a viewer gets the content, a browser the top
 * bar, the footer and the sidebars), the main content of a page, the blocks as variables of the templates, the URLs of the
 * helpers, the templates a grid can replace.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';
require_once dirname(__DIR__, 2) . '/classes/class-router.php';

defined('OPENSIM_GRID_NAME') || define('OPENSIM_GRID_NAME', 'Alpha <b>World');
defined('OPENSIM_LOGIN_URI') || define('OPENSIM_LOGIN_URI', 'http://127.0.0.1:9');
defined('OPENSIM_MOTD') || define('OPENSIM_MOTD', 'Hello <USERNAME>\nSecond line');

// The theme of the grid: what it holds replaces the files of the helpers
$theme = sys_get_temp_dir() . '/theme-' . bin2hex(random_bytes(4));
mkdir("$theme/templates/pages", 0o755, true);
mkdir("$theme/assets/css", 0o755, true);
file_put_contents("$theme/templates/pages/themed-page.twig", 'Themed {{ grid_name }}');
file_put_contents("$theme/templates/pages/home-page.twig", 'Home of the theme');
file_put_contents("$theme/assets/css/theme.css", 'body { color: red; }');
defined('OPENSIM_THEME_PATH') || define('OPENSIM_THEME_PATH', $theme);

const FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0';
const FIRESTORM = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) SecondLife/7.1.9 (Firestorm-Releasex64; default skin)';

/** A folder of templates the grid made, over the ones of the helpers. */
function own_templates(array $files): array
{
    $own = sys_get_temp_dir() . '/tpl-' . bin2hex(random_bytes(4));
    mkdir($own, 0o755, true);
    foreach ($files as $name => $content) {
        is_dir(dirname("$own/$name")) || mkdir(dirname("$own/$name"), 0o755, true);
        file_put_contents("$own/$name", $content);
    }

    return [$own, dirname(__DIR__, 2) . '/templates'];
}

describe('Who asks', function () {
    test('is a viewer by its User-Agent', function () {
        expect(OpenSim_Helpers_View::is_viewer(FIRESTORM, []))
            ->toBeTrue()
            ->and(OpenSim_Helpers_View::is_viewer('SecondLife/6.6.17.581101 (Second Life Release)', []))
            ->toBeTrue()
            ->and(OpenSim_Helpers_View::is_viewer(FIREFOX, []))
            ->toBeFalse()
            ->and(OpenSim_Helpers_View::is_viewer('', []))
            ->toBeFalse();
    });

    test('can be told by the address: a browser that wants to see what a viewer sees, and the other way round', function () {
        expect(OpenSim_Helpers_View::is_viewer(FIREFOX, ['viewer' => '1']))
            ->toBeTrue()
            ->and(OpenSim_Helpers_View::is_viewer(FIRESTORM, ['viewer' => '0']))
            ->toBeFalse();
    });
});

describe('URLs of the helpers', function () {
    test('start at the path of the helpers, /helpers by default', function () {
        expect(OpenSim_Helpers_View::base_path())
            ->toBe('/helpers')
            ->and(OpenSim_Helpers_View::assets_url('css/page.css'))
            ->toBe('/helpers/assets/css/page.css')
            ->and(OpenSim_Helpers_View::block_url('grid-info'))
            ->toBe('/helpers/blocks/grid-info');
    });
});

describe('theme of the grid', function () {
    test('replaces a template of the helpers, and adds its own', function () {
        $_SERVER['HTTP_USER_AGENT'] = FIREFOX;
        $themed = OpenSim_Helpers_View::page('themed');
        $home = OpenSim_Helpers_View::page('home');

        expect($themed)
            ->toContain('Themed Alpha &lt;b&gt;World')
            ->and($home)
            ->toContain('Home of the theme')
            ->not->toContain('class="grid-name"')
            // The layout is still the one of the helpers
            ->toContain('class="topbar"');
    });

    test('replaces an asset of the helpers, and adds its own, each with its date', function () {
        expect(OpenSim_Helpers_View::asset_file('css/theme.css'))
            ->toBe(OPENSIM_THEME_PATH . '/assets/css/theme.css')
            ->and(OpenSim_Helpers_View::asset_file('css/page.css'))
            ->toBe(dirname(__DIR__, 2) . '/assets/css/page.css')
            ->and(OpenSim_Helpers_View::asset_file('css/nothing.css'))
            ->toBeNull()
            ->and(OpenSim_Helpers_View::asset_url('css/theme.css'))
            ->toMatch('#^/helpers/assets/css/theme\.css\?v=\d+$#');
    });
});

describe('home page', function () {
    test('shows the destination guide, the message of an empty one included, the search form alone, and no place when none has traffic', function () {
        $_SERVER['HTTP_USER_AGENT'] = FIREFOX;
        ini_set('error_log', '/dev/null');
        // The templates of the helpers alone, whatever theme the grid has
        $html = OpenSim_Helpers_View::page('home', [], [dirname(__DIR__, 2) . '/templates']);

        expect($html)
            ->toContain('id="guide"')
            ->toContain('realm of destinations')
            ->toContain('block-search')
            ->toContain('type="search"')
            // Embedded, the search is the form: the results are on its page
            ->not->toContain('search-topics')
            ->not->toContain('block-popular-places');
    });
});

describe('columns of a page', function () {
    test('are all there for a browser, with the top bar and the footer', function () {
        $_SERVER['HTTP_USER_AGENT'] = FIREFOX;
        $html = OpenSim_Helpers_View::page('home');

        expect($html)
            ->toContain('<body class="browser page-home">')
            ->toContain('class="topbar"')
            ->toContain('<footer>')
            ->toContain('sidebar-left')
            ->toContain('sidebar-right')
            ->toContain('block-grid-info')
            ->toContain('block-motd')
            ->toContain('Alpha &lt;b&gt;World')
            ->not->toContain('Alpha <b>World');
    });

    test('are the content alone for a viewer, the splash page excepted', function () {
        $_SERVER['HTTP_USER_AGENT'] = FIRESTORM;
        $splash = OpenSim_Helpers_View::page('splash');

        expect($splash)
            ->toContain('<body class="viewer page-splash">')
            ->toContain('sidebar-left')
            ->toContain('block-grid-status')
            ->not->toContain('class="topbar"')
            ->not->toContain('<footer>');

        $home = OpenSim_Helpers_View::page('home');
        expect($home)
            ->toContain('<body class="viewer page-home">')
            ->toContain('content-only')
            ->not->toContain('sidebar-left');
    });
});

describe('main content of a page', function () {
    test('is the block of the same name, when the grid has no template for it', function () {
        $_SERVER['HTTP_USER_AGENT'] = FIREFOX;
        $_SERVER['HTTP_USER_AGENT'] = FIRESTORM;
        // In a viewer, a page of a block has no sidebar: the block is all there is
        $html = OpenSim_Helpers_View::page('motd');

        expect($html)->toContain('<main id="main">')->toContain('Second line')->not->toContain('sidebar');
    });

    test('is the template of the grid for that page when it has one', function () {
        $_SERVER['HTTP_USER_AGENT'] = FIRESTORM;
        $dirs = own_templates(['pages/motd-page.twig' => 'Mine {{ grid_name }}']);
        $html = OpenSim_Helpers_View::page('motd', [], $dirs);

        expect($html)->toContain('Mine Alpha &lt;b&gt;World')->not->toContain('Second line');
    });

    test('is nothing for a page that has neither: it is not found', function () {
        expect(OpenSim_Helpers_View::page('nothing'))->toBeNull();
    });
});

describe('A template', function () {
    test('shows a block as a variable of its name, only the ones it uses are made', function () {
        $dirs = own_templates(['pages/x-page.twig' => '[{{ motd }}] [{{ grid_info }}]']);
        $html = OpenSim_Helpers_View::render('pages/x-page.twig', OpenSim_Helpers_View::context('x'), $dirs);

        expect($html)->toContain('block-motd')->toContain('block-grid-info')->not->toContain('block-grid-status');
    });

    test('keeps the markup of the grid name as text, not the one of a block', function () {
        $dirs = own_templates(['pages/x-page.twig' => '{{ grid_name }} {{ motd }}']);
        $html = OpenSim_Helpers_View::render('pages/x-page.twig', OpenSim_Helpers_View::context('x'), $dirs);

        expect($html)->toContain('Alpha &lt;b&gt;World')->toContain('<section class="block block-motd">');
    });

    test('can still ask a block with arguments, for what needs more', function () {
        $dirs = own_templates(['pages/x-page.twig' => "{{ render_block('motd', { title: 'News <i>' }) }}"]);
        $html = OpenSim_Helpers_View::render('pages/x-page.twig', OpenSim_Helpers_View::context('x'), $dirs);

        expect($html)->toContain('News &lt;i&gt;');
    });
});

describe('menu', function () {
    $labels = OpenSim_Helpers_View::labels();

    test('has the pages the site serves, at the address of each, in a fixed order', function () use ($labels) {
        $menu = OpenSim_Helpers_View::menu(OpenSim_Helpers_Router::defaults('/helpers'), $labels);

        expect(array_column($menu, 'slug'))
            ->toBe(['home', 'destination_guide', 'directory_info', 'health'])
            ->and(array_column($menu, 'url'))
            ->toBe(['/', '/helpers/guide.php', '/helpers/directory_info.php', '/health']);
    });

    test('follows the addresses the grid gave, and leaves out what it does not serve', function () use ($labels) {
        $menu = OpenSim_Helpers_View::menu(['/' => '@home', '/places' => '@destination_guide', '/helpers/query.php' => 'query.php'], $labels);

        expect(array_column($menu, 'slug'))
            ->toBe(['home', 'destination_guide'])
            ->and($menu[1]['url'])
            ->toBe('/places');
    });

    test('is in the top bar of a browser, the current page marked, and not in a viewer', function () {
        $dirs = [dirname(__DIR__, 2) . '/templates'];
        $browser = OpenSim_Helpers_View::render('layouts/base-layout.twig', array_merge(['main' => 'x'], OpenSim_Helpers_View::context('health'), ['viewer' => false]), $dirs);
        $viewer = OpenSim_Helpers_View::render('layouts/base-layout.twig', array_merge(['main' => 'x'], OpenSim_Helpers_View::context('health'), ['viewer' => true]), $dirs);

        expect($browser)
            ->toContain('<a href="/health" aria-current="page">Health</a>')
            ->toContain('<a href="/">Home</a>')
            ->and($viewer)
            ->not->toContain('<nav>');
    });
});

describe('health block', function () {
    test('is one table: the files and the sections are rows of it, the cert, the time and the result are columns on the right, in this order', function () {
        $html = OpenSim_Helpers_View::render('components/health-block.twig', [
            'title' => 'Health',
            'when' => '2026-10-07T10:00:00+00:00',
            'cert_title' => 'certificate not trusted by this server',
            'groups' => [
                [
                    'file' => 'OpenSim.ini',
                    'first_of_file' => true,
                    'title' => '[Search]',
                    'rows' => [
                        ['label' => 'SearchURL', 'url' => 'https://play.example.org/helpers/query.php', 'tag' => 'API', 'cert' => true, 'level' => 'ok', 'status' => '200', 'ms' => 40],
                        ['label' => 'About', 'url' => '', 'level' => 'none', 'status' => '', 'ms' => null],
                    ],
                ],
                ['file' => 'OpenSim.ini', 'first_of_file' => false, 'title' => '[Messaging]', 'rows' => [['label' => 'OfflineMessageURL', 'url' => '', 'level' => 'none', 'status' => '', 'ms' => null]]],
                ['file' => 'PHP', 'first_of_file' => true, 'title' => '', 'rows' => [['label' => 'imagick', 'url' => '', 'level' => 'error', 'status' => 'missing', 'ms' => null]]],
            ],
        ]);

        expect(substr_count($html, '<table'))
            ->toBe(1)
            // The file is named once, the sections are under it, a group with no title has none
            ->and(substr_count($html, 'health-file'))
            ->toBe(2)
            ->and(substr_count($html, 'health-section'))
            ->toBe(2)
            ->and(strpos($html, 'OpenSim.ini'))
            ->toBeLessThan(strpos($html, '[Search]'))
            // Every row has the same columns, so that the results line up
            ->and(substr_count($html, 'class="health-status"'))
            ->toBe(4)
            ->and(substr_count($html, 'class="health-cert"'))
            ->toBe(4)
            ->and(strpos($html, 'health-cert"'))
            ->toBeLessThan(strpos($html, 'health-ms"'))
            ->and(strpos($html, 'health-ms"'))
            ->toBeLessThan(strpos($html, 'health-status"'))
            // The sigle of the certificate says what it is once, in a title; nothing is said of what is not defined
            ->and(substr_count($html, 'certificate not trusted by this server'))
            ->toBe(1)
            ->and(substr_count($html, 'not defined'))
            ->toBe(0)
            ->and(substr_count($html, 'status status-'))
            ->toBe(2)
            ->and(substr_count($html, 'health-tag'))
            ->toBe(1);
    });

    test('shows a value that is not an address as text, and what differs from what the grid says under the setting, in an error', function () {
        $html = OpenSim_Helpers_View::render('components/health-block.twig', [
            'title' => 'Health',
            'when' => 'now',
            'cert_title' => 'certificate not trusted by this server',
            'groups' => [
                [
                    'file' => 'get_grid_info',
                    'first_of_file' => true,
                    'title' => '',
                    'rows' => [
                        ['label' => 'gridname', 'url' => '', 'text' => 'Alpha World', 'level' => 'none', 'status' => '', 'ms' => null],
                        ['label' => 'economy', 'url' => 'https://other.example.org/helpers', 'detail' => 'get_grid_info: https://play.example.org/helpers', 'level' => 'error', 'status' => 'differs', 'ms' => null],
                    ],
                ],
            ],
        ]);

        expect($html)
            ->toContain('<code>Alpha World</code>')
            ->toContain('status status-error">differs')
            ->toContain('<small>get_grid_info: https://play.example.org/helpers</small>')
            // The title of a group that has none is not a row of the table
            ->not->toContain('health-section');
    });

    test('shows an address as the config says it and links the one it was asked with (the profile of the owner of the grid)', function () {
        $html = OpenSim_Helpers_View::render('components/health-block.twig', [
            'title' => 'Health',
            'when' => 'now',
            'cert_title' => '',
            'groups' => [
                [
                    'file' => 'Robust.HG.ini',
                    'first_of_file' => true,
                    'title' => '[GridInfoService]',
                    'rows' => [['label' => 'web_profile_url', 'url' => 'https://play.example.org/profile/Jane.Doe', 'display' => 'https://play.example.org/profile/[AGENT_NAME]', 'level' => 'ok', 'status' => '200', 'ms' => 5]],
                ],
            ],
        ]);

        expect($html)->toContain('<a href="https://play.example.org/profile/Jane.Doe"><code>https://play.example.org/profile/[AGENT_NAME]</code></a>');
    });
});
