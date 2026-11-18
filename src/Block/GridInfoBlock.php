<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Hypergrid\Destination;
use OpenSim\Models\Grid;

/** The name of the grid and the address to log in, linked to the grid where it is shown (Links::to()): hop:// out of a viewer. */
final class GridInfoBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $rows = [['label' => _('Grid Name'), 'value' => $grid->name()]];
        $login = $grid->loginUrl();
        if ($login !== null && $login !== '') {
            $rows[] = [
                'label' => _('Login URI'),
                'value' => (string) Destination::format($login, Destination::TXT),
                'url' => Links::to((string) $login, (string) $login, $args),
            ];
        }

        return ['title' => _('Grid Information'), 'rows' => $rows];
    }
}
