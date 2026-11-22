<?php

use W4OS\Engine\Hypergrid\Destination;
use W4OS\Helpers\Block\Blocks;

describe('grid info', function () {
    test('the login URI is shown as the grid gives it, linked to the grid as its tpLink() gives it', function () {
        $grid = W4OS\Engine\Models\Grid::find((new W4OS\Engine\Testing\Machine())->environment, 'demo');
        $rows = array_column(Blocks::data('grid-info', $grid, ['client' => 'browser'])['rows'], null, 'label');
        $login = $rows['Login URI'];

        expect($login['value'])->toBe($grid->tpUri())
            ->and($login['url'] ?? null)->toBe($grid->tpLink(Destination::AUTO, ['client' => 'browser']) ?: null);
    });

    test('the login URI adds the grid to the viewer and copies its exact address, as the grid gives its links', function () {
        $grid = W4OS\Engine\Models\Grid::find((new W4OS\Engine\Testing\Machine())->environment, 'demo');
        $login = array_column(Blocks::data('grid-info', $grid)['rows'], null, 'label')['Login URI'];
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('grid-info', $grid, ['client' => 'browser', 'lang' => 'en']);

        expect(array_column($login['links']['browser'], 'action'))->toBe(array_column($grid->tpLinks('browser'), 'action'))
            ->and($login['links']['browser'][0]['action'])->toBe('addgrid')
            ->and($html)->toContain('data-w4os-tp', htmlspecialchars($grid->addGrid()['hop']), 'data-w4os-tp-copy="' . $grid->tpUri() . '"');
    });
});
