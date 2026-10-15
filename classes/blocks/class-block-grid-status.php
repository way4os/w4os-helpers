<?php
/**
 * Block grid-status: whether the grid answers and its numbers (members, who is in world, regions, area). The numbers are
 * counted by the engine (OpenSim\Data\Stats) in the databases of Robust, and kept a minute.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Block_GridStatus
{
    /** How long the numbers are kept, in seconds. */
    const TTL = 60;

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
            fn() => ['online' => self::online($login), 'counts' => \OpenSim\Data\Stats::counts(function_exists('db_for') ? 'db_for' : fn() => null, time())],
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
}
