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

    test('serves a script at its route, and nowhere else: no ghost service', function () {
        $routes = ['/helpers/query.php' => 'query.php', '/v3/currency.php' => 'currency.php'];

        expect(OpenSim_Helpers_Router::match('/helpers/query.php', $routes))
            ->toBe(['script', 'query.php'])
            ->and(OpenSim_Helpers_Router::match('/v3/currency.php', $routes))
            ->toBe(['script', 'currency.php'])
            ->and(OpenSim_Helpers_Router::match('/helpers/currency.php', $routes))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/a/b/query.php', $routes))
            ->toBeNull();
    });

    test('serves what is under a route too, and gives what follows it as PATH_INFO', function () {
        $routes = ['/helpers/offline.php' => 'offline.php', '/guide' => '@guide', '/' => '@home'];

        expect(OpenSim_Helpers_Router::match('/helpers/offline.php/SaveMessage/', $routes))
            ->toBe(['script', 'offline.php'])
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/offline.php/SaveMessage/', $routes))
            ->toBe('/SaveMessage/')
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/offline.php/RetrieveMessages/', $routes))
            ->toBe('/RetrieveMessages/')
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/offline.php', $routes))
            ->toBe('')
            ->and(OpenSim_Helpers_Router::match('/guide/places/', $routes))
            ->toBe(['page', 'guide'])
            ->and(OpenSim_Helpers_Router::pathInfo('/guide/places/', $routes))
            ->toBe('/places/')
            // The root is the root, not everything
            ->and(OpenSim_Helpers_Router::match('/nothing', $routes))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::pathInfo('/', $routes))
            ->toBe('');
    });

    test('serves the longest route', function () {
        $routes = ['/a' => 'query.php', '/a/b' => 'register.php'];

        expect(OpenSim_Helpers_Router::match('/a/b/c', $routes))->toBe(['script', 'register.php']);
    });

    test('serves the routes it is given, the defaults without any', function () {
        $routes = ['/search/' => 'query.php', '/hello' => '@splash'];

        expect(OpenSim_Helpers_Router::match('/search', $routes))
            ->toBe(['script', 'query.php'])
            ->and(OpenSim_Helpers_Router::match('/hello', $routes))
            ->toBe(['page', 'splash'])
            ->and(OpenSim_Helpers_Router::match('/welcome', $routes))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/welcome'))
            ->toBe(['page', 'splash'])
            ->and(OpenSim_Helpers_Router::match('/helpers/query.php'))
            ->toBe(['script', 'query.php']);
    });

    test('serves the assets and the blocks under any prefix, nothing outside them', function () {
        expect(OpenSim_Helpers_Router::match('/assets/images/logos/logo.svg'))
            ->toBe(['asset', 'images/logos/logo.svg'])
            ->and(OpenSim_Helpers_Router::match('/helpers/assets/css/page.css'))
            ->toBe(['asset', 'css/page.css'])
            ->and(OpenSim_Helpers_Router::match('/assets/../includes/config.php'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Router::match('/helpers/blocks/grid-status'))
            ->toBe(['block', 'grid-status'])
            ->and(OpenSim_Helpers_Router::match('/blocks/nothing'))
            ->toBeNull();
    });

    test('serves a profile by the name of the avatar, in the three forms', function () {
        expect(OpenSim_Helpers_Router::match('/profile/jane.doe'))
            ->toBe(['page', 'profile', ['name' => 'jane.doe']])
            ->and(OpenSim_Helpers_Router::match('/profile/Jane-Doe/'))
            ->toBe(['page', 'profile', ['name' => 'Jane.Doe']])
            ->and(OpenSim_Helpers_Router::match('/@jane.doe'))
            ->toBe(['page', 'profile', ['name' => 'jane.doe']])
            ->and(OpenSim_Helpers_Router::match('/@jane.doe@grid.example:8002'))
            ->toBe(['page', 'profile', ['name' => 'jane.doe@grid.example:8002']])
            ->and(OpenSim_Helpers_Router::match('/profile'))
            ->toBe(['page', 'profile'])
            ->and(OpenSim_Helpers_Router::match('/members/jane.doe', ['/members' => '@profile']))
            ->toBe(['page', 'profile', ['name' => 'jane.doe']])
            // What is under the route of the profiles is for the profile page
            ->and(OpenSim_Helpers_Router::match('/profile/jane/doe'))
            ->toBe(['page', 'profile']);
    });

    test('serves the health page', function () {
        expect(OpenSim_Helpers_Router::match('/health'))->toBe(['page', 'health']);
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

    test('serves as pages what were scripts, which keep their name in the URL', function () {
        expect(OpenSim_Helpers_Router::match('/helpers/guide.php'))
            ->toBe(['page', 'guide'])
            ->and(OpenSim_Helpers_Router::match('/helpers/directory_info.php'))
            ->toBe(['page', 'directory_info'])
            ->and(OpenSim_Helpers_Router::match('/guide', ['/guide' => 'guide.php']))
            ->toBe(['page', 'guide']);
    });
});
