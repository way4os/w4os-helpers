<?php
/**
 * classes/class-router.php: which script or page answers a URL.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/classes/class-router.php';

describe('Router', function () {
    test('serves the home and the splash page by default', function () {
        expect(OpenSim_Helpers_Router::match('/'))
            ->toBe(['page', 'home'])
            ->and(OpenSim_Helpers_Router::match('/welcome/'))
            ->toBe(['page', 'splash']);
    });

    test('serves a script under any prefix', function () {
        expect(OpenSim_Helpers_Router::match('/helpers/query.php'))
            ->toBe(['script', 'query.php'])
            ->and(OpenSim_Helpers_Router::match('/a/b/currency.php'))
            ->toBe(['script', 'currency.php']);
    });

    test('serves a script called with what OpenSimulator adds to it', function () {
        expect(OpenSim_Helpers_Router::match('/helpers/offline.php/SaveMessage/'))
            ->toBe(['script', 'offline.php'])
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/offline.php/SaveMessage/'))
            ->toBe('/SaveMessage/')
            ->and(OpenSim_Helpers_Router::pathInfo('/offline.php/RetrieveMessages/'))
            ->toBe('/RetrieveMessages/')
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/query.php'))
            ->toBe('')
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/'))
            ->toBe('');
    });

    test('serves the routes of the operator', function () {
        $own = ['/search/' => 'query.php', '/hello' => '@splash'];

        expect(OpenSim_Helpers_Router::match('/search', $own))
            ->toBe(['script', 'query.php'])
            ->and(OpenSim_Helpers_Router::match('/hello', $own))
            ->toBe(['page', 'splash'])
            ->and(OpenSim_Helpers_Router::match('/welcome', $own))
            ->toBe(['page', 'splash']);
    });

    test('serves the static files and the blocks under any prefix, nothing outside them', function () {
        expect(OpenSim_Helpers_Router::match('/static/logos/logo.svg'))
            ->toBe(['static', 'logos/logo.svg'])
            ->and(OpenSim_Helpers_Router::match('/helpers/static/css/page.css'))
            ->toBe(['static', 'css/page.css'])
            ->and(OpenSim_Helpers_Router::match('/static/../includes/config.php'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/helpers/blocks/grid-status'))
            ->toBe(['block', 'grid-status'])
            ->and(OpenSim_Helpers_Router::match('/blocks/nothing'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/assets/logos/logo.svg'))
            ->toBeNull();
    });

    test('knows no other URL', function () {
        expect(OpenSim_Helpers_Router::match('/nothing'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/includes/config.php'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/x', ['/x' => 'config.php']))
            ->toBeNull();
    });

    test('gives a script back to the entry, which includes it in the global scope', function () {
        $_SERVER['REQUEST_URI'] = '/helpers/query.php';
        $cwd = getcwd();
        $script = OpenSim_Helpers_Router::run();
        chdir($cwd);

        expect($script)->toBe(dirname(__DIR__, 2) . '/query.php');
    });

    test('answers a page itself and gives nothing back', function () {
        $_SERVER['REQUEST_URI'] = '/nothing';
        ob_start();
        $script = OpenSim_Helpers_Router::run();
        ob_end_clean();

        expect($script)->toBeNull();
    });

    test('finds the file of a page script, which keeps its name in the URL', function () {
        $_SERVER['REQUEST_URI'] = '/helpers/guide.php';
        $cwd = getcwd();
        $script = OpenSim_Helpers_Router::run();
        chdir($cwd);

        expect($script)->toBe(dirname(__DIR__, 2) . '/pages/guide.php');
    });
});
