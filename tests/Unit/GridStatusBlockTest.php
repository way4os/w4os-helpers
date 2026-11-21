<?php

use W4OS\Helpers\Block\GridStatusBlock;
use W4OS\Engine\Runtime\GridMonitor;

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

    test('its rows are the ones of w4os-wp, in its words and its order: the regions online only', function () {
        $monitor = ['level' => GridMonitor::OK, 'regions' => ['online' => 5, 'total' => 7], 'users' => [
            'members' => 1234, 'active_members' => 12, 'members_in_world' => 3, 'active_users' => 40, 'total_users' => 6, 'regions' => 7, 'area' => 0.327680,
        ]];

        $rows = GridStatusBlock::rows($monitor);

        expect(array_column($rows, 'value', 'label'))->toBe([
            'Status' => 'Online',
            'Members' => '1,234',
            'Active members (30 days)' => '12',
            'Members in world' => '3',
            'Active users (30 days)' => '40',
            'Total users in world' => '6',
            'Regions' => '5',
            'Total area' => '0.33 km²',
        ])
            // Without the numbers of the users, the regions still
            ->and(GridStatusBlock::rows(['level' => GridMonitor::DOWN, 'regions' => ['online' => 0, 'total' => 7]]))
            ->toBe([['label' => 'Status', 'value' => 'Offline'], ['label' => 'Regions', 'value' => '0']]);
    });
});
