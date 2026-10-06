<?php
/**
 * includes/economy.php: what it computes without a database. It declares constants, so each case runs in its own process.
 */

/**
 * Run PHP with economy.php loaded, the rate and the key given, and give what the code echoes as JSON.
 *
 * @param array<string,mixed> $constants
 * @return mixed
 */
function economy_run(string $code, array $constants = [])
{
    $define = '';
    foreach ($constants as $name => $value) {
        $define .= 'define(' . var_export($name, true) . ', ' . var_export($value, true) . ');';
    }
    $root = dirname(__DIR__, 2);
    $script =
        "require '$root/vendor/autoload.php'; define('NULL_KEY', '00000000-0000-0000-0000-000000000000');" .
        "$define require '$root/includes/economy.php'; echo json_encode($code);";
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = (string) stream_get_contents($pipes[1]);
    proc_close($process);

    return json_decode($out, true);
}

describe('The price of the currency', function () {
    test('follows the rate of the grid (real currency for an amount of virtual currency)', function () {
        $cost = fn($amount, $rate, $per) => economy_run("currency_virtual_to_real($amount)", [
            'CURRENCY_RATE' => $rate,
            'CURRENCY_RATE_PER' => $per,
        ]);

        // In cents: 10 for 1000 is a cent per unit
        expect($cost(500, 10, 1000))
            ->toBe(500)
            ->and($cost(1000, 4, 1000))
            ->toBe(400)
            ->and($cost(250, 1, 100))
            ->toBe(250)
            ->and($cost(0, 10, 1000))
            ->toBe(0);
    });

    test('is nothing, and no crash, when the grid says an amount of nothing', function () {
        expect(economy_run('currency_virtual_to_real(500)', ['CURRENCY_RATE' => 10, 'CURRENCY_RATE_PER' => 0]))->toBe(0);
    });
});

describe('The confirm value', function () {
    test('is made with the key of the grid and the address of the caller', function () {
        $confirm = fn($key, $ip) => economy_run("currency_get_confirm_value('$ip')", ['CURRENCY_SCRIPT_KEY' => $key]);

        expect($confirm('secret', '203.0.113.7'))
            ->toBe(md5('secret_203.0.113.7'))
            ->and($confirm(null, '203.0.113.7'))
            ->toBe(md5('1234567883789_203.0.113.7'))
            ->and($confirm('secret', '203.0.113.8'))
            ->not->toBe($confirm('secret', '203.0.113.7'));
    });
});

test('a transaction has a random UUID of version 4', function () {
    [$one, $two] = economy_run('[make_random_guid(), make_random_guid()]');

    expect($one)
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($two)
        ->not->toBe($one);
});
