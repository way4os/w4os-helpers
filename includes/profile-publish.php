<?php
/**
 * profile-publish.php
 *
 * The "show in search" box of a profile. What OpenSimulator shows the viewer is the first flag of the user account
 * (its first flag, UserFlags, read by the simulator, kept one hour), but the profile service of Robust, which gets the box with the rest of the
 * profile, never writes them. So the OpenSimSearch module tells it to the search too (avatar_publish_update, query.php), which
 * writes them, for a caller that holds the secure session of the avatar.
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
 * Write the "show in search" box of a profile: the first flag of the account, which is what OpenSimulator shows the viewer and
 * keeps. The other flags of the account (identified, transacted, age verified...) are not the user's choice here: left as they are.
 *
 * @param  object $db     the database of the user accounts service (prepareAndExecute)
 * @param  string $avatar the UUID of the avatar
 * @param  mixed  $allow  the profile is shown in search
 * @return string ok, invalid (not an avatar) or missing (not an account of the grid)
 */
function helpers_profile_publish($db, $avatar, $allow)
{
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $avatar)) {
        return 'invalid';
    }
    $found = $db->prepareAndExecute('SELECT UserFlags FROM UserAccounts WHERE PrincipalID = :id', ['id' => $avatar]);
    $row = $found ? $found->fetchAll() : [];
    if (!$row) {
        return 'missing';
    }
    $flags = ((int) ($row[0]['UserFlags'] ?? 0) & ~1) | (filter_var($allow, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
    $db->prepareAndExecute('UPDATE UserAccounts SET UserFlags = :flags WHERE PrincipalID = :id', ['flags' => $flags, 'id' => $avatar]);

    return 'ok';
}
