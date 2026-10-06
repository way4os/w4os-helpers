<?php
/**
 * Block health: what an address answering means, and the answers of real addresses (a small web server of the test).
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';

/** A web server that answers the code its path says: /200, /404, /500, /redirect. */
function code_server(): array
{
    $dir = sys_get_temp_dir() . '/code-server-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents(
        "$dir/router.php",
        '<?php $path = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
        if ($path === "redirect") { header("Location: /200", true, 302); return; }
        http_response_code(is_numeric($path) ? (int) $path : 200); echo str_repeat("x", 5000);',
    );
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", "$dir/router.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    // Until it listens (a refused connection is expected meanwhile)
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

describe('An address in the health page', function () {
    test('is fine for 2xx and 3xx, answers for 4xx, is not for 5xx or no answer, and is not defined when empty', function () {
        expect(OpenSim_Helpers_Block_Health::verdict(200, '', 12))
            ->toBe(['level' => 'ok', 'status' => '200', 'ms' => 12])
            ->and(OpenSim_Helpers_Block_Health::verdict(302, '', 3)['level'])
            ->toBe('ok')
            ->and(OpenSim_Helpers_Block_Health::verdict(405, '', 3)['level'])
            ->toBe('warn')
            ->and(OpenSim_Helpers_Block_Health::verdict(500, '', 3)['level'])
            ->toBe('error')
            ->and(OpenSim_Helpers_Block_Health::verdict(0, 'Could not resolve host: x', 0))
            ->toBe(['level' => 'error', 'status' => 'no answer: Could not resolve host: x', 'ms' => null])
            ->and(OpenSim_Helpers_Block_Health::verdict(null, '', 0))
            ->toBe(['level' => 'none', 'status' => 'not defined', 'ms' => null]);
    });

    test('is asked, all of them, and the answer is the one of each', function () {
        [$process, $port] = code_server();
        $url = fn(string $path) => "http://127.0.0.1:$port/$path";
        $groups = OpenSim_Helpers_Block_Health::answers([
            [
                'title' => 'One',
                'rows' => [
                    ['label' => 'fine', 'url' => $url('200'), 'method' => 'get'],
                    ['label' => 'missing', 'url' => $url('404'), 'method' => 'get'],
                    ['label' => 'broken', 'url' => $url('500'), 'method' => 'get'],
                    ['label' => 'moved', 'url' => $url('redirect'), 'method' => 'get'],
                    ['label' => 'api', 'url' => $url('200'), 'method' => 'xmlrpc'],
                    ['label' => 'empty', 'url' => '', 'method' => 'get'],
                    ['label' => 'nobody', 'url' => 'http://127.0.0.1:9/', 'method' => 'get'],
                ],
            ],
        ]);
        proc_terminate($process);
        $by = array_column($groups[0]['rows'], null, 'label');

        expect($by['fine']['status'])
            ->toBe('200')
            ->and($by['missing']['level'])
            ->toBe('warn')
            ->and($by['broken']['level'])
            ->toBe('error')
            ->and($by['moved']['status'])
            ->toBe('302')
            ->and($by['api']['level'])
            ->toBe('ok')
            ->and($by['empty']['status'])
            ->toBe('not defined')
            ->and($by['nobody']['level'])
            ->toBe('error')
            ->and($by['nobody']['status'])
            ->toStartWith('no answer')
            ->and($by['fine'])
            ->not->toHaveKey('method');
    });
});

describe('Block health', function () {
    test('lists what the install needs, and says what is not defined', function () {
        $rows = array_column(OpenSim_Helpers_Block_Health::system(), null, 'label');

        expect($rows['XML-RPC (extension or polyfill)']['level'])
            ->toBe('ok')
            ->and($rows['Destination guide source']['status'])
            ->toBe('not defined');
    });
});
