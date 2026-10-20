<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Grid;

/** The grids whose simulators registered in the search of this one, with their regions and parcels. */
final class DirectoryInfoBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $search = new SearchData($grid);
        $regions = $search->regions();
        $grids = [];
        $total = ['grids' => 0, 'regions' => 0, 'parcels' => 0];
        foreach (
            $search->rows(
                "SELECT r.gatekeeperURL AS gatekeeper, COUNT(DISTINCT p.regionUUID) AS regions, COUNT(*) AS parcels FROM parcels AS p INNER JOIN $regions AS r ON p.regionUUID = r.regionUUID GROUP BY r.gatekeeperURL",
            )
            as $row
        ) {
            $gatekeeper = (string) $row['gatekeeper'];
            $r = (int) $row['regions'];
            $p = (int) $row['parcels'];
            $grids[] = [
                'url' => $gatekeeper,
                'name' => preg_replace('#^https?://#', '', $gatekeeper),
                'detail' => sprintf(
                    '(%s, %s)',
                    sprintf(ngettext('%d region', '%d regions', $r), $r),
                    sprintf(ngettext('%d parcel', '%d parcels', $p), $p),
                ),
            ];
            $total['grids']++;
            $total['regions'] += $r;
            $total['parcels'] += $p;
        }

        return [
            'title' => _('Directory Info'),
            'grids' => $grids,
            'rows' => $grids,
            'summary' => [
                sprintf(ngettext('%d grid', '%d grids', $total['grids']), $total['grids']),
                sprintf(ngettext('%d region', '%d regions', $total['regions']), $total['regions']),
                sprintf(ngettext('%d parcel', '%d parcels', $total['parcels']), $total['parcels']),
            ],
        ];
    }
}
