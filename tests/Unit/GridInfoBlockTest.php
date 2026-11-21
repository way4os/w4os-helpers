<?php

use W4OS\Engine\Hypergrid\Destination;
use W4OS\Helpers\Block\Blocks;

describe('grid info', function () {
    test('the login URI is shown as the grid gives it, linked to the grid as its tpLink() gives it', function () {
        $grid = W4OS\Engine\Models\Grid::find((new W4OS\Engine\Testing\Machine())->environment, 'demo');
        $rows = array_column(Blocks::data('grid-info', $grid, ['client' => 'browser'])['rows'], null, 'label');
        $login = $rows['Login URI'];

        expect($login['value'])->toBe($grid->tpLink(Destination::TXT))
            ->and($login['url'])->toBe($grid->tpLink(Destination::AUTO, ['client' => 'browser']));
    });
});
