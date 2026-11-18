<?php

use OpenSim\Helpers\Block\GridStatusBlock;
use OpenSim\Runtime\GridMonitor;

describe('grid status', function () {
    test('the area has its unit, and two decimals under 100 km²', function () {
        expect(GridStatusBlock::area(0.065536))->toBe('0.07 km²')
            ->and(GridStatusBlock::area(1.638))->toBe('1.64 km²')
            ->and(GridStatusBlock::area(0))->toBe('0.00 km²')
            ->and(GridStatusBlock::area(1234.5))->toBe('1,235 km²');
    });

    test('the status is the text of its level in the language of the page, the keyword left to the viewers', function () {
        $level = static fn(string $level): array => ['level' => $level];

        expect(array_map(static fn(string $l): ?string => GridStatusBlock::label($level($l)), [GridMonitor::OK, GridMonitor::DEGRADED, GridMonitor::DOWN, GridMonitor::UNKNOWN]))
            ->toBe(['Online', 'Degraded', 'Offline', null])
            ->and(GridStatusBlock::label($level(GridMonitor::OK), 'fr_FR'))->toBe('En ligne')
            ->and(GridStatusBlock::label($level(GridMonitor::DOWN), 'fr_FR'))->toBe('Hors ligne')
            ->and(GridStatusBlock::keyword($level(GridMonitor::OK)))->toBe('ONLINE');
    });
});
