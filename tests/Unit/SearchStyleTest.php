<?php

declare(strict_types=1);

use W4OS\Helpers\View\View;

describe('The style of the search', function () {
    test('comes with the block, wherever it is shown: its topics spaced, its label hidden', function () {
        $files = View::blockFiles('search');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/search.css');

        // The style of its pictures too (blocks.css), the ones of the places and of the classifieds
        expect($files['css'])->toBe(['css/blocks.css', 'css/search.css', 'css/places.css', 'css/search.web.css'])
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

describe('The box of the search', function () {
    test('holds its tabs, its form, its results and the detail, its title above it; on the web only, as the boxes of the grid info', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/search.css');
        $twig = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/components/search-block.twig');

        expect($twig)->toMatch('#class="block block-search search-\{\{ topic \}\} client-\{\{ client[^"]*"#')
            ->and(strpos($twig, 'class="block-title"'))->toBeLessThan((int) strpos($twig, '<div class="search-box">'))
            ->and(strpos($twig, '<div class="search-box">'))->toBeLessThan((int) strpos($twig, 'class="search-topics"'))
            ->and($css)->not->toContain('.search-box {')
            ->and((string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/search.web.css'))->toContain('.block-search .search-box { padding: .75rem 1rem; border: 1px solid color-mix(in srgb, currentColor 15%, transparent); border-radius: .5rem; }');
    });

    test('shows the popular places under the form of places before a search, their own block, gone with the first search', function () {
        $code = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Block/SearchBlock.php');
        $twig = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/components/search-block.twig');

        expect($code)->toContain('\'popular\' => $topic === \'places\' && !$asked[\'sent\'] && !$embedded', 'Blocks::data(\'popular_places\', $grid,')
            ->and($twig)->toContain("{% include 'components/popular_places-block.twig' with popular|merge({helpers_block: 'popular_places'}) %}");
    });
});
