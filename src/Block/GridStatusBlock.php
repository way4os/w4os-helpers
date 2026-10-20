<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Grid;
use OpenSim\Runtime\GridMonitor;

/** The state of the grid at a glance, from the monitor of the engine. */
final class GridStatusBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $monitor = GridMonitor::of($grid);
        $rows = [];
        if ($monitor['level'] !== GridMonitor::UNKNOWN) {
            // A keyword the viewers read (Firestorm takes the state of the grid from it): not translated
            $rows[] = ['label' => _('Status'), 'value' => $monitor['level'] === GridMonitor::DOWN ? 'OFFLINE' : 'ONLINE'];
        }
        $rows[] = ['label' => _('Regions'), 'value' => number_format($monitor['regions']['online']) . ' / ' . number_format($monitor['regions']['total'])];
        foreach ((array) ($monitor['users'] ?? []) as $label => $count) {
            $rows[] = ['label' => ucfirst(str_replace('_', ' ', (string) $label)), 'value' => number_format((float) $count)];
        }

        return ['title' => _('Grid Status'), 'rows' => $rows];
    }
}
