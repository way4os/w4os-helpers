<?php
/**
 * Block profile: the web page of an avatar of the grid (`web_profile_url`), what its profile says of it for who may see it:
 * its name, since when it is a member, what it published (its text, its languages, its web page), its picks and its
 * classifieds. A profile that is not published shows its name and the date of its account, nothing else.
 *
 * The profile is the one of the profile service of Robust (UserProfilesService: the tables userprofile, userpicks and
 * classifieds), the avatar is an account of the grid (UserAccounts). The page of an avatar that does not exist is a 404.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Block_Profile
{
    /** The account of the services of the grid, which makes no profile. */
    const SERVICES = ['GRID', 'SERVICES'];

    /**
     * @param  array<string,mixed> $args `name`: First.Last, the way the address of a profile says it
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        $name = (string) ($args['name'] ?? '');
        [$first, $last] = array_pad(explode('.', explode('@', $name)[0], 2), 2, '');
        $accounts = function_exists('db_for') ? db_for('UserAccountService') : null;
        $profiles = function_exists('db_for') ? db_for('UserProfilesService') : null;
        $fetch = static function ($db, $sql, $params) {
            $statement = $db ? $db->prepareAndExecute($sql, $params) : false;

            return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        };

        $account = $fetch($accounts, 'SELECT PrincipalID, FirstName, LastName, Created, UserTitle FROM UserAccounts WHERE FirstName = :first AND LastName = :last AND active = 1', ['first' => $first, 'last' => $last])[0] ?? null;
        if ($account === null || ($account['FirstName'] === self::SERVICES[0] && $account['LastName'] === self::SERVICES[1])) {
            return self::missing($name);
        }
        $id = $account['PrincipalID'];
        $profile = $fetch($profiles, 'SELECT * FROM userprofile WHERE useruuid = :id', ['id' => $id])[0] ?? [];
        $picks = $fetch($profiles, "SELECT name, description, simname, posglobal, gatekeeper FROM userpicks WHERE creatoruuid = :id AND enabled = 'true' ORDER BY sortorder, name", ['id' => $id]);
        $classifieds = $fetch($profiles, 'SELECT name, description, simname, parcelname, creationdate, expirationdate FROM classifieds WHERE creatoruuid = :id AND expirationdate > :now ORDER BY creationdate DESC', ['id' => $id, 'now' => time()]);
        $partner = null;
        if (!empty($profile['profilePartner']) && $profile['profilePartner'] !== NULL_KEY) {
            $row = $fetch($accounts, 'SELECT FirstName, LastName FROM UserAccounts WHERE PrincipalID = :id', ['id' => $profile['profilePartner']])[0] ?? null;
            $partner = $row ? ['name' => $row['FirstName'] . ' ' . $row['LastName'], 'url' => self::url($row['FirstName'], $row['LastName'])] : null;
        }

        return self::build($account, $profile, $picks, $classifieds, $partner);
    }

    /**
     * What the page shows, from what the grid keeps of an avatar.
     *
     * @param  array<string,mixed>             $account     UserAccounts: FirstName, LastName, Created, UserTitle
     * @param  array<string,mixed>             $profile     userprofile, empty when the avatar made none
     * @param  list<array<string,mixed>>       $picks       userpicks
     * @param  list<array<string,mixed>>       $classifieds classifieds still on
     * @param  array{name:string,url:?string}|null $partner
     * @return array<string,mixed>
     */
    public static function build($account, $profile, $picks, $classifieds, $partner = null)
    {
        $name = $account['FirstName'] . ' ' . $account['LastName'];
        $published = self::flag($profile['profileAllowPublish'] ?? null);
        $text = static fn($key) => trim((string) ($profile[$key] ?? ''));
        $data = [
            'title' => $name,
            'found' => true,
            'name' => $name,
            'member_since' => (int) ($account['Created'] ?? 0) > 0 ? date('Y-m-d', (int) $account['Created']) : '',
            'account_title' => trim((string) ($account['UserTitle'] ?? '')),
            // What the avatar did not publish is not shown
            'published' => $published,
            'about' => $published ? $text('profileAboutText') : '',
            'first_life' => $published ? $text('profileFirstText') : '',
            'wants' => $published ? $text('profileWantToText') : '',
            'skills' => $published ? $text('profileSkillsText') : '',
            'languages' => $published ? $text('profileLanguages') : '',
            'web' => $published && preg_match('#^https?://#i', $text('profileURL')) ? $text('profileURL') : '',
            'partner' => $published ? $partner : null,
            'picks' => [],
            'classifieds' => [],
        ];
        if ($published) {
            foreach ($picks as $pick) {
                $data['picks'][] = [
                    'name' => (string) $pick['name'],
                    'description' => trim((string) $pick['description']),
                    'place' => (string) ($pick['simname'] ?? ''),
                    'url' => self::hop((string) ($pick['gatekeeper'] ?? ''), (string) ($pick['simname'] ?? ''), (string) ($pick['posglobal'] ?? '')),
                ];
            }
            foreach ($classifieds as $ad) {
                $data['classifieds'][] = [
                    'name' => (string) $ad['name'],
                    'description' => trim((string) $ad['description']),
                    'place' => trim(($ad['parcelname'] ?? '') . (empty($ad['simname']) ? '' : ', ' . $ad['simname']), ', '),
                    'until' => date('Y-m-d', (int) $ad['expirationdate']),
                ];
            }
        }

        return $data;
    }

    /** The page of an avatar that is not there: a 404, with the layout of the site. */
    private static function missing($name)
    {
        OpenSim_Helpers_View::set_status(404);

        return ['title' => _('Profile not found'), 'found' => false, 'name' => $name];
    }

    /** The address of the profile of an avatar, where the grid has its profiles. */
    private static function url($first, $last)
    {
        $path = OpenSim_Helpers_View::page_path('profile');

        return $path === null ? null : rtrim($path, '/') . '/' . rawurlencode("$first.$last");
    }

    /**
     * Whether a flag of the profile service is on: the column is binary, a byte 0 or 1, or the character of the digit.
     *
     * @param  mixed $value
     * @return bool
     */
    public static function flag($value)
    {
        return $value === true || in_array($value, ["\x01", '1', 1], true);
    }

    /**
     * The link of a pick: its place as a hop link when the grid and the place are known. The place is the global position
     * (`<x,y,z>` in meters), of which the position in the region is the rest of the division by the size of a region.
     */
    private static function hop($gatekeeper, $region, $posglobal)
    {
        if ($region === '' || !preg_match('/<?\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/', $posglobal, $m)) {
            return null;
        }
        $grid = $gatekeeper !== '' ? $gatekeeper : (defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : '');
        $host = preg_replace('#^https?://#i', '', rtrim($grid, '/'));
        if ($host === '') {
            return null;
        }
        $place = sprintf('%s:%s/%d/%d/%d', $host, $region, fmod((float) $m[1], 256), fmod((float) $m[2], 256), (float) $m[3]);

        return opensim_format_tp($place, TPLINK_HOP);
    }
}
