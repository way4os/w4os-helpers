<?php
/**
 * Block directory_info: what the search directory holds, the grids that registered parcels with their numbers of regions and
 * parcels.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once dirname(__DIR__) . '/class-grid-info.php';

class OpenSim_Helpers_Block_DirectoryInfo
{
    /**
     * @param  array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        global $SearchDB;
        if (empty($SearchDB)) {
            require_once dirname(__DIR__, 2) . '/includes/search.php';
        }
        // One row per grid: how many regions and parcels it registered
        $query = $SearchDB->prepareAndExecute(
            'SELECT r.gatekeeperURL AS gatekeeper, COUNT(DISTINCT p.regionUUID) AS regions, COUNT(*) AS parcels
            FROM parcels AS p INNER JOIN ' .
                SEARCH_REGION_TABLE .
                ' AS r ON p.regionUUID = r.regionUUID
            GROUP BY r.gatekeeperURL',
        );
        $grids = [];
        $total = ['grids' => 0, 'regions' => 0, 'parcels' => 0];
        foreach ($query ? $query->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
            $gatekeeper = (string) $row['gatekeeper'];
            $info = OpenSim_Helpers_GridInfo::get($gatekeeper);
            $regions = (int) $row['regions'];
            $parcels = (int) $row['parcels'];
            $grids[] = [
                'url' => $gatekeeper,
                'name' => $info['gridname'] ?? $gatekeeper,
                'detail' => sprintf(
                    '(%s, %s)',
                    sprintf(ngettext('%d region', '%d regions', $regions), $regions),
                    sprintf(ngettext('%d parcel', '%d parcels', $parcels), $parcels),
                ),
            ];
            $total['grids']++;
            $total['regions'] += $regions;
            $total['parcels'] += $parcels;
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
