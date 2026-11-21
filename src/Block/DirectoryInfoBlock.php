<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Models\Grid;
use W4OS\Helpers\I18n;

/** The grids whose simulators registered in the search of this one, with their regions and parcels. */
final class DirectoryInfoBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $search = new SearchData($grid);
        try {
            $regions = $search->regions();
            $counts = $search->rows(
                "SELECT r.gatekeeperURL AS gatekeeper, COUNT(DISTINCT p.regionUUID) AS regions, COUNT(*) AS parcels FROM parcels AS p INNER JOIN $regions AS r ON p.regionUUID = r.regionUUID GROUP BY r.gatekeeperURL",
            );
        } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
            Blocks::unreachable($e);

            return ['title' => I18n::text('Directory Info'), 'grids' => [], 'rows' => [], 'unavailable' => true];
        }
        $grids = [];
        $total = ['grids' => 0, 'regions' => 0, 'parcels' => 0];
        foreach ($counts as $row) {
            $gatekeeper = (string) $row['gatekeeper'];
            $r = (int) $row['regions'];
            $p = (int) $row['parcels'];
            $grids[] = [
                'url' => $gatekeeper,
                'name' => preg_replace('#^https?://#', '', $gatekeeper),
                'detail' => sprintf(
                    '(%s, %s)',
                    sprintf(I18n::plural('%d region', '%d regions', $r), $r),
                    sprintf(I18n::plural('%d parcel', '%d parcels', $p), $p),
                ),
            ];
            $total['grids']++;
            $total['regions'] += $r;
            $total['parcels'] += $p;
        }

        return [
            'title' => I18n::text('Directory Info'),
            'grids' => $grids,
            'rows' => $grids,
            'summary' => [
                sprintf(I18n::plural('%d grid', '%d grids', $total['grids']), $total['grids']),
                sprintf(I18n::plural('%d region', '%d regions', $total['regions']), $total['regions']),
                sprintf(I18n::plural('%d parcel', '%d parcels', $total['parcels']), $total['parcels']),
            ],
        ];
    }
}
