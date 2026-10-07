<?php
/**
 * profile-publish.php
 *
 * The "show in search" box of a profile. The simulator sends it to the profile service of Robust, which ignores it (its update
 * of the profile leaves profileAllowPublish and profileMaturePublish as they were made, false), so the OpenSimSearch module
 * tells it to the search too (avatar_publish_update, query.php), which writes it where the profile service keeps it.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

/**
 * Whether a call comes from a simulator of the grid: one registered to the search, or the machine of the site itself.
 *
 * @param  string   $remote   the address of the caller
 * @param  string[] $hosts    the hosts the simulators registered with (addresses or names)
 * @param  string   $server   the address of this site, for the simulators on the same machine
 * @param  callable $resolve  gives the addresses of a name (gethostbynamel)
 * @return bool
 */
function helpers_is_simulator($remote, $hosts, $server = '', $resolve = 'gethostbynamel')
{
    if ($remote === '') {
        return false;
    }
    if ($remote === '127.0.0.1' || $remote === '::1' || ($server !== '' && $remote === $server)) {
        return true;
    }
    foreach ($hosts as $host) {
        if ($host === $remote) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false && in_array($remote, $resolve($host) ?: [], true)) {
            return true;
        }
    }

    return false;
}

/**
 * Write the "show in search" box of a profile that exists.
 *
 * @param  object $db     the database of the profile service (prepareAndExecute)
 * @param  string $avatar the UUID of the avatar
 * @param  mixed  $allow  the profile is published
 * @param  mixed  $mature the published profile is mature
 * @return string ok, invalid (not an avatar) or missing (the avatar made no profile)
 */
function helpers_profile_publish($db, $avatar, $allow, $mature)
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $avatar)) {
        return 'invalid';
    }
    $found = $db->prepareAndExecute('SELECT useruuid FROM userprofile WHERE useruuid = :id', ['id' => $avatar]);
    if (!$found || !$found->fetchAll()) {
        return 'missing';
    }
    $db->prepareAndExecute('UPDATE userprofile SET profileAllowPublish = :allow, profileMaturePublish = :mature WHERE useruuid = :id', [
        'allow' => filter_var($allow, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        'mature' => filter_var($mature, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        'id' => $avatar,
    ]);

    return 'ok';
}
