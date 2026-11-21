<?php

declare(strict_types=1);

/**
 * The rules of the code of the helpers that no other test sees.
 */

/** The PHP files of a folder, by their path under it, with their code. */
function arch_sources(string $root): array
{
    $sources = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        str_ends_with($file->getPathname(), '.php') && ($sources[substr($file->getPathname(), strlen($root) + 1)] = (string) file_get_contents($file->getPathname()));
    }
    ksort($sources);

    return $sources;
}

/** Whether PHP code calls a static method of a class (its comments and strings aside), by the last part of the class name. */
function arch_calls(string $code, string $class, string $method): bool
{
    $tokens = array_values(array_filter(token_get_all($code), static fn($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    foreach ($tokens as $i => $token) {
        $name = is_array($token) ? $token[1] : $token;
        if (($name === $class || str_ends_with($name, "\\$class")) && ($tokens[$i + 1][0] ?? null) === T_DOUBLE_COLON
            && strcasecmp((string) ($tokens[$i + 2][1] ?? ''), $method) === 0 && ($tokens[$i + 3] ?? null) === '(') {
            return true;
        }
    }

    return false;
}

describe('A teleport link', function () {
    test('is asked of the grid, its region, its parcel or its place (tpLink()), never made with Destination::format()', function () {
        $found = array_keys(array_filter(arch_sources(dirname(__DIR__, 2) . '/src'), static fn(string $code): bool => arch_calls($code, 'Destination', 'format')));

        expect($found)->toBe([])
            ->and(arch_calls('<?php \W4OS\Engine\Hypergrid\Destination::format($a, 8);', 'Destination', 'format'))->toBeTrue()
            ->and(arch_calls('<?php /* Destination::format() */ $grid->tpLink();', 'Destination', 'format'))->toBeFalse();
    });
});
