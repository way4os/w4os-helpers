<?php
// Runs the guide of opensim-helpers 2.x on a source file and prints what it shows, as JSON: its categories with their count, and
// each category's destinations with their link. It made the *.old.json files next to it, the reference of DestinationGuideTest:
// put classes/class-helpers-guide.php and engine/includes/functions*.php of opensim-helpers 2.x (0d82a3c) beside it, then
//   php run.php ../big-display.txt http://speculoos.world:8002 > ../big-display.old.json
//   php run.php ../edge-cases.txt http://grid.example.org:8002 > ../edge-cases.old.json
// Stubs stand for its environment.
$source = $argv[1];
$login = $argv[2] ?? 'http://grid.example.org:8002';
class OpenSim { static function login_uri() { return $GLOBALS['login']; } }
class Engine_Settings { static function get($k, $d = null) { return $d; } static function log_migration_required() {} }
class Helpers { static function url($p) { return "/$p"; } }
define('OSHELPERS_URL', '');
require __DIR__.'/functions.php';
require __DIR__.'/class-helpers-guide.php';
$_SERVER += ['HTTP_HOST' => 'helpers.example.org', 'REQUEST_URI' => '/guide.php'];
$out = [];
$_GET = [];
$guide = new OpenSim_Helpers_Guide($source);
$html = $guide->build_html();
preg_match_all('#<a href="[^"]*category=([^"&]*)[^"]*">.*?<div class="name">(.*?)</div><div class="data">(.*?)</div>#s', $html, $m, PREG_SET_ORDER);
foreach ($m as $c) {
    $name = html_entity_decode($c[2]);
    $out['categories'][] = ['name' => $name, 'data' => $c[3]];
    $_GET = ['category' => $name];
    $page = (new OpenSim_Helpers_Guide($source))->build_html();
    preg_match_all('#<a href="([^"]*)"><div class="item">.*?<div class="name">(.*?)</div>#s', $page, $d, PREG_SET_ORDER);
    $out['destinations'][$name] = array_map(fn ($x) => ['name' => $x[2], 'url' => $x[1]], $d);
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
