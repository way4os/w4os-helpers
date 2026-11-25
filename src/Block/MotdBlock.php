<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Helpers\I18n;
use W4OS\Engine\Models\Grid;

/**
 * The message of the day, a title alone, in the language of the page: a welcome to the avatar signed in on the site (`avatar`, its
 * name, as a site gives it), else the grid's own welcome (Grid::welcome(): WelcomeMessage of [LoginService], "Welcome, Avatar"
 * without one). A page of the helpers does not know who is signed in: the grid's own.
 */
final class MotdBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        return ['title' => $grid->welcome(trim((string) ($args['avatar'] ?? '')), I18n::current())];
    }
}
