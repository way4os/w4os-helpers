<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Grid;

/** The message of the day; a page does not know the name of the avatar. */
final class MotdBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        return ['title' => _('Message of the day'), 'text' => $grid->motd(_('traveler'))];
    }
}
