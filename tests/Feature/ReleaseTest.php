<?php

/**
 * A request of the helpers answers first, then does what waits (a simulator that registered is read, the tasks): the simulator, the
 * viewer never wait for it (the engine's Server::hold() and Server::release(), as dontWait() of the helpers of w4os).
 */

describe('The answer of the helpers', function () {
    test('frees its client before what waits for it ends, on a server without fastcgi_finish_request', function () {
        $dir = sys_get_temp_dir() . '/helpers-release-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/router.php", '<?php
            require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';
            W4OS\Helpers\App::create(static fn () => null);
            echo "answered";
            W4OS\Helpers\Http\Action\Engine::finish();
            // What waits for the answer: the tasks
            sleep(2);
            file_put_contents(__DIR__ . "/done", "1");');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", "$dir/router.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        set_error_handler(static fn () => true);
        for ($try = 0; $try < 50 && ! ($connection = fsockopen('127.0.0.1', $port)); ++$try) {
            usleep(100_000);
        }
        restore_error_handler();
        empty($connection) || fclose($connection);
        $curl = curl_init("http://127.0.0.1:$port/");
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $start = microtime(true);
        $body = curl_exec($curl);
        $took = microtime(true) - $start;
        $before = is_file("$dir/done");
        for ($try = 0; $try < 50 && ! is_file("$dir/done"); ++$try) {
            usleep(100_000);
        }
        proc_terminate($server);
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);

        expect($body)->toBe('answered')
            ->and($took)->toBeLessThan(1.5)
            ->and($before)->toBeFalse();
    });
});
