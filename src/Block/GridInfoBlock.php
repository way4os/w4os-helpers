<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Hypergrid\Destination;
use W4OS\Engine\Models\Grid;
use W4OS\Helpers\I18n;

/** The name of the grid and the address to log in, linked to the grid where it is shown (Links::to()): hop:// out of a viewer. */
final class GridInfoBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $rows = [['label' => I18n::text('Grid Name'), 'value' => $grid->name()]];
        $login = $grid->loginUrl();
        if ($login !== null && $login !== '') {
            $rows[] = [
                'label' => I18n::text('Login URI'),
                'value' => (string) Destination::format($login, Destination::TXT),
                'url' => Links::to($grid, (string) $login, $args),
            ];
        }

        return ['title' => I18n::text('Grid Information'), 'rows' => $rows];
    }
}
