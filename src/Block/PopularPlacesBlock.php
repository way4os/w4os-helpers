<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
use OpenSim\Models\Grid;

/**
 * The parcels with the most visitors, as a grid of cards: the picture of the parcel (its snapshot), its name, the label of its
 * destination under it (its region and point for this grid, its grid for another), as the engine gives them (Grid::popularPlaces()).
 */
final class PopularPlacesBlock implements Block
{
    public const COUNT = 10;

    public function data(Grid $grid, array $args = []): array
    {
        $count = max(1, min(50, (int) ($args['count'] ?? self::COUNT)));
        $rows = array_map(
            static fn(array $place): array => [
                'picture' => $place['picture'],
                'label' => (string) $place['name'],
                'value' => (string) $place['label'],
                'url' => Links::to($grid, (string) $place['url'], $args),
            ],
            $grid->popularPlaces($count),
        );

        return ['title' => _('Popular places'), 'rows' => $rows, 'placeholder' => WebAssets::PLACEHOLDER];
    }
}
