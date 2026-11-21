<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Models\Grid;

/** A piece of content a page is made of: it gives its data, the template components/<name>-block.twig shows it. */
interface Block
{
    /**
     * @param array<string,mixed> $args title, level and what the block takes
     * @return array<string,mixed> `title` and `rows`, `items`, `text`, `groups`... as the template of the block reads them
     */
    public function data(Grid $grid, array $args = []): array;
}
