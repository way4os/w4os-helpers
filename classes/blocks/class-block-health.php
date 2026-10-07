<?php
/**
 * Block health: the page of the checks of the grid, which the engine makes (OpenSim_Health): every address the grid gives out
 * and what each answers. What the helpers add is what is theirs: their own scripts, at the addresses they are served, and what
 * they need to work. Kept half a minute: each look asks the grid.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once dirname(__DIR__) . '/class-grid-info.php';
require_once dirname(__DIR__) . '/class-grid-config.php';

class OpenSim_Helpers_Block_Health
{
    /** How long a look is kept, in seconds. */
    const TTL = 30;

    /**
     * @param  array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        $key = 'health-' . md5((defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : '') . (defined('OPENSIM_GRID_NAME') ? OPENSIM_GRID_NAME : ''));
        $groups = OpenSim_Helpers_Cache::remember($key, self::TTL, fn() => self::look());

        return ['title' => _('Health'), 'groups' => $groups, 'when' => date('c'), 'cert_title' => _('certificate not trusted by this server')];
    }

    /**
     * What there is to see now: the addresses and the state of the install, with what each address answers.
     *
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function look()
    {
        $settings = OpenSim_Helpers_GridConfig::settings();
        $login = defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : ($settings['login_uri'] ?? null);
        $live = $login ? OpenSim_Helpers_GridInfo::fetch($login) : [];
        $routes = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $groups = OpenSim_Health::answers(self::plan($settings, $login, $live, $routes, defined('OPENSIM_GUIDE_SOURCE') ? (string) OPENSIM_GUIDE_SOURCE : '', self::owner()));
        $groups[] = ['title' => '[DatabaseService]', 'rows' => [self::database_row()]];
        $groups[] = ['title' => _('PHP extensions'), 'rows' => OpenSim_Health::extensions()];
        $files = OpenSim_Health::files($settings ? OpenSim_Helpers_GridConfig::files($settings['dir']) : []);
        if ($files) {
            $groups[] = ['title' => _('Files read'), 'rows' => $files];
        }

        return $groups;
    }

    /**
     * The addresses to ask: the ones of the grid, as the engine makes them, with the scripts of the helpers (where they are
     * served, and only the ones the grid does not already give an address to) and what has no place in OpenSimulator's files.
     *
     * @param  array|null           $settings what OpenSim_Helpers_GridConfig::settings() gave, null without a config
     * @param  string|null          $login    the login address
     * @param  array<string,mixed>  $live     what the grid says at /get_grid_info
     * @param  array<string,string> $routes   the paths the helpers answer
     * @param  string               $guide    the source of the destination guide
     * @param  string|null          $owner    the name of an avatar of the grid (there is always one, the owner), Firstname.Lastname
     * @return list<array{title:string,rows:list<array<string,mixed>>}>
     */
    public static function plan($settings, $login, $live, $routes, $guide, $owner = null)
    {
        $groups = OpenSim_Health::grid(['sources' => $settings['sources'] ?? [], 'live' => $live, 'login' => $login, 'owner' => $owner]);
        $web = rtrim((string) ($settings['web_url'] ?? ''), '/');
        $null = defined('NULL_KEY') ? NULL_KEY : '00000000-0000-0000-0000-000000000000';

        // The scripts of the helpers, each where the helpers serve it: the grid may give it no address (a default one is
        // served), or give it to a setting that is already in the list
        $asked = [];
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $asked[rtrim((string) $row['url'], '/')] = true;
            }
        }
        $at = function ($script) use ($routes, $web, $asked) {
            $path = array_search($script, $routes, true);
            $url = $path === false || $web === '' ? '' : $web . $path;

            return isset($asked[$url]) ? '' : $url;
        };
        $rows = [];
        foreach (
            [
                'query.php' => fn($url) => OpenSim_Health::api('query.php', $url, 'dir_places_query', ['flags' => 0, 'text' => 'health', 'query_start' => '0']),
                'register.php' => fn($url) => [OpenSim_Health::bare('register.php', $url)],
                'offline.php' => fn($url) => [OpenSim_Health::bare('offline.php', $url)],
                'currency.php' => fn($url) => OpenSim_Health::api('currency.php', $url, 'getCurrencyQuote', ['agentId' => $null, 'secureSessionId' => $null, 'currencyBuy' => 0]),
                'landtool.php' => fn($url) => OpenSim_Health::api('landtool.php', $url, 'preflightBuyLandPrep', ['agentId' => $null, 'secureSessionId' => $null, 'currencyBuy' => 0, 'billableArea' => 0]),
                'textgen.php' => fn($url) => [['label' => 'textgen.php', 'url' => $url, 'method' => 'get']],
            ]
            as $script => $rows_of
        ) {
            if (($url = $at($script)) !== '') {
                array_push($rows, ...$rows_of($url));
            }
        }
        $helpers = [['title' => 'opensim-helpers', 'rows' => $rows]];

        // What has no place in OpenSimulator's files, in Helpers.ini
        $own = $settings['helpers'] ?? [];
        $rows = [OpenSim_Health::is_url($guide) ? ['label' => 'GuideSource', 'url' => $guide, 'method' => 'get'] : OpenSim_Health::path('GuideSource', $guide)];
        foreach (['HypEventsURL', 'LogoURL'] as $key) {
            if (OpenSim_Health::is_url($own[$key] ?? '')) {
                $rows[] = ['label' => $key, 'url' => $own[$key], 'method' => 'get'];
            }
        }
        $helpers[] = ['title' => '[Helpers]', 'rows' => $rows];

        // Before the key of the currency, the last of the grid: what the helpers serve is read after what the grid says
        array_splice($groups, -1, 0, $helpers);

        return $groups;
    }

    /**
     * The name of an avatar of the grid to ask a profile for: there is always one, its owner, the first account made (not the
     * account of the services of the grid, which Robust makes for itself).
     *
     * @return string|null Firstname.Lastname, null when the accounts cannot be read
     */
    private static function owner()
    {
        $db = function_exists('db_for') ? db_for('UserAccountService') : null;
        $statement = $db ? $db->prepareAndExecute("SELECT FirstName, LastName FROM UserAccounts WHERE NOT (FirstName = 'GRID' AND LastName = 'SERVICES') ORDER BY Created, FirstName LIMIT 1", []) : false;
        $row = $statement ? $statement->fetch(PDO::FETCH_NUM) : false;

        return $row ? "$row[0].$row[1]" : null;
    }

    /** The database of Robust, the one every query starts from. */
    private static function database_row()
    {
        global $OpenSimDB;

        return OpenSim_Health::row('ConnectionString', !empty($OpenSimDB) && !empty($OpenSimDB->connected));
    }
}
