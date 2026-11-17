<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

/** The blocks, by the name they have everywhere (a variable of a template has dashes as underscores). */
final class Blocks
{
    public const BLOCKS = [
        'grid-info' => GridInfoBlock::class,
        'grid-status' => GridStatusBlock::class,
        'motd' => MotdBlock::class,
        'health' => HealthBlock::class,
        'destination_guide' => DestinationGuideBlock::class,
        'popular_places' => PopularPlacesBlock::class,
        'directory_info' => DirectoryInfoBlock::class,
        'new_users' => NewUsersBlock::class,
        'search' => SearchBlock::class,
        'profile' => ProfileBlock::class,
    ];

    public const LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'];

    /** The name of a block from a slug or the name of a variable (`grid_info` is `grid-info`), null when none is called so. */
    public static function name(string $word): ?string
    {
        foreach (array_keys(self::BLOCKS) as $name) {
            if ($name === $word || str_replace('-', '_', $name) === $word) {
                return $name;
            }
        }

        return null;
    }

    /**
     * An address of a page of the site that shows the block (`search_url`, `web_profile_url` with `[AGENT_NAME]`), as the site
     * gives it, in its language: a path or an http(s) address; null without one, the block then links to the grid's.
     *
     * @param array<string,mixed> $args
     */
    public static function siteAddress(array $args, string $name): ?string
    {
        $address = trim((string) ($args[$name] ?? ''));

        return preg_match('#^(/(?!/)|https?://)#i', $address) === 1 ? $address : null;
    }

    /**
     * @param array<string,mixed> $args
     * @return ?array<string,mixed> null for a block that does not exist
     */
    public static function data(string $name, \OpenSim\Models\Grid $grid, array $args = []): ?array
    {
        $class = self::BLOCKS[$name] ?? null;
        if ($class === null) {
            return null;
        }
        $data = (new $class())->data($grid, $args);
        $level = $args['level'] ?? 'h3';
        $data['level'] = in_array($level, self::LEVELS, true) ? $level : 'h3';
        // A title of the call wins, an empty one leaves the title out
        $data['title'] = array_key_exists('title', $args) ? $args['title'] : $data['title'] ?? null;
        $data['name'] = $name;

        return $data;
    }
}
