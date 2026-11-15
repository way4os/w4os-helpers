<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Grid;
use OpenSim\Runtime\GridMonitor;

/** The state of the grid at a glance, from the monitor of the engine. */
final class GridStatusBlock implements Block
{
    /** The keyword the viewers read (Firestorm takes the state of the grid from it): not translated. Null when the machine cannot tell. */
    public static function keyword(array $monitor): ?string
    {
        return [
            GridMonitor::OK => 'ONLINE',
            GridMonitor::DEGRADED => 'DEGRADED',
            GridMonitor::DOWN => 'OFFLINE',
        ][$monitor['level']] ?? null;
    }

    /** An area in square kilometers, with its unit: two decimals under 100 km² (a region of 256 m is 0.07 km²). */
    public static function area(float $squareKilometers): string
    {
        return number_format($squareKilometers, $squareKilometers < 100 ? 2 : 0) . ' km²';
    }

    public function data(Grid $grid, array $args = []): array
    {
        $monitor = GridMonitor::of($grid);
        $rows = [];
        $keyword = self::keyword($monitor);
        $keyword === null || ($rows[] = ['label' => _('Status'), 'value' => $keyword]);
        $rows[] = [
            'label' => _('Regions'),
            'value' => number_format($monitor['regions']['online']) . ' / ' . number_format($monitor['regions']['total']),
        ];
        foreach ((array) ($monitor['users'] ?? []) as $label => $count) {
            $rows[] = $label === 'area'
                ? ['label' => _('Area'), 'value' => self::area((float) $count)]
                : ['label' => ucfirst(str_replace('_', ' ', (string) $label)), 'value' => number_format((float) $count)];
        }

        return ['title' => _('Grid Status'), 'rows' => $rows, 'monitor' => $monitor];
    }
}
