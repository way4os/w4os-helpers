<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Assets\WebAssets;
use W4OS\Engine\Models\Grid;
use W4OS\Helpers\I18n;

/**
 * The parcels with the most visitors, as a grid of cards: the picture of the parcel (its snapshot), what you go to (its name here,
 * its region on another grid) and where under it (its region and point here, its grid elsewhere), as the engine gives them
 * (Grid::popularPlaces()). The options of w4os-wp 2.x and its defaults: `include_hypergrid` and `include_landsales`, true to show the
 * places of the other grids and the land for sale (both left out by default).
 */
final class PopularPlacesBlock implements Block
{
    public const COUNT = 10;

    public function data(Grid $grid, array $args = []): array
    {
        $count = max(1, min(50, (int) ($args['count'] ?? self::COUNT)));
        try {
            $places = $grid->popularPlaces($count, hypergrid: self::option($args, 'include_hypergrid'), landSales: self::option($args, 'include_landsales'));
        } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
            Blocks::unreachable($e);
            $places = [];
            $unavailable = true;
        }
        $rows = array_map(
            static fn(array $place): array => [
                'picture' => $place['picture'],
                // What you go to, then where, as the engine says it (Grid::card())
                'label' => (string) ($place['title'] ?? $place['name']),
                'value' => (string) $place['label'],
                'url' => Links::to($grid, (string) $place['url'], $args),
            ],
            $places,
        );

        // No place: nothing to show, the title neither (a database that cannot be reached is said, it is not empty)
        if ($rows === [] && !isset($unavailable)) {
            return [];
        }

        return ['title' => I18n::text('Popular places'), 'rows' => $rows, 'placeholder' => WebAssets::PLACEHOLDER]
            + (isset($unavailable) ? ['unavailable' => true] : []);
    }

    /** An option of the block that is false unless it is told so (true, 1, yes, on). */
    private static function option(array $args, string $name): bool
    {
        $value = $args[$name] ?? false;

        return is_bool($value) ? $value : in_array(strtolower(trim((string) $value)), ['true', '1', 'yes', 'on'], true);
    }
}
