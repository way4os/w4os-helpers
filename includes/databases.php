<?php
/**
 * The databases of the helpers, opened with OSPDO (the class of the engine).
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link            https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

function tableExists($pdo, $tables)
{
    if (!is_object($pdo)) {
        return false;
    }
    if (!$pdo->connected) {
        return false;
    }
    // error_log("pdo " . print_r($pdo, true));

    if (is_string($tables)) {
        $tables = [$tables];
    }
    foreach ($tables as $table) {
        // Try a select statement against the table
        // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
        try {
            $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        } catch (Exception $e) {
            error_log(__FILE__ . ': ' . SEARCH_DB_NAME . " is missing table $table");
            // We got an exception == table not found
            return false;
        }
        if ($result == false) {
            error_log(__FILE__ . ': ' . SEARCH_DB_NAME . " is missing table $table");
            return false;
        }
    }
    return true;
}

if (defined('OPENSIM_DB') && OPENSIM_DB === true) {
    $OpenSimDB = new OSPDO(
        'mysql:host=' . OPENSIM_DB_HOST . ';dbname=' . OPENSIM_DB_NAME,
        OPENSIM_DB_USER,
        OPENSIM_DB_PASS,
    );
}

/**
 * The database of a service of OpenSimulator (UserAccountService, GridUserService, PresenceService, GridService,
 * UserProfilesService...), chosen the way OpenSimulator chooses it: the one the service has of its own in the config
 * (OPENSIM_DB_SERVICES), else the one of Robust. Each is opened once.
 *
 * @param  string|null $service the section of the service in the Robust config
 * @return OSPDO|null
 */
function db_for($service = null)
{
    static $handles = [];
    global $OpenSimDB;
    $own = $service !== null && defined('OPENSIM_DB_SERVICES') ? OPENSIM_DB_SERVICES[$service] ?? null : null;
    if (empty($own['hostname']) || empty($own['prefix'])) {
        return $OpenSimDB ?? null;
    }
    $key = md5(serialize($own));
    if (!isset($handles[$key])) {
        $handles[$key] = new OSPDO(
            'mysql:host=' . $own['hostname'] . ';dbname=' . $own['prefix'],
            $own['user'] ?? null,
            $own['password'] ?? null,
        );
    }

    return $handles[$key];
}

// The offline messages are those of the OfflineIMService of Robust: its database, Robust's unless it has its own
if (defined('OFFLINE_DB_HOST') && defined('OFFLINE_DB_NAME') && defined('OFFLINE_DB_USER') && defined('OFFLINE_DB_PASS')) {
    $OfflineDB =
        defined('OPENSIM_DB_HOST') &&
        OFFLINE_DB_HOST === OPENSIM_DB_HOST &&
        OFFLINE_DB_NAME === OPENSIM_DB_NAME &&
        OFFLINE_DB_USER === OPENSIM_DB_USER &&
        isset($OpenSimDB)
            ? $OpenSimDB
            : new OSPDO('mysql:host=' . OFFLINE_DB_HOST . ';dbname=' . OFFLINE_DB_NAME, OFFLINE_DB_USER, OFFLINE_DB_PASS);
}
