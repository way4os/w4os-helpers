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
        $routes = ['/helpers/offline.php' => 'offline.php', '/guide' => '@destination_guide', '/' => '@home'];

        expect(OpenSim_Helpers_Router::match('/helpers/offline.php/SaveMessage/', $routes))
            ->toBe(['script', 'offline.php'])
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/offline.php/SaveMessage/', $routes))
            ->toBe('/SaveMessage/')
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/offline.php/RetrieveMessages/', $routes))
            ->toBe('/RetrieveMessages/')
            ->and(OpenSim_Helpers_Router::pathInfo('/helpers/offline.php', $routes))
            ->toBe('')
            ->and(OpenSim_Helpers_Router::match('/guide/places/', $routes))
            ->toBe(['page', 'destination_guide'])
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

    test('serves the health page at its own address only: it is for who manages the grid', function () {
        expect(OpenSim_Helpers_Router::match('/health'))
            ->toBe(['page', 'health'])
            ->and(OpenSim_Helpers_Router::match('/helpers/health'))
            ->toBeNull();
    });

    test('answers at the address of the helpers itself, and not at what is under it that it does not know', function () {
        // Not a page: a teapot, unless the grid says the address is the one of a page
        expect(OpenSim_Helpers_Router::match('/helpers'))
            ->toBe(['teapot', 'teapot'])
            ->and(OpenSim_Helpers_Router::match('/helpers/'))
            ->toBe(['teapot', 'teapot'])
            ->and(OpenSim_Helpers_Router::match('/helpers', ['/helpers' => '@splash']))
            ->toBe(['page', 'splash'])
            ->and(OpenSim_Helpers_Router::match('/helpers/nothing'))
            ->toBeNull()
            // A site that has them at the root has no such address, the root is the home page
            ->and(OpenSim_Helpers_Router::defaults(''))
            ->not->toHaveKey('');
    });

    test('serves the public pages of the grid, what it is and its state, without a word of the grid', function () {
        expect(OpenSim_Helpers_Router::match('/helpers/grid_info'))
            ->toBe(['page', 'grid_info'])
            ->and(OpenSim_Helpers_Router::match('/helpers/grid_status'))
            ->toBe(['page', 'grid_status'])
            ->and(OpenSim_Helpers_Router::match('/status', ['/status' => '@grid_status']))
            ->toBe(['page', 'grid_status']);
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

    test('serves as pages what were scripts, which keep their name in the URL', function () {
        expect(OpenSim_Helpers_Router::match('/helpers/guide.php'))
            ->toBe(['page', 'destination_guide'])
            ->and(OpenSim_Helpers_Router::match('/helpers/directory_info.php'))
            ->toBe(['page', 'directory_info'])
            ->and(OpenSim_Helpers_Router::match('/guide', ['/guide' => 'guide.php']))
            ->toBe(['page', 'destination_guide']);
    });
});

/** A web server that gives every request to the router, with the routes it is given. */
function router_server(array $routes): array
{
    $root = dirname(__DIR__, 2);
    $dir = sys_get_temp_dir() . '/router-server-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents(
        "$dir/index.php",
        "<?php define('OPENSIM_ENGINE', true); require '$root/vendor/autoload.php'; require '$root/includes/functions.php';" .
            'define("OPENSIM_ROUTES", ' . var_export($routes, true) . ");\nrequire '$root/classes/class-router.php'; OpenSim_Helpers_Router::run();",
    );
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-d', 'log_errors=0', '-S', "127.0.0.1:$port", "$dir/index.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    set_error_handler(static fn() => true);
    for ($try = 0; $try < 50 && !($connection = fsockopen('127.0.0.1', $port)); ++$try) {
        usleep(100_000);
    }
    restore_error_handler();
    if (!empty($connection)) {
        fclose($connection);
    }

    return [$process, $port];
}

/** What a server answers to a request: the HTTP code, the status line, the Allow header. */
function router_ask(int $port, string $path, string $method = 'GET'): array
{
    $handle = curl_init("http://127.0.0.1:$port$path");
    $headers = '';
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headers) {
            $headers .= $line;

            return strlen($line);
        },
    ]);
    $body = (string) curl_exec($handle);

    return ['code' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'status' => strtok($headers, "\r\n"), 'allow' => preg_match('/^Allow: (.*)$/mi', $headers, $m) ? trim($m[1]) : null, 'body' => $body];
}

describe('What the router does not serve', function () {
    test('says why with the code that tells it, never with a 500', function () {
        [$process, $port] = router_server([
            '/' => '@home',
            '/welcome' => '@splash',
            '/profile' => '@profile',
            '/helpers' => '@teapot!',
            '/helpers/query.php' => 'query.php',
        ]);
        $answers = [
            // An address that is not a page, and not an error either
            'helpers' => router_ask($port, '/helpers'),
            'helpers slash' => router_ask($port, '/helpers/'),
            'profile' => router_ask($port, '/profile'),
            // An address that is nothing
            'nothing' => router_ask($port, '/nothing'),
            'under' => router_ask($port, '/helpers/nothing'),
            // A page is read, not called
            'post page' => router_ask($port, '/welcome', 'POST'),
            'put profile' => router_ask($port, '/profile/jane.doe', 'PUT'),
        ];
        proc_terminate($process);

        expect($answers['helpers'])
            ->toMatchArray(['code' => 418, 'status' => "HTTP/1.1 418 I'm a teapot"])
            ->and($answers['helpers slash']['code'])
            ->toBe(418)
            ->and($answers['profile']['code'])
            ->toBe(418)
            ->and($answers['nothing'])
            ->toMatchArray(['code' => 404, 'status' => 'HTTP/1.1 404 Not found'])
            ->and($answers['under']['code'])
            ->toBe(404)
            ->and($answers['post page'])
            ->toMatchArray(['code' => 405, 'allow' => 'GET, HEAD'])
            ->and($answers['put profile']['code'])
            ->toBe(405);
    });
});
