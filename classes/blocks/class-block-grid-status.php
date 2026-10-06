<?php
/**
 * Block grid-status: whether the grid answers and its numbers (members, who is in world, regions, area). The numbers are
 * those of the database of Robust, kept a minute.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Block_GridStatus
{
    /** How long the numbers are kept, in seconds. */
    const TTL = 60;

    const NULL_KEY = '00000000-0000-0000-0000-000000000000';

    /**
     * @param  array<string,mixed> $args `hypergrid` false leaves out the visitors of other grids
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        global $OpenSimDB;
        $hypergrid = !array_key_exists('hypergrid', $args) || filter_var($args['hypergrid'], FILTER_VALIDATE_BOOLEAN);
        $login = defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : null;
        $db = $OpenSimDB ?? null;
        $key = 'grid-status-' . md5((string) $login . (defined('OPENSIM_DB_NAME') ? OPENSIM_DB_NAME : ''));
        $numbers = OpenSim_Helpers_Cache::remember(
            $key,
            self::TTL,
            fn() => ['online' => self::online($login), 'counts' => self::counts($db, time())],
        );

        return ['title' => _('Grid Status'), 'rows' => self::rows($numbers, $hypergrid)];
    }

    /**
     * The rows that show the numbers.
     *
     * @param  array{online:?bool,counts:?array<string,mixed>} $numbers
     * @return list<array{label:string,value:string}>
     */
    public static function rows($numbers, $hypergrid = true)
    {
        $rows = [];
        if ($numbers['online'] !== null) {
            $rows[] = ['label' => _('Status'), 'value' => $numbers['online'] ? _('Online') : _('Offline')];
        }
        $counts = $numbers['counts'] ?? null;
        if ($counts) {
            $rows[] = ['label' => _('Members'), 'value' => number_format((int) $counts['members'])];
            $rows[] = ['label' => _('Active members (30 days)'), 'value' => number_format((int) $counts['active_members'])];
            $rows[] = ['label' => _('Members in world'), 'value' => number_format((int) $counts['members_in_world'])];
            if ($hypergrid) {
                $rows[] = ['label' => _('Active users (30 days)'), 'value' => number_format((int) $counts['active_users'])];
                $rows[] = ['label' => _('Total users in world'), 'value' => number_format((int) $counts['total_users'])];
            }
            $rows[] = ['label' => _('Regions'), 'value' => number_format((int) $counts['regions'])];
            $rows[] = ['label' => _('Total area'), 'value' => number_format((float) $counts['area'], 2) . "\u{a0}km²"];
        }

        return $rows;
    }

    /** Whether something answers at the login address, null when there is none. */
    public static function online($login)
    {
        $parts = parse_url((string) $login);
        if (empty($parts['host'])) {
            return null;
        }
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
        // A host that does not answer is a result, not a warning
        set_error_handler(static fn() => true);
        $socket = fsockopen($parts['host'], $port, $errno, $error, 1.0);
        restore_error_handler();
        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * The numbers of the database of Robust, null without one.
     *
     * @param  object|null $db the database (OSPDO)
     * @param  int         $now
     * @return array<string,int|float>|null
     */
    public static function counts($db, $now)
    {
        if (!$db || empty($db->connected)) {
            return null;
        }
        $month = $now - 30 * 86400;
        $zero = self::NULL_KEY;
        $one = function ($sql, $params = []) use ($db) {
            $statement = $db->prepareAndExecute($sql, $params);

            return $statement ? $statement->fetchColumn() : 0;
        };

        return [
            'members' => $one('SELECT COUNT(*) FROM UserAccounts WHERE active = 1'),
            'active_members' => $one(
                'SELECT COUNT(*) FROM GridUser g, UserAccounts u WHERE u.PrincipalID = g.UserID AND CAST(g.Login AS SIGNED) > :month',
                ['month' => $month],
            ),
            'members_in_world' => $one(
                'SELECT COUNT(*) FROM Presence p, UserAccounts u WHERE p.RegionID != :zero AND p.UserID = u.PrincipalID',
                ['zero' => $zero],
            ),
            'active_users' => $one('SELECT COUNT(*) FROM GridUser WHERE CAST(Login AS SIGNED) > :month', ['month' => $month]),
            'total_users' => $one('SELECT COUNT(*) FROM Presence WHERE RegionID != :zero', ['zero' => $zero]),
            'regions' => $one('SELECT COUNT(*) FROM regions'),
            'area' => $one('SELECT ROUND(SUM(sizeX * sizeY / 1000000), 2) FROM regions') ?: 0,
        ];
    }
}
