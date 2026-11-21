<?php

declare(strict_types=1);

namespace W4OS\Helpers\View;

/**
 * A picture of the grid that never shows broken: the initials of its name on a tint of the text, the picture over them, hidden
 * when it cannot be loaded (a texture the grid has not: a 404 without a picture). Its class gives its size and its shape
 * (assets/css/blocks.css); the templates call it as `picture(src, name, class)`.
 */
final class Picture
{
    public static function tag(string $src, ?string $name = null, string $class = ''): string
    {
        $esc = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<span class="' . $esc(trim("picture $class")) . '" data-initials="' . $esc(self::initials($name)) . '">'
            . '<img src="' . $esc($src) . '" alt="" loading="lazy" onerror="this.hidden=true"></span>';
    }

    /** The initials of a name (Ada Love: AL; Plaza: P), '' for none. */
    public static function initials(?string $name): string
    {
        $words = preg_split('/[\s._-]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(static fn(string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }
}
