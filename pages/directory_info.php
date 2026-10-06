<?php
/**
 * directory_info.php
 *
 * A page with the content of the search directory: the grids that registered parcels, with their numbers of regions and
 * parcels.
 *
 * @package		magicoli/opensim-helpers
 **/

if (__FILE__ !== $_SERVER['SCRIPT_FILENAME']) {
    // The file must only be called directly.
    http_response_code(403);
    exit("I'm not that kind of girl, I don't want to be included.");
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/search.php';
require_once dirname(__DIR__) . '/classes/class-router.php';
require_once dirname(__DIR__) . '/classes/class-grid-info.php';

set_helpers_locale();

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
    $grids[] = [
        'url' => $gatekeeper,
        'name' => $info['gridname'] ?? $gatekeeper,
        'regions' => (int) $row['regions'],
        'parcels' => (int) $row['parcels'],
    ];
    $total['grids']++;
    $total['regions'] += (int) $row['regions'];
    $total['parcels'] += (int) $row['parcels'];
}

header('Content-Type: text/html; charset=utf-8');
echo OpenSim_Helpers_View::render(
    'pages/directory.twig',
    OpenSim_Helpers_View::context() + [
        'title' => _('Directory Info'),
        'grids' => $grids,
        'total' => $total,
        'summary' => sprintf(
            '<strong>%s</strong>, %s, %s',
            sprintf(ngettext('%d grid', '%d grids', $total['grids']), $total['grids']),
            sprintf(ngettext('%d region', '%d regions', $total['regions']), $total['regions']),
            sprintf(ngettext('%d parcel', '%d parcels', $total['parcels']), $total['parcels']),
        ),
        'detail' => array_map(
            fn($grid) => sprintf(
                '(%s, %s)',
                sprintf(ngettext('%d region', '%d regions', $grid['regions']), $grid['regions']),
                sprintf(ngettext('%d parcel', '%d parcels', $grid['parcels']), $grid['parcels']),
            ),
            $grids,
        ),
    ],
);
