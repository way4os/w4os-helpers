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
        $hypergrid = !array_key_exists('hypergrid', $args) || filter_var($args['hypergrid'], FILTER_VALIDATE_BOOLEAN);
        $login = defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : null;
        $key = 'grid-status-' . md5((string) $login . (defined('OPENSIM_DB_NAME') ? OPENSIM_DB_NAME : ''));
        $numbers = OpenSim_Helpers_Cache::remember(
            $key,
            self::TTL,
            fn() => ['online' => self::online($login), 'counts' => self::counts(function_exists('db_for') ? 'db_for' : fn() => null, time())],
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
            // A keyword the viewers read (Firestorm takes the state of the grid from it), as Second Life says it: not translated
            $rows[] = ['label' => _('Status'), 'value' => $numbers['online'] ? 'ONLINE' : 'OFFLINE'];
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
     * The numbers of the databases of Robust, each from the database of its own service (the accounts in the one of
     * UserAccountService, the last logins in GridUserService, who is in world in PresenceService, the regions in
     * GridService): Robust's unless the service has its own. Two services on one database are counted with one query, on
     * two databases a count asks the one for the identifiers and the other for who they are.
     *
     * @param  callable $db_for gives the database of a service (db_for)
     * @param  int      $now
     * @return array<string,int|float>|null null when there is no database to ask
     */
    public static function counts($db_for, $now)
    {
        $accounts = $db_for('UserAccountService');
        $users = $db_for('GridUserService');
        $presence = $db_for('PresenceService');
        $grid = $db_for('GridService');
        foreach ([$accounts, $users, $presence, $grid] as $db) {
            if (!$db || empty($db->connected)) {
                return null;
            }
        }
        $month = $now - 30 * 86400;
        $zero = self::NULL_KEY;
        $one = function ($db, $sql, $params = []) {
            $statement = $db->prepareAndExecute($sql, $params);

            return $statement ? $statement->fetchColumn() : 0;
        };
        // How many of the users are accounts: one query when both are in one database, else by groups of identifiers
        $accounts_among = function ($db, $sql, $params, $ids_sql) use ($accounts, $one) {
            if ($db === $accounts) {
                return $one($accounts, $sql, $params);
            }
            $statement = $db->prepareAndExecute($ids_sql, $params);
            $ids = $statement ? $statement->fetchAll(PDO::FETCH_COLUMN) : [];
            $count = 0;
            foreach (array_chunk($ids, 500) as $group) {
                $marks = implode(',', array_fill(0, count($group), '?'));
                $count += (int) $one($accounts, "SELECT COUNT(*) FROM UserAccounts WHERE PrincipalID IN ($marks)", $group);
            }

            return $count;
        };

        return [
            'members' => $one($accounts, 'SELECT COUNT(*) FROM UserAccounts WHERE active = 1'),
            'active_members' => $accounts_among(
                $users,
                'SELECT COUNT(*) FROM GridUser g, UserAccounts u WHERE u.PrincipalID = g.UserID AND CAST(g.Login AS SIGNED) > :month',
                ['month' => $month],
                'SELECT UserID FROM GridUser WHERE CAST(Login AS SIGNED) > :month',
            ),
            'members_in_world' => $accounts_among(
                $presence,
                'SELECT COUNT(*) FROM Presence p, UserAccounts u WHERE p.RegionID != :zero AND p.UserID = u.PrincipalID',
                ['zero' => $zero],
                'SELECT UserID FROM Presence WHERE RegionID != :zero',
            ),
            'active_users' => $one($users, 'SELECT COUNT(*) FROM GridUser WHERE CAST(Login AS SIGNED) > :month', ['month' => $month]),
            'total_users' => $one($presence, 'SELECT COUNT(*) FROM Presence WHERE RegionID != :zero', ['zero' => $zero]),
            'regions' => $one($grid, 'SELECT COUNT(*) FROM regions'),
            'area' => $one($grid, 'SELECT ROUND(SUM(sizeX * sizeY / 1000000), 2) FROM regions') ?: 0,
        ];
    }
}
