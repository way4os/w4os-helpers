<?php
/**
 * register.php
 *
 * Script called by the simulator to register on the search engine. Actual data
 * fetch is processed by parser.php.
 *
 * Requires OpenSimulator Search module
 *   [OpenSimSearch](https://github.com/kcozens/OpenSimSearch)
 * Events are fetched from 2do HYPEvents or any other HYPEvents implementation
 *   [2do HYPEvents](https://2do.directory)
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link            https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 *
 * Includes portions of code from
 *   [OpenSimSearch](https://github.com/kcozens/OpenSimSearch)
 */

require_once 'includes/bootstrap.php';
require_once 'includes/search.php';
require_once 'includes/search-parser.php';

if (!$SearchDB || !$SearchDB->connected) {
    osDie('Bad configuration: the search database is not available', 503);
}

$host = $_GET['host'] ?? '';
$port = $_GET['port'] ?? '';
$service = $_GET['service'] ?? '';

if ($host == '' || $port == '') {
    osDie('Bad request: missing simulator host and/or port', 400);
}

switch ($service) {
    case 'online':
        // Check if there is already a database row for this host
        $query = $SearchDB->prepare('SELECT register FROM hostsregister WHERE host = :host AND port = :port');
        $query->execute([
            ':host' => $host,
            ':port' => $port,
        ]);

        // Get the request time as a timestamp for later
        $timestamp = $_SERVER['REQUEST_TIME'];

        // If a database row was returned check the nextcheck date
        if ($query->rowCount() > 0) {
            $query = $SearchDB->prepare(
                'UPDATE hostsregister
      SET register = :timestamp, nextcheck = 0, checked = 0, failcounter = 0
      WHERE host = :host AND port = :port',
            );
        } else {
            // The SELECT did not return a result. Insert a new record.
            $query = $SearchDB->prepare("INSERT INTO hostsregister VALUES (:host, :port, :timestamp, 0, 0, 0, '')");
            // $query->execute( array($host, $port, $timestamp) );
        }
        $query->execute([
            ':host' => $host,
            ':port' => $port,
            ':timestamp' => $timestamp,
        ]);

        // Read what the simulator tells, here and not by a request to this same site: its own PHP may not trust its certificate
        ossearch_answer_sent(); // make sure simulator doesn't pause start process
        sleep(2); // leave simulator start process some time before querying it
        ossearch_parse();
        touch(get_writable_tmp_dir() . '/ossearch-parsed');
        break;

    case 'offline':
        ossearch_hostUnregister($host, $port);
        break;

    default:
        osDie('Bad request: service is online or offline', 400);
}

die();
