<?php

declare(strict_types=1);

namespace W4OS\Helpers\View;

use W4OS\Engine\Assets\WebAssets;

/**
 * A picture of the grid in a block, as every page shows one (W4OS\Views\Picture): the picture; for a person (`person`) without
 * one, the engine's silhouette at half its opacity; for a place, a group (the image of its title when it has none of its own, the
 * engine's Pictured), its picture, gone when it cannot be loaded. Never a name or its initials. Its class gives its size and its
 * shape (assets/css/blocks.css); the templates call it as `picture(src, 'person', class)`, `picture(src, null, class)`.
 */
final class Picture
{
    /** The picture of nothing, as the grid names it. */
    private const ZERO = '00000000-0000-0000-0000-000000000000';

    public static function tag(string $src, ?string $of = null, string $class = ''): string
    {
        $class = trim("picture $class");
        if ($of !== 'person') {
            return \W4OS\Views\Picture::tag($src, null, $class);
        }
        // The silhouette at the address of the pictures, under its own id
        $id = '#[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?=\.\w+(?:\?.*)?$)#i';
        $silhouette = (string) preg_replace($id, WebAssets::PERSON, $src);
        $none = preg_match($id, $src, $found) === 1 && in_array(strtolower($found[0]), [WebAssets::PERSON, self::ZERO], true);

        return \W4OS\Views\Picture::tag($none ? null : $src, $silhouette, $class);
    }
}
