<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Hypergrid\Destination;
use OpenSim\Models\Grid;

/** The name of the grid and the address to log in: as text to copy in a viewer, and its teleport link (hop://) for a viewer. */
final class GridInfoBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $rows = [['label' => _('Grid Name'), 'value' => $grid->name()]];
        $login = $grid->loginUrl();
        if ($login !== null && $login !== '') {
            $rows[] = [
                'label' => _('Login URI'),
                'value' => preg_replace('#^https?://#', '', rtrim($login, '/')),
                'copy' => true,
                'hop' => (string) Destination::format($login, Destination::HOP),
            ];
        }

        return ['title' => _('Grid Information'), 'rows' => $rows];
    }
}
