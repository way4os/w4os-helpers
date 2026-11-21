<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Helpers\I18n;
use W4OS\Engine\Models\Grid;

/** The message of the day, in the language of the page; a page does not know the name of the avatar: the word for a visitor. */
final class MotdBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        return ['title' => I18n::text('Message of the day'), 'text' => $grid->motd('', I18n::current())];
    }
}
