<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
use OpenSim\Models\Grid;
use OpenSim\Text\Uuid;

/** The parcels with the most visitors, as a grid of cards: the picture of the parcel (its snapshot), its name, its region. */
final class PopularPlacesBlock implements Block
{
    public const COUNT = 10;

    public function data(Grid $grid, array $args = []): array
    {
        $search = new SearchData($grid);
        $count = max(1, min(50, (int) ($args['count'] ?? self::COUNT)));
        $regions = $search->regions();
        $rows = [];
        foreach (
            $search->rows(
                "SELECT p.parcelname AS name, p.landingpoint, p.imageUUID AS picture, p.gatekeeperURL AS gatekeeper, p.dwell, r.regionname AS region FROM popularplaces AS pop INNER JOIN parcels AS p ON p.parcelUUID = pop.parcelUUID LEFT JOIN $regions AS r ON r.regionUUID = p.regionUUID WHERE pop.dwell > 0 ORDER BY pop.dwell DESC, p.parcelname LIMIT $count",
            )
            as $row
        ) {
            $region = (string) ($row['region'] ?? '');
            $picture = (string) ($row['picture'] ?? '');
            $rows[] = [
                'picture' => $picture === '' || in_array($picture, Uuid::NULL_KEYS, true) ? null : $picture,
                'label' => (string) $row['name'],
                'value' => $region,
                'url' => $search->teleport((string) $row['gatekeeper'], $region, (string) $row['landingpoint'], $args),
            ];
        }

        return ['title' => _('Popular places'), 'rows' => $rows, 'placeholder' => WebAssets::PLACEHOLDER];
    }
}
