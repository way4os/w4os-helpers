<?php

declare(strict_types=1);

/** The helpers speak to a visitor informally, as the engine does: tu in French, ti in Welsh. */
describe('The texts of the helpers', function () {
    test('speak to a visitor informally, a meeting place, not a bank: tu in French, ti in Welsh', function () {
        $formal = [
            'fr_FR' => '/\\b(vous|votre|vos)\\b/iu',
            'cy_GB' => "/\\b(chi|chwi|eich|gennych|ydych|atoch)\\b|['’]ch\\b/iu",
        ];
        foreach ($formal as $locale => $pattern) {
            preg_match_all('/^msgstr "(.*)"$/m', (string) file_get_contents(dirname(__DIR__, 2) . "/locales/$locale/LC_MESSAGES/messages.po"), $texts);
            expect(array_values(array_filter($texts[1], static fn(string $text): bool => preg_match($pattern, $text) === 1)))->toBe([], $locale);
        }
    });
});
