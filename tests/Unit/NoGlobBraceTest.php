<?php

declare(strict_types=1);

test('GLOB_BRACE is not used (musl has none)', function () {
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $file->getExtension() === 'php' && str_contains((string) file_get_contents($file->getPathname()), 'GLOB_BRACE') && ($found[] = $file->getPathname());
    }

    expect($found)->toBe([]);
});
