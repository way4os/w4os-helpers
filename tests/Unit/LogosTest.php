<?php
/**
 * The logos of w4os and OpenSimulator: the engine ships them, composer copies them where the pages and a standalone install
 * expect them (assets/images/logos); git does not keep copies of its own.
 */

use OpenSim\Assets\Logos;

$root = dirname(__DIR__, 2);

describe('The logos of w4os and OpenSimulator', function () use ($root) {
    test('are the ones of the engine, in assets/images/logos', function () use ($root) {
        $logos = Logos::all();

        expect($logos)->not->toBeEmpty();
        foreach ($logos as $name => $file) {
            expect("$root/assets/images/logos/$name")->toBeFile()
                ->and(file_get_contents("$root/assets/images/logos/$name"))->toBe(file_get_contents($file), $name);
        }
    });

    test('are kept by git, with the note that says where they come from', function () use ($root) {
        $tracked = array_filter(explode("\n", (string) shell_exec('git -C ' . escapeshellarg($root) . ' ls-files assets/images/logos')));

        expect($tracked)->toEqualCanonicalizing([
            'assets/images/logos/README.md',
            ...array_map(static fn(string $name): string => "assets/images/logos/$name", array_keys(Logos::all())),
        ])
            ->and(file_get_contents("$root/assets/images/logos/README.md"))->toContain('opensim-engine');
    });

    test('are refreshed by composer with the development tools, never by an install without them (the packages)', function () use ($root) {
        $script = json_decode((string) file_get_contents("$root/composer.json"), true)['scripts']['logos'];
        $dir = sys_get_temp_dir() . '/helpers-logos-' . bin2hex(random_bytes(4));
        mkdir("$dir/vendor/magicoli/opensim-engine/share/images/logos", 0o755, true);
        mkdir("$dir/assets/images/logos", 0o755, true);
        file_put_contents("$dir/vendor/magicoli/opensim-engine/share/images/logos/new.png", 'new');
        $run = static fn(string $mode) => shell_exec('cd ' . escapeshellarg($dir) . " && COMPOSER_DEV_MODE=$mode $script");

        $run('0');
        $packaged = is_file("$dir/assets/images/logos/new.png");
        $run('1');

        expect($packaged)->toBeFalse()
            ->and(file_get_contents("$dir/assets/images/logos/new.png"))->toBe('new');
        shell_exec('rm -rf ' . escapeshellarg($dir));
    });
});
