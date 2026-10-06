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
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */

class OpenSim_Helpers_GridConfig
{
    const DEFAULT_CONF = '/etc/opensim/opensim.conf';
    const DEFAULT_ETC = '/etc/opensim';

    /** The file of what has no official place. */
    const HELPERS_FILE = 'Helpers.ini';

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
        foreach (self::files($settings['dir']) as $file) {
            if (is_file($file) && !is_readable($file)) {
                return "$file is not readable by the web server user (every ini file of the grid belongs to the group of the web server, mode 640: chgrp www-data)";
            }
        }
        self::define_constants($settings);

        return null;
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
     * Read an ini file the way OpenSimulator writes them: sections, `key = value`, quotes around a
     * value optional, `;` and `#` comments. `${Const|Name}` is replaced by the value of [Const], the one of the file first,
     * else the one of the constants given (those of the Robust config, for the files of the simulators).
     *
     * @param  string                              $path
     * @param  array<string,string>                $constants
     * @return array<string,array<string,string>> section => key => value, empty when unreadable
     */
    public static function read_ini($path, $constants = [])
    {
        $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : false;
        if ($lines === false) {
            return [];
        }

        $ini = [];
        $section = '';
        foreach ($lines as $line) {
            $line = trim(str_replace("\r", '', $line));
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
                $section = trim($m[1]);
                $ini[$section] ??= [];
                continue;
            }
            if (!preg_match('/^([^=]+?)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }
            $value = trim($m[2]);
            if (preg_match('/^"(.*)"\s*(?:[;#].*)?$/', $value, $quoted)) {
                $value = $quoted[1];
            }
            // A key given twice in a section: the first is the one OpenSimulator keeps
            $ini[$section][trim($m[1])] ??= $value;
        }

        return self::expand($ini, $constants);
    }

    /**
     * Replace `${Const|Name}` by the value of Name in [Const], itself expanded (a few levels).
     *
     * @param  array<string,array<string,string>> $ini
     * @param  array<string,string>               $others the constants of another file, used for the ones this one has not
     * @return array<string,array<string,string>>
     */
    private static function expand($ini, $others = [])
    {
        $const = ($ini['Const'] ?? []) + $others;
        $replace = function ($value) use (&$const) {
            for ($i = 0; $i < 5 && strpos($value, '${Const|') !== false; $i++) {
                $value = preg_replace_callback('/\$\{Const\|([^}]+)\}/', fn($m) => $const[$m[1]] ?? $m[0], $value);
            }

            return $value;
        };
        foreach ($const as $key => $value) {
            $const[$key] = $replace($value);
        }
        foreach ($ini as $section => $values) {
            foreach ($values as $key => $value) {
                $ini[$section][$key] = $replace($value);
            }
        }

        return $ini;
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
        $ini = $conf === null ? [] : self::read_ini($conf);
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
     * The ini files of a grid the helpers read, in the order of their authority: Robust, OpenSim.ini, the includes, the
     * simulators, then Helpers.ini.
     *
     * @param  string $dir the folder of the grid
     * @return string[]
     */
    public static function files($dir)
    {
        $files = [];
        foreach (['Robust.HG.ini', 'Robust.ini', 'OpenSim.ini'] as $file) {
            if (is_file("$dir/$file")) {
                $files[] = "$dir/$file";
            }
        }
        // The modules are set in the files a simulator includes ([Gloebit]), and in the ones of the simulators
        foreach (array_merge(glob("$dir/config-include/*.ini") ?: [], glob("$dir/sims/*.ini") ?: []) as $file) {
            $files[] = $file;
        }
        $files[] = "$dir/" . self::HELPERS_FILE;

        return $files;
    }

    /**
     * What the files of a grid say, file by file, in the order of their authority.
     *
     * @param  string $dir
     * @return array<string,array<string,array<string,string>>> file => section => key => value
     */
    public static function sources($dir)
    {
        $sources = [];
        $constants = [];
        foreach (self::files($dir) as $file) {
            $ini = self::read_ini($file, $constants);
            if (str_contains(basename($file), 'Robust')) {
                // The constants of Robust serve the files that follow (the web address)
                $constants = $ini['Const'] ?? [];
            }
            $sources[$file] = $ini;
        }

        return $sources;
    }

    /**
     * The value of a setting: the first file that has it. Helpers.ini is the last one.
     *
     * @param  array  $sources what sources() gave
     * @param  string $section
     * @param  string $key
     * @return string|null
     */
    public static function value($sources, $section, $key)
    {
        foreach ($sources as $ini) {
            if (isset($ini[$section][$key]) && $ini[$section][$key] !== '') {
                return $ini[$section][$key];
            }
        }

        return null;
    }

    /**
     * Every value of the settings of a section whose key matches, in every file (DATA_SRV_* of [DataSnapshot]).
     *
     * @param  array  $sources
     * @param  string $section
     * @param  string $pattern a regular expression for the keys
     * @return string[]
     */
    public static function values($sources, $section, $pattern)
    {
        $values = [];
        foreach ($sources as $ini) {
            foreach ($ini[$section] ?? [] as $key => $value) {
                if ($value !== '' && preg_match($pattern, $key)) {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
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
        $sources = self::sources($dir);
        $get = fn($section, $key) => self::value($sources, $section, $key);
        $helpers = [];
        foreach ($sources as $ini) {
            $helpers += $ini['Helpers'] ?? [];
        }

        return [
            'nick' => $nick,
            'dir' => $dir,
            'profile' => $profile,
            'hypergrid' => isset($sources["$dir/Robust.HG.ini"]),
            'sources' => $sources,
            'grid_name' => $get('GridInfoService', 'gridname') ?? ucfirst($nick),
            'login_uri' => rtrim((string) ($get('GridInfoService', 'login') ?? self::login_uri($sources)), '/'),
            'web_url' => rtrim((string) ($get('Const', 'WebURL') ?? ''), '/'),
            'helpers' => $helpers,
        ];
    }

    /**
     * The URL of a setting that is an address.
     *
     * @return string|null
     */
    private static function login_uri($sources)
    {
        $uri = self::value($sources, 'Hypergrid', 'GatekeeperURI');
        if ($uri === null) {
            $base = self::value($sources, 'Const', 'BaseURL');
            $port = self::value($sources, 'Const', 'PublicPort');
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
        $sources = $settings['sources'];
        $site = self::site($settings);
        $get = fn($section, $key) => self::value($sources, $section, $key);
        $routes = ['/' => '@home', '/health' => '@health'];

        // The economy: the viewers add the name of the script to the address the grid gives
        $economy = $get('GridInfoService', 'economy') ?? $get('Economy', 'economy');
        $base = $economy !== null && self::is_local($economy, $site) ? self::path_of($economy) : '/helpers';
        $base = $base === '/' ? '' : $base;
        if ($economy === null || self::is_local($economy, $site)) {
            $routes["$base/currency.php"] = 'currency.php';
            $routes["$base/landtool.php"] = 'landtool.php';
        }

        // The other services: the addresses the grid gives, or the default one
        $add = function (array $urls, $target, $default) use (&$routes, $site) {
            if (!$urls) {
                $routes[$default] = $target;

                return;
            }
            foreach ($urls as $url) {
                if (self::is_local($url, $site)) {
                    $routes[self::path_of($url)] = $target;
                }
            }
        };
        $one = fn($section, $key) => ($value = $get($section, $key)) === null ? [] : [$value];
        $add($one('GridInfoService', 'welcome'), '@splash', '/welcome');
        $add($one('LoginService', 'DestinationGuide'), '@destination_guide', "$base/guide.php");
        $add($one('LoginService', 'MessageUrl'), 'motd.php', "$base/motd.php");
        $add($one('GridInfoService', 'web_profile_url'), '@profile', '/profile');
        $add($one('Search', 'SearchURL'), 'query.php', "$base/query.php");
        $add(self::values($sources, 'DataSnapshot', '/^DATA_SRV_/'), 'register.php', "$base/register.php");
        $add($one('Messaging', 'OfflineMessageURL'), 'offline.php', "$base/offline.php");
        foreach (['textgen.php', 'parser.php', 'eventsparser.php'] as $script) {
            $routes["$base/$script"] = $script;
        }
        $routes["$base/directory_info.php"] = '@directory_info';

        return $routes;
    }

    /** The path the helpers are served under: the one of the economy of the grid (`/helpers` by default). */
    public static function base_path($settings)
    {
        $sources = $settings['sources'];
        $economy = self::value($sources, 'GridInfoService', 'economy') ?? self::value($sources, 'Economy', 'economy');

        return $economy !== null && self::is_local($economy, self::site($settings)) ? self::path_of($economy) : '/helpers';
    }

    /**
     * The database of a service, the way OpenSimulator chooses it: the connection string of the section of the service when
     * it has one, else the one of [DatabaseService]. Only the Robust config and Helpers.ini have services.
     *
     * @param  array  $settings what settings() gave
     * @param  string $service  the section (UserAccountService, GridUserService, OfflineIMService...)
     * @return array{hostname:?string,prefix:?string,user:?string,password:?string}
     */
    public static function connection_for($settings, $service)
    {
        $sources = $settings['sources'];
        $string = self::value($sources, $service, 'ConnectionString') ?? self::value($sources, 'DatabaseService', 'ConnectionString');

        return self::connection((string) $string);
    }

    /**
     * The constants the helper scripts expect: what the grid says, the helpers have a default for the rest.
     *
     * @param  array $settings what settings() gave
     * @return array<string,mixed> name => value
     */
    public static function constants($settings)
    {
        $sources = $settings['sources'];
        $get = fn($section, $key) => self::value($sources, $section, $key);
        $helpers = $settings['helpers'];
        $economy = $get('GridInfoService', 'economy') ?? $get('Economy', 'economy');
        $module = (string) $get('Economy', 'economymodule');
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
            // The currency is what the economy module of the grid says: the modules we know are Gloebit and
            // BetaGridLikeMoneyModule (everything is free), a grid with another one handles its currency by hand
            'CURRENCY_PROVIDER' => strtolower($module) === 'gloebit' ? 'gloebit' : null,
            // Gloebit tells whether it is the sandbox or the real one
            'GLOEBIT_SANDBOX' => $get('Gloebit', 'GLBEnvironment') === null ? null : strtolower($get('Gloebit', 'GLBEnvironment')) === 'sandbox',
            'CURRENCY_HELPER_URL' =>
                $settings['web_url'] === '' ? null : $settings['web_url'] . ($economy === null ? $base : self::path_of($economy)) . '/currency.php',
        ];
        foreach ($given as $name => $value) {
            if ($value !== null) {
                $constants[$name] = $value;
            }
        }

        // The databases: Robust's, each service that has its own, and the ones of the helpers (the search registry, the
        // currency) which are Robust's unless Helpers.ini gives them another
        $default = self::connection_for($settings, 'DatabaseService');
        $databases = [
            'ROBUST' => $default,
            'OPENSIM' => $default,
            'SEARCH' => isset($helpers['SearchConnectionString']) ? self::connection($helpers['SearchConnectionString']) : $default,
            'CURRENCY' => $default,
            'OFFLINE' => self::connection_for($settings, 'OfflineIMService'),
        ];
        foreach ($databases as $prefix => $connection) {
            $constants["{$prefix}_DB_HOST"] = $connection['hostname'];
            $constants["{$prefix}_DB_NAME"] = $connection['prefix'];
            $constants["{$prefix}_DB_USER"] = $connection['user'];
            $constants["{$prefix}_DB_PASS"] = $connection['password'];
        }
        // Each service that has a database of its own: UserAccountService, GridUserService, PresenceService, GridService...
        $services = [];
        foreach ($sources as $ini) {
            foreach ($ini as $section => $values) {
                if (isset($values['ConnectionString']) && $values['ConnectionString'] !== '' && $section !== 'DatabaseService') {
                    $services[$section] ??= self::connection($values['ConnectionString']);
                }
            }
        }
        $constants['OPENSIM_DB_SERVICES'] = $services;

        return $constants;
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
