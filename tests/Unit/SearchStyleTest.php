<?php

declare(strict_types=1);

use W4OS\Helpers\View\View;

describe('The style of the search', function () {
    test('comes with the block, wherever it is shown: its topics spaced, its label hidden', function () {
        $files = View::blockFiles('search');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/search.css');

        // The style of its pictures too (blocks.css), the ones of the places and of the classifieds
        expect($files['css'])->toBe(['css/blocks.css', 'css/search.css'])
            ->and($css)->toContain('.search-topics')
            ->and($css)->toContain('.visually-hidden');
    });

    test('takes the colors of the page that shows it, not the ones of the pages of the helpers', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/search.css');

        expect($css)->not->toContain('var(--');
    });
});

describe('The style of the pages of the helpers', function () {
    test('gives the font of the page to its forms too: the brand font, never the small one of the browser', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/page.css');

        expect($css)->toContain('button, input, select, textarea { font: inherit; }');
    });
});

describe('The date of the events', function () {
    test('chosen in the picker of the browser selects the search of a day', function () {
        $files = View::blockFiles('search');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/' . ($files['js'][0] ?? 'none'));

        expect($files['js'])->toBe(['js/search.js'])
            ->and($js)->toContain('[data-search-day]', 'input[name="when"][value="day"]', '.checked = true');
    });
});

