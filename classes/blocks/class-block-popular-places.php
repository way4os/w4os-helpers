<?php
/**
 * Block popular_places: the places of the grid that are visited the most, from the parcels the simulators tell in the search
 * (their traffic), as links a viewer follows.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Block_PopularPlaces
{
    /** How many places. */
    const COUNT = 10;

    /**
     * @param  array<string,mixed> $args `count`: how many places
     * @return array<string,mixed> nothing to show (no row) when no place has any traffic
     */
    public static function data($args = [])
    {
        global $SearchDB;
        if (empty($SearchDB) && class_exists('OSPDO') && is_file(dirname(__DIR__, 2) . '/includes/search.php')) {
            require_once dirname(__DIR__, 2) . '/includes/search.php';
        }
        $count = max(1, min(50, (int) ($args['count'] ?? self::COUNT)));
        $regions = defined('SEARCH_REGION_TABLE') ? SEARCH_REGION_TABLE : 'regionsregister';
        $statement = empty($SearchDB)
            ? false
            : $SearchDB->prepareAndExecute(
                "SELECT p.parcelname AS name, p.landingpoint, p.gatekeeperURL AS gatekeeper, p.dwell, r.regionname AS region FROM popularplaces AS pop INNER JOIN parcels AS p ON p.parcelUUID = pop.parcelUUID LEFT JOIN $regions AS r ON r.regionUUID = p.regionUUID WHERE pop.dwell > 0 ORDER BY pop.dwell DESC, p.parcelname LIMIT $count",
            );
        $rows = [];
        foreach ($statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
            $region = (string) ($row['region'] ?? '');
            $rows[] = [
                'label' => (string) $row['name'],
                'value' => $region,
                'url' => OpenSim_Helpers_Block_Search::hop((string) $row['gatekeeper'], $region, (string) $row['landingpoint']),
            ];
        }

        return ['title' => _('Popular places'), 'rows' => $rows];
    }
}
