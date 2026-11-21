<?php

declare(strict_types=1);

use W4OS\Helpers\View\View;

describe('The style of the search', function () {
    test('comes with the block, wherever it is shown: its topics spaced, its label hidden', function () {
        $files = View::blockFiles('search');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/' . ($files['css'][0] ?? 'none'));

        expect($files['css'])->toBe(['css/search.css'])
            ->and($css)->toContain('.search-topics')
            ->and($css)->toContain('.visually-hidden');
    });

    test('takes the colors of the page that shows it, not the ones of the pages of the helpers', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/search.css');

        expect($css)->not->toContain('var(--');
    });
});
