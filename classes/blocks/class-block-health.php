<?php
/**
 * Block health: the page of the checks of the grid, which the engine makes (OpenSim_Health): every address the grid gives out
 * and what each answers, by file of the config. What the helpers add is what is theirs: their own scripts, at the addresses they
 * are served, and what they need to work. Kept half a minute: each look asks the grid.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
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
        // What the grid says of itself, as it says it (the pages make a name of the address when it says none, not the checks)
        $live = $login ? OpenSim_Health::grid_info($login) : [];
        $routes = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $groups = OpenSim_Health::answers(self::plan($settings, $login, $live, $routes, defined('OPENSIM_GUIDE_SOURCE') ? (string) OPENSIM_GUIDE_SOURCE : '', self::owner()));
        $groups[] = ['file' => OpenSim_Health::names($settings['config'] ?? null)['robust'], 'title' => '[DatabaseService]', 'rows' => [self::database_row()]];
        $groups[] = ['file' => _('PHP extensions'), 'title' => '', 'rows' => OpenSim_Health::extensions()];
        $files = OpenSim_Health::files(isset($settings['config']) ? $settings['config']->files() : []);
        if ($files) {
            $groups[] = ['file' => _('Files read'), 'title' => '', 'rows' => $files];
        }

        return self::arrange($groups);
    }

    /**
     * The groups of a file together, files in the order they first appear, and the first group of each file says so (the page
     * names the file once).
     *
     * @param  list<array{file:string,title:string,rows:array}> $groups
     * @return list<array<string,mixed>>
     */
    public static function arrange($groups)
    {
        $by = [];
        foreach ($groups as $group) {
            $by[$group['file']][] = $group;
        }
        $arranged = [];
        foreach ($by as $file_groups) {
            foreach ($file_groups as $i => $group) {
                $arranged[] = $group + ['first_of_file' => $i === 0];
            }
        }

        return $arranged;
    }

    /**
     * The addresses to ask: the ones of the grid, as the engine makes them, with the scripts of the helpers (where they are
     * served, and only the ones the grid does not already give an address to) and what has no place in OpenSimulator's files.
     *
     * @param  array|null           $settings what OpenSim_Helpers_GridConfig::settings() gave (its `config` is the one of the
     *                                        engine, OpenSim_Config), null without a config
     * @param  string|null          $login    the login address
     * @param  array<string,mixed>  $live     what the grid says at /get_grid_info
     * @param  array<string,string> $routes   the paths the helpers answer
     * @param  string               $guide    the source of the destination guide
     * @param  string|null          $owner    the name of an avatar of the grid (there is always one, the owner), Firstname.Lastname
     * @return list<array{file:string,title:string,rows:list<array<string,mixed>>}>
     */
    public static function plan($settings, $login, $live, $routes, $guide, $owner = null)
    {
        $groups = OpenSim_Health::grid(['config' => $settings['config'] ?? null, 'live' => $live, 'login' => $login, 'owner' => $owner]);
        $web = rtrim((string) ($settings['web_url'] ?? ''), '/');
        $null = defined('NULL_KEY') ? NULL_KEY : '00000000-0000-0000-0000-000000000000';

        // The scripts of the helpers, each where the helpers serve it: the grid may give it no address (a default one is
        // served), or give it to a setting that is already in the list
        $asked = [];
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $asked[rtrim((string) ($row['url'] ?? ''), '/')] = true;
            }
        }
        $at = function ($script) use ($routes, $web, $asked) {
            $path = array_search($script, $routes, true);
            $url = $path === false || $web === '' ? '' : $web . $path;

            return isset($asked[$url]) ? '' : $url;
        };
        // Each service is called twice, with nothing (it must refuse) and with what it expects (it must answer)
        $rows = [];
        foreach (
            [
                'query.php' => fn($url) => OpenSim_Health::xmlrpc('query.php', $url, 'dir_places_query', ['flags' => 0, 'text' => 'health', 'query_start' => '0']),
                'register.php' => fn($url) => OpenSim_Health::query('register.php', $url, 'service=offline&host=health.invalid&port=1'),
                'offline.php' => fn($url) => OpenSim_Health::post('offline.php', $url, OpenSim_Health::retrieve_messages(), 'RetrieveMessages/'),
                'currency.php' => fn($url) => OpenSim_Health::xmlrpc('currency.php', $url, 'getCurrencyQuote', ['agentId' => $null, 'secureSessionId' => $null, 'currencyBuy' => 0]),
                'landtool.php' => fn($url) => OpenSim_Health::xmlrpc('landtool.php', $url, 'preflightBuyLandPrep', ['agentId' => $null, 'secureSessionId' => $null, 'currencyBuy' => 0, 'billableArea' => 0]),
                'textgen.php' => fn($url) => [OpenSim_Health::page('textgen.php', $url)],
            ]
            as $script => $rows_of
        ) {
            if (($url = $at($script)) !== '') {
                array_push($rows, ...$rows_of($url));
            }
        }

        // What has no place in OpenSimulator's files, in Helpers.ini
        $own = $settings['helpers'] ?? [];
        $helpers = [OpenSim_Health::is_url($guide) ? OpenSim_Health::page('GuideSource', $guide) : OpenSim_Health::path('GuideSource', $guide)];
        foreach (['HypEventsURL', 'LogoURL'] as $key) {
            if (OpenSim_Health::is_url($own[$key] ?? '')) {
                $helpers[] = OpenSim_Health::page($key, $own[$key]);
            }
        }

        $groups[] = ['file' => 'Helpers.ini', 'title' => '[Helpers]', 'rows' => $helpers];
        $groups[] = ['file' => 'opensim-helpers', 'title' => '', 'rows' => $rows];

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
