<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\I18n\Catalog;
use W4OS\Engine\Models\Grid;
use W4OS\Helpers\I18n;

/**
 * The name of the grid and the address to log in, a teleport link that fits where it is seen (W4OS\Views\TeleportLink): adding the
 * grid to the viewer first, its login URI to copy exactly, teleporting to it when it has a landing region (the grid's own links).
 */
final class GridInfoBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        // The texts every site shares, from the engine's catalog by their key
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        $rows = [['label' => $texts['setting_grid_name'], 'value' => $grid->name()]];
        $login = $grid->loginUrl();
        if ($login !== null && $login !== '') {
            $rows[] = [
                'label' => $texts['setting_login'],
                'value' => (string) $grid->tpUri(),
                'url' => Links::to($grid, $args),
                'links' => Links::all($grid),
            ];
        }

        return ['title' => I18n::text('Grid Information'), 'rows' => $rows];
    }
}
