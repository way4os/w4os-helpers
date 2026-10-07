<?php
/**
 * Block health: what an address answering means, the answers of real addresses (a small web server of the test), and the page
 * that names the sections and the settings as the ini files do.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';

/** A web server that answers the code its path says: /200, /404, /500, /redirect (to /200), /redirect404 (to /404). */
function code_server(): array
{
    $dir = sys_get_temp_dir() . '/code-server-' . bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents(
        "$dir/router.php",
        '<?php $path = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
        if ($path === "redirect") { header("Location: /200", true, 308); return; }
        if ($path === "redirect404") { header("Location: /404", true, 308); return; }
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
    test('is fine for 2xx, answers for 4xx, is not for 5xx, gives the error when nothing answers, and is not defined when empty', function () {
        expect(OpenSim_Helpers_Block_Health::verdict(200, '', 12))
            ->toBe(['level' => 'ok', 'status' => '200', 'ms' => 12])
            ->and(OpenSim_Helpers_Block_Health::verdict(404, '', 3))
            ->toBe(['level' => 'warn', 'status' => '404', 'ms' => 3])
            ->and(OpenSim_Helpers_Block_Health::verdict(405, '', 3)['level'])
            ->toBe('warn')
            ->and(OpenSim_Helpers_Block_Health::verdict(500, '', 3)['level'])
            ->toBe('error')
            // No answer is not an answer: the code of the error is, and what it says
            ->and(OpenSim_Helpers_Block_Health::verdict(0, 'Could not resolve host: x', 0, 6))
            ->toBe(['level' => 'error', 'status' => 'cURL error 6', 'ms' => null, 'detail' => 'Could not resolve host: x'])
            ->and(OpenSim_Helpers_Block_Health::verdict(null, '', 0))
            ->toBe(['level' => 'none', 'status' => 'not defined', 'ms' => null]);
    });

    test('is asked, all of them, and the answer is the one of each, where the redirections lead', function () {
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
                    ['label' => 'movedaway', 'url' => $url('redirect404'), 'method' => 'get'],
                    ['label' => 'api', 'url' => $url('200'), 'method' => 'xmlrpc'],
                    ['label' => 'probed', 'url' => $url('404'), 'probe' => $url('200'), 'method' => 'get', 'note' => '/get_grid_info'],
                    ['label' => 'template', 'url' => $url('404'), 'method' => 'get', 'skip' => 'not asked'],
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
            // The redirection is anecdotal, the result at the end is what counts
            ->and($by['moved']['status'])
            ->toBe('200')
            ->and($by['movedaway']['status'])
            ->toBe('404')
            ->and($by['api']['level'])
            ->toBe('ok')
            ->and($by['probed'])
            ->toMatchArray(['level' => 'ok', 'url' => $url('404'), 'note' => '/get_grid_info'])
            ->and($by['template'])
            ->toMatchArray(['level' => 'none', 'status' => 'not asked'])
            ->and($by['empty']['status'])
            ->toBe('not defined')
            ->and($by['nobody'])
            ->toMatchArray(['level' => 'error', 'status' => 'cURL error 7'])
            ->and($by['nobody']['detail'])
            ->not->toBe('')
            ->and($by['fine'])
            ->not->toHaveKeys(['method', 'probe', 'skip']);
    });
});

describe('An address with a certificate this server does not trust', function () {
    test('answers its code, the check of the certificate said apart', function () {
        $openssl = trim((string) shell_exec('command -v openssl 2>/dev/null'));
        $dir = sys_get_temp_dir() . '/tls-' . bin2hex(random_bytes(4));
        mkdir($dir);
        exec(escapeshellarg($openssl) . " req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -keyout $dir/k.pem -out $dir/c.pem -days 1 -subj /CN=localhost 2>/dev/null", $out, $code);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        // A server that answers 200 to whatever is asked, with a certificate nobody signed
        $process = proc_open([$openssl, 's_server', '-accept', (string) $port, '-cert', "$dir/c.pem", '-key', "$dir/k.pem", '-www', '-quiet'], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        set_error_handler(static fn() => true);
        for ($try = 0; $try < 50 && !($connection = fsockopen('127.0.0.1', $port)); ++$try) {
            usleep(100_000);
        }
        restore_error_handler();
        if (!empty($connection)) {
            fclose($connection);
        }

        $groups = OpenSim_Helpers_Block_Health::answers([['title' => 'One', 'rows' => [['label' => 'tls', 'url' => "https://127.0.0.1:$port/", 'method' => 'get']]]]);
        proc_terminate($process);
        $row = $groups[0]['rows'][0];

        expect($code)->toBe(0)->and($row)->toMatchArray(['status' => '200', 'level' => 'ok', 'note' => 'certificate not trusted by this server']);
    })->skip(fn() => trim((string) shell_exec('command -v openssl 2>/dev/null')) === '', 'no openssl to make a certificate');
});

describe('Block health', function () {
    /** What a grid says, as the settings give it. */
    $settings = fn(array $sections, array $helpers = []) => [
        'sources' => ['Robust.HG.ini' => $sections],
        'web_url' => 'https://play.example.org',
        'helpers' => $helpers,
    ];
    $routes = [
        '/helpers/currency.php' => 'currency.php',
        '/helpers/landtool.php' => 'landtool.php',
        '/helpers/query.php' => 'query.php',
        '/helpers/register.php' => 'register.php',
        '/helpers/offline.php' => 'offline.php',
        '/helpers/textgen.php' => 'textgen.php',
    ];
    $by = fn(array $groups) => array_column(array_map(fn($g) => ['title' => $g['title'], 'rows' => array_column($g['rows'], null, 'label')], $groups), 'rows', 'title');

    test('names the sections and the settings as the ini files do, every address of [GridInfoService] in it', function () use ($settings, $routes, $by) {
        $groups = OpenSim_Helpers_Block_Health::plan(
            $settings(
                [
                    'GridInfoService' => [
                        'gridname' => 'Alpha',
                        'login' => 'http://play.example.org:8002/',
                        'economy' => 'https://play.example.org/helpers',
                        'web_profile_url' => 'https://play.example.org/profile/[AGENT_NAME]',
                        'partner' => 'https://partner.example.org/',
                    ],
                    'LoginService' => ['DestinationGuide' => 'https://play.example.org/helpers/guide.php'],
                    'DataSnapshot' => ['DATA_SRV_Alpha' => 'https://play.example.org/helpers/register.php'],
                ],
                ['HypEventsURL' => 'https://2do.directory/events'],
            ),
            'http://play.example.org:8002/',
            [],
            $routes,
            '',
        );
        $rows = $by($groups);

        expect(array_column($groups, 'title'))
            ->toBe(['[GridInfoService]', '[LoginService]', '[Search]', '[DataSnapshot]', '[Messaging]', 'opensim-helpers', '[Helpers]'])
            // Every address the section has, and the ones the helpers expect when it has not, in the same list
            ->and(array_keys($rows['[GridInfoService]']))
            ->toBe(['login', 'gatekeeper', 'uas', 'welcome', 'economy', 'about', 'register', 'help', 'password', 'search', 'web_profile_url', 'partner'])
            ->and($rows['[GridInfoService]']['partner']['url'])
            ->toBe('https://partner.example.org/')
            ->and($rows['[GridInfoService]']['about']['url'])
            ->toBe('')
            // Not a name of a setting nor a text of the helpers: what the ini files say
            ->and(array_keys($rows['[LoginService]']))
            ->toBe(['DestinationGuide', 'MessageUrl', 'SearchURL'])
            ->and(array_keys($rows['[DataSnapshot]']))
            ->toBe(['DATA_SRV_Alpha'])
            ->and(array_keys($rows['[Helpers]']))
            ->toBe(['GuideSource', 'HypEventsURL']);
    });

    test('asks the grid info for the addresses of the grid, and asks no avatar for the address of a profile', function () use ($settings, $routes, $by) {
        $rows = $by(
            OpenSim_Helpers_Block_Health::plan(
                $settings(['GridInfoService' => ['login' => 'http://play.example.org:8002/', 'web_profile_url' => 'https://play.example.org/profile/[AGENT_NAME]']]),
                'http://play.example.org:8002/',
                [],
                $routes,
                '',
            ),
        )['[GridInfoService]'];

        expect($rows['login'])
            ->toMatchArray(['url' => 'http://play.example.org:8002/', 'probe' => 'http://play.example.org:8002/get_grid_info'])
            ->and($rows['web_profile_url'])
            ->toHaveKey('skip');
    });

    test('shows where the helpers expect a service the grid does not give, saying it is a default', function () use ($settings, $routes, $by) {
        $rows = $by(OpenSim_Helpers_Block_Health::plan($settings(['GridInfoService' => []]), null, [], $routes, ''));

        expect($rows['[Search]']['SearchURL'])
            ->toMatchArray(['url' => 'https://play.example.org/helpers/query.php', 'method' => 'xmlrpc', 'note' => 'default address of the helpers'])
            ->and($rows['[Messaging]']['OfflineMessageURL']['url'])
            ->toBe('https://play.example.org/helpers/offline.php')
            ->and($rows['[DataSnapshot]']['DATA_SRV_*']['url'])
            ->toBe('https://play.example.org/helpers/register.php')
            ->and($rows['opensim-helpers']['currency.php']['url'])
            ->toBe('https://play.example.org/helpers/currency.php');
    });

    test('says what the install needs, and what is not defined', function () {
        $rows = array_column(OpenSim_Helpers_Block_Health::extensions(), null, 'label');

        expect($rows['xmlrpc']['level'])->toBe('ok');
        expect(OpenSim_Helpers_Block_Health::plan(null, null, [], [], '')[6]['rows'][0])
            ->toMatchArray(['label' => 'GuideSource', 'status' => 'not defined', 'level' => 'none']);
    });
});
