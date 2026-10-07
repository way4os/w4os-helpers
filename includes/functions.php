<?php
/**
 * includes/functions.php
 *
 * Provides functions required by opensimulator helpers
 *
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link		https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

// Only when the extension is missing: the functions of a file are declared when it is compiled, it cannot guard itself
if (!function_exists('xmlrpc_encode')) {
    require_once dirname(__DIR__) . '/vendor/magicoli/opensim-engine/includes/library-xmlrpc.php';
}

// The folder of the translations of the helpers, before the engine defines its own
defined('HELPERS_LOCALE_DIR') || define('HELPERS_LOCALE_DIR', dirname(__DIR__) . '/languages');

// What does not depend on the helpers is the engine's: destinations and teleport links, UUIDs, XML answers and errors, the
// locale, the constants of those (NULL_KEY, TPLINK_*). Only what has a meaning for the helpers alone is defined here.
require_once dirname(__DIR__) . '/vendor/magicoli/opensim-engine/includes/functions.php';

/**
 * Refuse a request made with another method than the ones a script answers: 405, with the methods it does answer. A script that
 * talks XML-RPC says it in XML.
 *
 * @param string|string[] $methods the methods allowed
 * @param bool            $xml     whether the script talks XML-RPC
 */
function helpers_require_method($methods, $xml = false)
{
    $methods = array_map('strtoupper', (array) $methods);
    if (in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? $methods[0]), $methods, true)) {
        return;
    }
    header('Allow: ' . implode(', ', $methods));
    $xml ? osXmlDie('Method not allowed: ' . implode(' or ', $methods) . ' only', 405) : osDie('Method not allowed: ' . implode(' or ', $methods) . ' only', 405);
}

function os_cache_get($key, $default = null)
{
    global $oshelpers_cache;
    return isset($oshelpers_cache[$key]) ? $oshelpers_cache[$key] : $default;
}

function os_cache_set($key, $value, $expire = 0)
{
    global $oshelpers_cache;
    $oshelpers_cache[$key] = $value;
}

/**
 * OpenSim source to help further attempts to allow Hypergrid search results.
 * Infouuid is a fake parcelid resolving to region handle and (region-level?)
 * pos which might (or not) give enough information to allow hg results.
 * 1. Link region locally with link-region (or directly in db?)
 * 2. Use local link region handle (instead of remote one) to generate infouuid
 * 3. Use local link Global pos instead of remote one
 */
//
// public static UUID BuildFakeParcelID(ulong regionHandle, uint x, uint y)
// {
// byte[] bytes =
// {
// (byte)regionHandle, (byte)(regionHandle >> 8), (byte)(regionHandle >> 16), (byte)(regionHandle >> 24),
// (byte)(regionHandle >> 32), (byte)(regionHandle >> 40), (byte)(regionHandle >> 48), (byte)(regionHandle >> 56),
// (byte)x, (byte)(x >> 8), 0, 0,
// (byte)y, (byte)(y >> 8), 0, 0 };
// return new UUID(bytes, 0);
// }
//
// public static UUID BuildFakeParcelID(ulong regionHandle, uint x, uint y, uint z)
// {
// byte[] bytes =
// {
// (byte)regionHandle, (byte)(regionHandle >> 8), (byte)(regionHandle >> 16), (byte)(regionHandle >> 24),
// (byte)(regionHandle >> 32), (byte)(regionHandle >> 40), (byte)(regionHandle >> 48), (byte)(regionHandle >> 56),
// (byte)x, (byte)(x >> 8), (byte)z, (byte)(z >> 8),
// (byte)y, (byte)(y >> 8), 0, 0 };
// return new UUID(bytes, 0);
// }
