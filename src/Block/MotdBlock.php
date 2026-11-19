<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Helpers\I18n;
use OpenSim\Models\Grid;

/** The message of the day, in the language of the page; a page does not know the name of the avatar: the word for a visitor. */
final class MotdBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        return ['title' => _('Message of the day'), 'text' => $grid->motd('', I18n::current())];
    }
}
