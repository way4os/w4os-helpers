<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Grid;

/** The name of the grid and the address to log in. */
final class GridInfoBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $rows = [['label' => _('Grid Name'), 'value' => $grid->name()]];
        $login = $grid->loginUrl();
        if ($login !== null && $login !== '') {
            $rows[] = ['label' => _('Login URI'), 'value' => preg_replace('#^https?://#', '', $login), 'url' => $login];
        }

        return ['title' => _('Grid Information'), 'rows' => $rows];
    }
}
