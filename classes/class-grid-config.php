<?php
/**
 * The settings of the helpers from the config of a grid, when there is no includes/config.php.
 *
 * One source of truth, one format. The grids of a machine are in /etc/opensim/grids/<grid>/ and the helpers read what
 * OpenSimulator reads, as it writes it: the Robust config (Robust.HG.ini or Robust.ini), OpenSim.ini and the ini of the
 * simulators (sims/*.ini), the connection strings in the format of .NET. A value that has its place there (the name of the
 * grid, its addresses, the message of the day, the databases) is read from there and nowhere else.
 *
 * Helpers.ini, in the same format, holds what has no official place, in a section [Helpers] (the source of the guide, the
 * search database, the theme...), and may hold any other setting: a web host that has no OpenSimulator on it has a reduced
 * copy of the sections it needs. What the other files say wins over Helpers.ini, which only fills what they leave out.
 *
 * Which grid: the one named by OPENSIM_GRID (constant, environment or web server variable, the virtual host of a grid
 * sets it), else the only grid there is. The files are the only source: what they do not say, bootstrap.php has a
 * default for. Read only, nothing is created.
 *
 * The files are read by the engine (OpenSim_Config: the files they include, the constants, a nested array that follows the
 * structure of the ini files); what the helpers do here is find the grid, make the settings and the constants of their own.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

class OpenSim_Helpers_GridConfig
{
    /** @var array|null what settings() gave for the grid served, for the addons of the modules (see section()) */
    private static $current = null;

    const DEFAULT_CONF = '/etc/opensim/opensim.conf';
    const DEFAULT_ETC = '/etc/opensim';

    /** The file of what has no official place. */
    const HELPERS_FILE = 'Helpers.ini';

    /** The scripts of the helpers, which an address names. */
    const SCRIPTS = ['query.php', 'register.php', 'offline.php', 'currency.php', 'landtool.php', 'motd.php', 'textgen.php', 'parser.php', 'eventsparser.php'];

    /**
     * Define the constants of the grid.
     *
     * @return string|null why it cannot, null when the constants are defined
     */
    public static function load()
    {
        $profile = self::profile();
        $grids = self::grids($profile);
        $etc = self::etc_root($profile);
        $nick = self::requested_nick();
        if (!$grids) {
            return "no includes/config.php, and no grid in $etc/grids (see README.md)";
        }
        if ($nick !== '' && !isset($grids[$nick])) {
            return "grid $nick not found in $etc/grids (" . implode(', ', array_keys($grids)) . ')';
        }
        if ($nick === '' && count($grids) > 1) {
            return "several grids in $etc/grids (" .
                implode(', ', array_keys($grids)) .
                '), give the nick of the one to serve in OPENSIM_GRID';
        }

        $settings = self::settings($nick);
        // The files hold the passwords of the databases: unreadable for the web server, the helpers would use the placeholders
        foreach ($settings['config']->files() as $file) {
            if (is_file($file) && !is_readable($file)) {
                return "$file is not readable by the web server user (every ini file of the grid belongs to the group of the web server, mode 640: chgrp www-data)";
            }
        }
        self::$current = $settings;
        self::define_constants($settings);

        return null;
    }

    /**
     * A section of the config of the grid, whatever the file: what a module says of itself ([Gloebit]), for the addon that
     * knows the module. The first file that has a key wins, as everywhere.
     *
     * @param  string $name
     * @return array<string,string> empty when there is no such section, or no grid config
     */
    public static function section($name)
    {
        return isset(self::$current['config']) ? self::$current['config']->section($name) : [];
    }

    /**
     * The config of the grid in the form the engine reads, to ask its models (OpenSim\Models\Avatar...): the files of the grid when
     * the helpers read them, else the databases the constants of includes/config.php give.
     *
     * @return \OpenSim\Config\GridConfig
     */
    public static function engine_config()
    {
        return self::$current['config'] ?? self::config_from_constants(get_defined_constants(true)['user'] ?? []);
    }

    /**
     * A config for the engine from the constants of the historic includes/config.php: Robust's database, the one of each service that
     * has its own, and the search and the offline messages when they are not Robust's.
     *
     * @param  array<string,mixed> $constants
     * @return \OpenSim\Config\GridConfig
     */
    public static function config_from_constants($constants)
    {
        $string = static fn($host, $name, $user, $password) => sprintf('Data Source=%s;Database=%s;User ID=%s;Password=%s;Old Guids=true;', $host, $name, $user, $password);
        $of = static fn($prefix) => isset($constants["{$prefix}_DB_HOST"], $constants["{$prefix}_DB_NAME"])
            ? $string($constants["{$prefix}_DB_HOST"], $constants["{$prefix}_DB_NAME"], $constants["{$prefix}_DB_USER"] ?? '', $constants["{$prefix}_DB_PASS"] ?? '')
            : null;
        $tree = [];
        $robust = $of('OPENSIM');
        if ($robust !== null) {
            $tree['Robust']['DatabaseService']['ConnectionString'] = $robust;
        }
        foreach ((array) ($constants['OPENSIM_DB_SERVICES'] ?? []) as $section => $own) {
            $tree['Robust'][$section]['ConnectionString'] = $string($own['hostname'] ?? '', $own['prefix'] ?? '', $own['user'] ?? '', $own['password'] ?? '');
        }
        if (($search = $of('SEARCH')) !== null && $search !== $robust) {
            $tree['Helpers']['Helpers']['SearchConnectionString'] = $search;
        }
        if (($offline = $of('OFFLINE')) !== null && $offline !== $robust) {
            $tree['Robust']['Messaging']['ConnectionString'] = $offline;
        }

        return new \OpenSim\Config\GridConfig($tree);
    }

    /**
     * The opensim.conf to read: OPENSIM_CONF (constant or environment), else the one of the system.
     *
     * @return string|null
     */
    public static function conf_path()
    {
        $path = defined('OPENSIM_CONF') ? OPENSIM_CONF : getenv('OPENSIM_CONF');
        if (empty($path)) {
            $path = $_SERVER['OPENSIM_CONF'] ?? self::DEFAULT_CONF;
        }

        return is_file($path) ? $path : null;
    }

    /**
     * The profile of opensim.conf: [Defaults] completed by the section of the default profile.
     *
     * @param  string|null $conf
     * @return array<string,string> empty when there is no opensim.conf
     */
    public static function profile($conf = null)
    {
        $conf ??= self::conf_path();
        $ini = $conf === null ? [] : OpenSim_Config::read_ini($conf);
        $defaults = $ini['Defaults'] ?? [];
        $profile = $defaults;
        if (!empty($defaults['DefaultProfile']) && isset($ini[$defaults['DefaultProfile']])) {
            $profile = array_merge($defaults, $ini[$defaults['DefaultProfile']]);
        }

        return $profile;
    }

    /** Where the config of the machine is: EtcRoot of the profile, else /etc/opensim. */
    private static function etc_root($profile)
    {
        return rtrim(!empty($profile['EtcRoot']) ? $profile['EtcRoot'] : self::DEFAULT_ETC, '/');
    }

    /**
     * The grids of the profile: the folders of EtcRoot/grids/ (/etc/opensim/grids/ without opensim.conf) with a
     * Robust config or a Helpers.ini.
     *
     * @param  array<string,string> $profile
     * @return array<string,string> nick => folder
     */
    public static function grids($profile)
    {
        $etc = self::etc_root($profile);
        $grids = [];
        foreach (glob("$etc/grids/*", GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (['Robust.HG.ini', 'Robust.ini', self::HELPERS_FILE] as $file) {
                if (is_file("$dir/$file")) {
                    $grids[basename($dir)] = $dir;
                    break;
                }
            }
        }

        return $grids;
    }

    /**
     * The nick of the grid the environment names, empty when it does not.
     *
     * @return string
     */
    private static function requested_nick()
    {
        return (string) (defined('OPENSIM_GRID')
            ? OPENSIM_GRID
            : (getenv('OPENSIM_GRID') ?:
            $_SERVER['OPENSIM_GRID'] ?? ''));
    }

    /**
     * The grid asked, or the one the environment names, or the only one.
     *
     * @param  string|null $nick
     * @param  string|null $conf
     * @return string|null its nick, null when it cannot be told
     */
    public static function grid_nick($nick = null, $conf = null)
    {
        $grids = self::grids(self::profile($conf));
        $nick = $nick ?: self::requested_nick();
        if ($nick !== '') {
            return isset($grids[$nick]) ? $nick : null;
        }

        return count($grids) === 1 ? (string) array_key_first($grids) : null;
    }

    /**
     * What the helpers need, from the config of a grid.
     *
     * @param  string|null $nick
     * @param  string|null $conf
     * @return array|null null when the grid is not found
     */
    public static function settings($nick = null, $conf = null)
    {
        $profile = self::profile($conf);
        $nick = self::grid_nick($nick, $conf);
        $grids = self::grids($profile);
        if ($nick === null || !isset($grids[$nick])) {
            return null;
        }

        $dir = $grids[$nick];
        // The files of the grid, Helpers.ini last: it only fills what the others leave out
        $config = OpenSim_Config::from_folder($dir, ['Helpers' => self::HELPERS_FILE]);
        $get = fn($section, $key) => $config->find($section, $key);

        return [
            'nick' => $nick,
            'dir' => $dir,
            'profile' => $profile,
            'hypergrid' => basename((string) $config->file('Robust')) === 'Robust.HG.ini',
            'config' => $config,
            'grid_name' => $get('GridInfoService', 'gridname') ?? ucfirst($nick),
            'login_uri' => rtrim((string) ($get('GridInfoService', 'login') ?? self::login_uri($config)), '/'),
            'web_url' => rtrim((string) ($get('Const', 'WebURL') ?? ''), '/'),
            // The search of the web: `search` of [GridInfoService] (what get_grid_info says) is the real one, SearchURL of
            // [LoginService] (sent to the viewers at login) stands for it when the grid has only that one
            'search_url' => $get('GridInfoService', 'search') ?? $get('LoginService', 'SearchURL'),
            'helpers' => $config->section('Helpers'),
        ];
    }

    /**
     * The URL of a setting that is an address.
     *
     * @return string|null
     */
    private static function login_uri($config)
    {
        $uri = $config->find('Hypergrid', 'GatekeeperURI');
        if ($uri === null) {
            $base = $config->find('Const', 'BaseURL');
            $port = $config->find('Const', 'PublicPort');
            $uri = $base !== null && $port !== null ? "$base:$port" : null;
        }

        return $uri;
    }

    /** The host name of an address, lowercase, without its port. */
    public static function host_of($url)
    {
        return strtolower((string) (parse_url((string) $url, PHP_URL_HOST) ?? ''));
    }

    /** The path of an address, without the slash that ends it (`/` for the root). */
    public static function path_of($url)
    {
        $path = (string) (parse_url((string) $url, PHP_URL_PATH) ?? '');
        // web_profile_url gives the place of the name of the avatar: /profile/[AGENT_NAME] is the route /profile
        $path = (string) preg_replace('#/?\[AGENT_NAME\].*$#i', '', $path);

        return '/' . trim($path, '/');
    }

    /**
     * The host (and the port, when there is one) of the web page that is being served: what the web server says
     * (HTTP_HOST, else SERVER_NAME); without a request (a command, a test) the host of the web address of the grid.
     *
     * @param  array $settings what settings() gave
     * @return array{host:string,port:?int}
     */
    public static function site($settings)
    {
        $authority = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        if ($authority === '') {
            return ['host' => self::host_of($settings['web_url'] ?? ''), 'port' => null];
        }
        $parts = parse_url('//' . $authority) ?: [];
        $port = $parts['port'] ?? (isset($_SERVER['HTTP_HOST']) && !str_contains($authority, ':') ? ($_SERVER['SERVER_PORT'] ?? null) : null);

        return ['host' => strtolower((string) ($parts['host'] ?? $authority)), 'port' => $port === null ? null : (int) $port];
    }

    /**
     * Whether an address is served by this site: its host (and its port, when it gives one) are the ones of the page that is
     * being served. An address with no host is a path of the site. A service the grid sends elsewhere (a third party, another
     * site of the grid) is not served here. The address of a simulator, or of the grid that asks, has nothing to do with it.
     *
     * @param  string                          $url
     * @param  array{host:string,port:?int}    $site
     */
    private static function is_local($url, $site)
    {
        $host = self::host_of($url);
        if ($host === '') {
            return true;
        }
        if ($host !== $site['host']) {
            return false;
        }
        $port = parse_url((string) $url, PHP_URL_PORT);

        return $port === null || $site['port'] === null || (int) $port === $site['port'];
    }

    /**
     * The paths the helpers answer and what answers each, from the addresses the grid gives to its services in the official
     * settings, those that are on the host of the page served only (see site()): a service the grid has set up elsewhere has
     * no ghost on this site.
     * A service the grid says nothing about is served at its default path, under the one of the economy (`/helpers`).
     *
     * @param  array $settings what settings() gave
     * @return array<string,string> path => script (`query.php`) or page (`@home`)
     */
    public static function routes($settings)
    {
        $config = $settings['config'];
        $site = self::site($settings);
        $get = fn($section, $key) => $config->find($section, $key);
        $routes = ['/' => '@home', '/health' => '@health'];

        // The economy: the viewers add the name of the script to the address the grid gives
        $economy = $get('GridInfoService', 'economy') ?? $get('Economy', 'economy');
        $base = $economy !== null && self::is_local($economy, $site) ? self::path_of($economy) : '/helpers';
        $base = $base === '/' ? '' : $base;
        if ($economy === null || self::is_local($economy, $site)) {
            $routes["$base/currency.php"] = 'currency.php';
            $routes["$base/landtool.php"] = 'landtool.php';
        }

        // The other services: the addresses the grid gives, or the default one. The name of a script of the helpers in an address
        // is the script that answers it, whatever the setting that gives it: a registration (DATA_SRV_) that points to
        // query.php is a mistake of the setting, which must not take the place of the search at that address; the service that
        // the setting is for is then served at its default address
        $add = function (array $urls, $target, $default) use (&$routes, $site) {
            if (!$urls) {
                $routes[$default] = $target;

                return;
            }
            $served = false;
            $misnamed = false;
            foreach ($urls as $url) {
                if (!self::is_local($url, $site)) {
                    continue;
                }
                $path = self::path_of($url);
                $named = basename($path);
                if ($target[0] !== '@' && $named !== $target && in_array($named, self::SCRIPTS, true)) {
                    $routes[$path] = $named;
                    $misnamed = true;

                    continue;
                }
                $routes[$path] = $target;
                $served = true;
            }
            if ($misnamed && !$served) {
                $routes[$default] = $target;
            }
        };
        $one = fn($section, $key) => ($value = $get($section, $key)) === null ? [] : [$value];
        $add($one('GridInfoService', 'welcome'), '@splash', '/welcome');
        $add($one('LoginService', 'DestinationGuide'), '@destination_guide', "$base/guide.php");
        $add($one('LoginService', 'MessageUrl'), 'motd.php', "$base/motd.php");
        $add($one('GridInfoService', 'web_profile_url'), '@profile', '/profile');
        // The web search: `search` of the grid info is the real one, SearchURL of the login service stands for it
        $add($one('GridInfoService', 'search') ?: $one('LoginService', 'SearchURL'), '@search', '/search');
        $add($one('Search', 'SearchURL'), 'query.php', "$base/query.php");
        $add(array_values(array_unique($config->keys('DataSnapshot', '/^DATA_SRV_/'))), 'register.php', "$base/register.php");
        $add($one('Messaging', 'OfflineMessageURL'), 'offline.php', "$base/offline.php");
        // Public information of the grid, served by default: what it is, and its state (`GridStatus` of [GridInfoService] can
        // point to the second one)
        $add($one('Helpers', 'GridInfoURL'), '@grid_info', "$base/grid_info");
        $add($one('Helpers', 'GridStatusURL'), '@grid_status', "$base/grid_status");
        foreach (['textgen.php', 'parser.php', 'eventsparser.php'] as $script) {
            $routes["$base/$script"] = $script;
        }
        $routes["$base/directory_info.php"] = '@directory_info';
        // The address of the helpers itself, the one the grid gives for them (its economy), is not a page, unless the grid says
        // it is the one of a page: a teapot, and not what is under it
        if ($base !== '') {
            $routes[$base] ??= '@teapot!';
        }

        return $routes;
    }

    /** The path the helpers are served under: the one of the economy of the grid (`/helpers` by default). */
    public static function base_path($settings)
    {
        $config = $settings['config'];
        $economy = $config->find('GridInfoService', 'economy') ?? $config->find('Economy', 'economy');

        return $economy !== null && self::is_local($economy, self::site($settings)) ? self::path_of($economy) : '/helpers';
    }

    /**
     * The database of a service, the way OpenSimulator chooses it: the connection string of the section of the service when
     * it has one, else the one of [DatabaseService]. Only the Robust config and Helpers.ini have services.
     *
     * @param  array  $settings what settings() gave
     * @param  string $service  the section (UserAccountService, GridUserService...)
     * @return array{hostname:?string,prefix:?string,user:?string,password:?string}
     */
    public static function connection_for($settings, $service)
    {
        return self::as_connection(\OpenSim\Data\Database::service($settings['config'], $service));
    }

    /**
     * The settings of a database from a connection of the engine.
     *
     * @param  \OpenSim\Config\ConnectionString|null $connection
     * @return array{hostname:?string,prefix:?string,user:?string,password:?string}
     */
    private static function as_connection($connection)
    {
        return [
            'hostname' => $connection?->source,
            'prefix' => $connection?->database,
            'user' => $connection?->user,
            'password' => $connection?->password,
        ];
    }

    /**
     * The constants the helper scripts expect: what the grid says, the helpers have a default for the rest.
     *
     * @param  array $settings what settings() gave
     * @return array<string,mixed> name => value
     */
    public static function constants($settings)
    {
        $config = $settings['config'];
        $get = fn($section, $key) => $config->find($section, $key);
        $helpers = $settings['helpers'];
        $economy = $get('GridInfoService', 'economy') ?? $get('Economy', 'economy');
        $module = strtolower((string) $get('Economy', 'economymodule'));
        // The module the helpers know has an addon of its name
        $provider = preg_match('/^[a-z0-9_-]+$/', $module) && is_file(dirname(__DIR__) . "/addons/$module.php") ? $module : null;
        $base = self::base_path($settings);

        $constants = [
            'OPENSIM_GRID_NAME' => $settings['grid_name'],
            'OPENSIM_LOGIN_URI' => $settings['login_uri'],
            'ROBUST_DB' => true,
            'OPENSIM_DB' => true,
            'OPENSIM_ROUTES' => self::routes($settings),
            'OPENSIM_HELPERS_PATH' => $base,
            // Whatever the theme folder of the grid holds replaces the templates and the assets of the helpers
            'OPENSIM_THEME_PATH' => self::in_grid_folder($helpers['ThemePath'] ?? 'theme', $settings['dir']),
        ];
        $given = [
            'OPENSIM_MOTD' => $get('LoginService', 'WelcomeMessage'),
            'OPENSIM_GUIDE_SOURCE' => self::in_grid_folder($helpers['GuideSource'] ?? null, $settings['dir']),
            'OPENSIM_GRID_LOGO_URL' => $helpers['LogoURL'] ?? null,
            'OPENSIM_MAIL_SENDER' => $helpers['MailSender'] ?? null,
            'HYPEVENTS_URL' => isset($helpers['HypEventsURL']) ? rtrim($helpers['HypEventsURL'], '/') : null,
            // The currency is what the economy module of the grid says. BetaGridLikeMoneyModule (the default of OpenSim) makes
            // everything free and needs no helper. With any other module, the helpers estimate the cost, relay the transfers and
            // the balance to the module and send the user to its page; the ones that have an addon (addons/gloebit.php) also
            // know its rates and settings
            'CURRENCY_PROVIDER' => $provider,
            'CURRENCY_USE_MONEYSERVER' => $module === '' || $module === 'betagridlikemoneymodule' ? null : true,
            'CURRENCY_SCRIPT_KEY' => self::script_key($settings),
            'OPENSIM_USE_UTC_TIME' => isset($helpers['UseUTCTime']) ? self::flag($helpers['UseUTCTime']) : null,
            'CURRENCY_HELPER_URL' =>
                $settings['web_url'] === '' ? null : $settings['web_url'] . ($economy === null ? $base : self::path_of($economy)) . '/currency.php',
        ];
        foreach ($given as $name => $value) {
            if ($value !== null) {
                $constants[$name] = $value;
            }
        }

        // The databases (see \OpenSim\Data\Database): Robust's, and the ones of the helpers (the search registry, the currency, the
        // offline messages) which are Robust's unless the config gives them another
        $robust = \OpenSim\Data\Database::Robust->connection($config);
        $databases = [
            'ROBUST' => $robust,
            'OPENSIM' => $robust,
            'SEARCH' => \OpenSim\Data\Database::Search->connection($config),
            'CURRENCY' => \OpenSim\Data\Database::Economy->connection($config) ?? $robust,
            'OFFLINE' => \OpenSim\Data\Database::Offline->connection($config),
        ];
        foreach ($databases as $prefix => $connection) {
            $connection = self::as_connection($connection);
            $constants["{$prefix}_DB_HOST"] = $connection['hostname'];
            $constants["{$prefix}_DB_NAME"] = $connection['prefix'];
            $constants["{$prefix}_DB_USER"] = $connection['user'];
            $constants["{$prefix}_DB_PASS"] = $connection['password'];
        }
        // Each service that has a database of its own: UserAccountService, GridUserService, PresenceService, GridService...
        $services = [];
        foreach ($config->all() as $section => $values) {
            if (isset($values['ConnectionString']) && $values['ConnectionString'] !== '' && $section !== 'DatabaseService') {
                $services[$section] = self::connection($values['ConnectionString']);
            }
        }
        $constants['OPENSIM_DB_SERVICES'] = $services;

        return $constants;
    }

    /**
     * The key that ties a quote of currency to its purchase (the confirm value is made with it), the script key of the
     * MoneyServer: `MoneyScriptAccessKey` of `[MoneyServer]` in its MoneyServer.ini, where it is written (by the setup, which
     * makes it at random: the server needs it too) and read, whatever name the helpers give it inside.
     *
     * @param  array $settings what settings() gave
     * @return string|null null when the grid has none (the helpers use their default then)
     */
    public static function script_key($settings)
    {
        $given = $settings['config']->find('MoneyServer', 'MoneyScriptAccessKey');

        return $given !== null && trim($given) !== '' ? trim($given) : null;
    }

    /**
     * A file name of Helpers.ini is relative to the folder of the grid (where Helpers.ini is); a URL or an absolute path
     * is kept.
     *
     * @param  string|null $source
     * @param  string|null $dir the folder of the grid
     * @return string|null
     */
    public static function in_grid_folder($source, $dir)
    {
        $source = is_string($source) ? trim($source) : '';
        if ($source === '' || empty($dir) || $source[0] === '/' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $source)) {
            return $source === '' ? null : $source;
        }

        return rtrim($dir, '/') . '/' . $source;
    }

    /**
     * Define the constants of constants(), those that are not defined already.
     *
     * @param  array $settings
     * @return void
     */
    public static function define_constants($settings)
    {
        foreach (self::constants($settings) as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }

    /**
     * The settings of a database from a connection string of OpenSimulator
     * ("Data Source=host;Database=name;User ID=user;Password=pass;Old Guids=true;").
     *
     * @param  string $string
     * @return array{hostname:?string,prefix:?string,user:?string,password:?string}
     */
    public static function connection($string)
    {
        $values = [];
        foreach (explode(';', $string) as $pair) {
            if (strpos($pair, '=') !== false) {
                [$key, $value] = explode('=', $pair, 2);
                $values[strtolower(trim($key))] = trim($value);
            }
        }

        return [
            'hostname' => $values['data source'] ?? ($values['server'] ?? null),
            'prefix' => $values['database'] ?? null,
            'user' => $values['user id'] ?? ($values['uid'] ?? null),
            'password' => $values['password'] ?? ($values['pwd'] ?? null),
        ];
    }

    private static function flag($value)
    {
        return !in_array(strtolower((string) $value), ['', '0', 'false', 'no', 'off'], true);
    }
}
