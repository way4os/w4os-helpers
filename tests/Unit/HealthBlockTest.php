<?php

use OpenSim\Helpers\Block\Blocks;
use OpenSim\Models\Grid;
use OpenSim\Testing\Machine;

describe('The health page of the helpers', function () {
    test('tells whether the folders they write as the web server can be: the pictures and the state of the tasks', function () {
        $machine = new Machine();
        $grid = Grid::find($machine->environment, 'demo');

        $groups = array_column(Blocks::data('health', $grid)['groups'], null, 'file');

        expect(array_column($groups['cache']['rows'] ?? [], 'label'))->toBe(['assets', 'tasks'])
            ->and(array_column($groups['cache']['rows'], 'path'))->toBe([
                OpenSim\Assets\WebAssets::folder($grid),
                $machine->environment->layout->tasksRoot(),
            ]);
    });
});
