<?php
/**
 * profile-publish.php
 *
 * The "show in search" box of a profile. The simulator sends it to the profile service of Robust, which ignores it (its update
 * of the profile leaves profileAllowPublish and profileMaturePublish as they were made, false), so the OpenSimSearch module
 * tells it to the search too (avatar_publish_update, query.php), which writes it where the profile service keeps it, for a caller
 * that holds the secure session of the avatar.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

/**
 * Whether the caller holds the secure session of an avatar who is in the grid: what only the simulator the avatar is connected
 * to knows (the economy of the helpers asks the same of a simulator).
 *
 * @param  string   $avatar  the UUID of the avatar
 * @param  mixed    $secure  the secure session ID the caller gives
 * @param  callable $session gives the session of an avatar (opensim_get_avatar_session)
 * @return bool
 */
function helpers_has_session($avatar, $secure, $session = 'opensim_get_avatar_session')
{
    $null = '00000000-0000-0000-0000-000000000000';
    $current = $session($avatar);
    $known = is_array($current) ? strtolower((string) ($current['secureID'] ?? '')) : '';
    $given = is_string($secure) ? strtolower($secure) : '';

    return $known !== '' && $known !== $null && $given !== '' && hash_equals($known, $given);
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
