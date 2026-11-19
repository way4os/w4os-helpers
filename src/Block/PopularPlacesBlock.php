<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
use OpenSim\Models\Grid;

/**
 * The parcels with the most visitors, as a grid of cards: the picture of the parcel (its snapshot), what you go to (its name here,
 * its region on another grid) and where under it (its region and point here, its grid elsewhere), as the engine gives them
 * (Grid::popularPlaces()).
 */
final class PopularPlacesBlock implements Block
{
    public const COUNT = 10;

    public function data(Grid $grid, array $args = []): array
    {
        $count = max(1, min(50, (int) ($args['count'] ?? self::COUNT)));
        try {
            $places = $grid->popularPlaces($count);
        } catch (\OpenSim\Exceptions\ChannelUnavailable $e) {
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

        return ['title' => _('Popular places'), 'rows' => $rows, 'placeholder' => WebAssets::PLACEHOLDER]
            + (isset($unavailable) ? ['unavailable' => true] : []);
    }
}
