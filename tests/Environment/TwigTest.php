<?php
/**
 * The templates work on Twig 1.44 as on Twig 3: a site of Grav 1.7 renders the blocks with its own Twig (1.44), Grav 2 and the
 * helpers alone with Twig 3.
 */

$root = dirname(__DIR__, 2);

// Twig 1.44 in a folder of its own (tests/twig1), installed when it is not yet, once per run: the compile test is skipped only when
// composer cannot install it (no composer, offline), and says why
$twig1 = "$root/tests/twig1";
$twig1Missing = null;
if (! is_file("$twig1/vendor/autoload.php")) {
    exec('composer install -q --no-interaction -d ' . escapeshellarg($twig1) . ' 2>&1', $installed, $status);
    $twig1Missing = is_file("$twig1/vendor/autoload.php") ? null : 'Twig 1.44 could not be installed (composer install -d tests/twig1): '
        . (trim(implode(' ', $installed)) ?: "exit $status");
}

/** @return array<string,string> the templates of the helpers, by their name, their comments left out */
function twig_templates(string $root): array
{
    $templates = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/templates", FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_ends_with((string) $file, '.twig')) {
            $templates[substr((string) $file, strlen("$root/templates/"))] = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents((string) $file));
        }
    }

    return $templates;
}

describe('Twig', function () use ($root, $twig1Missing) {
    test('the templates use nothing of Twig 3 alone, which Twig 1.44 refuses or reads otherwise', function () use ($root) {
        // An arrow in sort or column, ?., the spread, the types and guard tags, the sequence and mapping tests, has some or every,
        // ?? after a call (Twig 1.44 tests only variables there)
        $twig3 = '/\|\s*(sort|column)\(\s*\(?\s*\w+\s*(,\s*\w+\s*)?\)?\s*=>|\?\.|\.\.\.\s*[\w\[{(]|\{%-?\s*(types|guard)\b|\bis\s+(not\s+)?(sequence|mapping)\b|\bhas\s+(some|every)\b|\b(?!attribute\b)\w+\([^()]*\)\s*\?\?/';
        $found = [];
        foreach (twig_templates($root) as $name => $source) {
            if (preg_match_all($twig3, $source, $matches)) {
                $found[$name] = $matches[0];
            }
        }

        expect($found)->toBe([]);
    });

    test('every template compiles with Twig 1.44', function () use ($root) {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/tests/twig1/compile.php") . ' 2>&1', $output, $status);

        expect(implode("\n", $output))->toBe('')
            ->and($status)->toBe(0);
    })->skip($twig1Missing !== null, (string) $twig1Missing);
});
