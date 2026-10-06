<?php
/**
 * classes/class-view.php: the layout that adapts to who asks (a viewer gets the content, a browser the top bar and the footer
 * too), the URLs of the helpers, the templates a grid can replace.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';

defined('OPENSIM_GRID_NAME') || define('OPENSIM_GRID_NAME', 'Alpha <b>World');
defined('OPENSIM_LOGIN_URI') || define('OPENSIM_LOGIN_URI', 'http://127.0.0.1:9');

const FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0';
const FIRESTORM = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) SecondLife/7.1.9 (Firestorm-Releasex64; default skin)';

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

describe('The URLs of the helpers', function () {
    test('start at the path of the helpers, /helpers by default', function () {
        expect(OpenSim_Helpers_View::base_path())
            ->toBe('/helpers')
            ->and(OpenSim_Helpers_View::static_url('css/page.css'))
            ->toBe('/helpers/static/css/page.css')
            ->and(OpenSim_Helpers_View::block_url('grid-info'))
            ->toBe('/helpers/blocks/grid-info');
    });
});

describe('A page', function () {
    $context = fn(bool $viewer) => OpenSim_Helpers_View::context() + [] + ['viewer' => $viewer];

    test('has the top bar and the footer in a browser', function () use ($context) {
        $html = OpenSim_Helpers_View::render('pages/home.twig', ['viewer' => false] + $context(false));

        expect($html)
            ->toContain('<body class="browser">')
            ->toContain('class="topbar"')
            ->toContain('<footer>')
            ->toContain('Alpha &lt;b&gt;World')
            ->not->toContain('Alpha <b>World');
    });

    test('is the content alone in a viewer', function () use ($context) {
        $html = OpenSim_Helpers_View::render('pages/splash.twig', ['viewer' => true] + $context(true));

        expect($html)
            ->toContain('<body class="viewer">')
            ->toContain('block-grid-info')
            ->not->toContain('class="topbar"')
            ->not->toContain('<footer>');
    });

    test('shows the blocks it includes, and nothing for one that does not exist', function () use ($context) {
        $html = OpenSim_Helpers_View::render('pages/home.twig', $context(false));

        expect($html)
            ->toContain('127.0.0.1:9')
            ->toContain('hop://127.0.0.1:9')
            ->not->toContain('block-popular-places');
    });

    test('is the template of the grid when it has one', function () use ($context) {
        $own = sys_get_temp_dir() . '/tpl-' . bin2hex(random_bytes(4));
        mkdir("$own/pages", 0o755, true);
        file_put_contents("$own/pages/home.twig", 'Mine {{ grid_name }}');

        expect(OpenSim_Helpers_View::render('pages/home.twig', $context(false), [$own, dirname(__DIR__, 2) . '/templates']))->toBe(
            'Mine Alpha &lt;b&gt;World',
        );
    });
});
