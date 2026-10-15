<?php
/**
 * classes/class-grid-config.php: the config of a grid as OpenSimulator has it (Robust, OpenSim.ini, the simulators), the
 * settings Helpers.ini adds, the databases of each service, the routes the grid's own addresses make, and the constants.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/classes/class-grid-config.php';

/**
 * A setup in a temporary tree: a profile, one grid.
 *
 * @param string $helpers The content of Helpers.ini of the grid, none when empty.
 * @param bool $robust Whether the grid has a Robust config.
 * @param array<string,string> $files Other files of the folder of the grid (OpenSim.ini, sims/one.ini), by name.
 * @return array{0:string,1:string} The opensim.conf, the grid folder.
 */
function grid_config_tree(string $helpers = '', bool $robust = true, array $files = []): array
{
    $root = sys_get_temp_dir() . '/helpers-grid-' . bin2hex(random_bytes(4));
    $grid = "$root/etc/grids/Alpha";
    mkdir($grid, 0o755, true);
    file_put_contents(
        "$root/opensim.conf",
        "[Defaults]\nDefaultProfile = 0.9.3.0\nSystemUser = opensim\n\n[0.9.3.0]\nEtcRoot = $root/etc\n",
    );
    if ($robust) {
        file_put_contents(
            "$grid/Robust.HG.ini",
            <<<'INI'
            ; Robust of the grid
            [Const]
                BaseHostname = "play.example.org"
                BaseURL = "http://${Const|BaseHostname}"
                WebURL = "https://${Const|BaseHostname}"
                PublicPort = 8002
            [DatabaseService]
                ConnectionString = "Data Source=localhost;Database=alpha_robust;User ID=opensim;Password=s3cret;Old Guids=true;"
            [Hypergrid]
                GatekeeperURI = "${Const|BaseURL}:${Const|PublicPort}"
            [GridInfoService]
                gridname = "Alpha World"
            [LoginService]
                WelcomeMessage = "Welcome to Alpha, <USERNAME>!"
            INI
            ,
        );
    }
    if ($helpers !== '') {
        file_put_contents("$grid/Helpers.ini", $helpers);
    }
    foreach ($files as $name => $content) {
        is_dir(dirname("$grid/$name")) || mkdir(dirname("$grid/$name"), 0o755, true);
        file_put_contents("$grid/$name", $content);
    }

    return ["$root/opensim.conf", $grid];
}

/** The constants a grid gives, from the tree of a test. */
function grid_constants(string $helpers = '', bool $robust = true, array $files = []): array
{
    [$conf] = grid_config_tree($helpers, $robust, $files);

    return OpenSim_Helpers_GridConfig::constants(OpenSim_Helpers_GridConfig::settings(null, $conf));
}

describe('Finding the grid', function () {
    test('expands the constants of Robust in the files of the simulators', function () {
        [$conf] = grid_config_tree('', true, ['sims/one.ini' => "[Messaging]\nOfflineMessageURL = \"\${Const|WebURL}/helpers/offline.php\"\n"]);

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);

        expect($settings['config']->find('Messaging', 'OfflineMessageURL'))->toBe(
            'https://play.example.org/helpers/offline.php',
        );
    });

    test('gives the default profile and the grids it has', function () {
        [$conf] = grid_config_tree();

        $profile = OpenSim_Helpers_GridConfig::profile($conf);

        expect($profile['SystemUser'])
            ->toBe('opensim')
            ->and(array_keys(OpenSim_Helpers_GridConfig::grids($profile)))
            ->toBe(['Alpha'])
            ->and(OpenSim_Helpers_GridConfig::grid_nick(null, $conf))
            ->toBe('Alpha')
            ->and(OpenSim_Helpers_GridConfig::grid_nick('Beta', $conf))
            ->toBeNull();
    });

    test('finds no grid, or none chosen', function () {
        [$conf, $grid] = grid_config_tree();
        expect(OpenSim_Helpers_GridConfig::settings('Nowhere', $conf))->toBeNull();

        mkdir(dirname($grid) . '/Beta');
        copy("$grid/Robust.HG.ini", dirname($grid) . '/Beta/Robust.ini');

        expect(OpenSim_Helpers_GridConfig::grid_nick(null, $conf))
            ->toBeNull()
            ->and(OpenSim_Helpers_GridConfig::grid_nick('Beta', $conf))
            ->toBe('Beta');
    });
});

describe('settings', function () {
    test('are the official ones: the name of the grid, its address, its web site', function () {
        [$conf] = grid_config_tree();

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);

        expect($settings['grid_name'])
            ->toBe('Alpha World')
            ->and($settings['login_uri'])
            ->toBe('http://play.example.org:8002')
            ->and($settings['web_url'])
            ->toBe('https://play.example.org')
            ->and($settings['hypergrid'])
            ->toBeTrue();
    });

    test('have the web search of the grid: search of the grid info is the real one, SearchURL of the login service stands for it', function () {
        $search = function (string $robust) {
            [$conf, $grid] = grid_config_tree();
            file_put_contents("$grid/Robust.HG.ini", (string) file_get_contents("$grid/Robust.HG.ini") . $robust);

            return OpenSim_Helpers_GridConfig::settings(null, $conf)['search_url'];
        };

        expect($search("\n[GridInfoService]\nsearch = \"https://play.example.org/search\"\n"))
            ->toBe('https://play.example.org/search')
            ->and($search("\n[LoginService]\nSearchURL = \"https://play.example.org/find\"\n"))
            ->toBe('https://play.example.org/find')
            ->and($search("\n[LoginService]\nSearchURL = \"https://play.example.org/find\"\n[GridInfoService]\nsearch = \"https://play.example.org/search\"\n"))
            ->toBe('https://play.example.org/search')
            ->and($search(''))
            ->toBeNull();
    });

    test('are the ones of the official files first: Helpers.ini only fills what they leave out', function () {
        [$conf] = grid_config_tree(
            <<<'INI'
            [GridInfoService]
            gridname = "Another name"
            [Helpers]
            GuideSource = "destinations.txt"
            INI
            ,
        );

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);

        expect($settings['grid_name'])->toBe('Alpha World')->and($settings['helpers']['GuideSource'])->toBe('destinations.txt');
    });

    test('can all be in Helpers.ini, in the same format, for a web host that has no OpenSimulator', function () {
        [$conf] = grid_config_tree(
            <<<'INI'
            [Const]
            WebURL = "https://play.example.org"
            [GridInfoService]
            gridname = "Alpha"
            login = "http://play.example.org:8002/"
            [LoginService]
            WelcomeMessage = "Hi"
            [DatabaseService]
            ConnectionString = "Data Source=db;Database=alpha;User ID=helpers;Password=pw;"
            INI
            ,
            false,
        );

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);
        $constants = OpenSim_Helpers_GridConfig::constants($settings);

        expect($settings['grid_name'])
            ->toBe('Alpha')
            ->and($settings['login_uri'])
            ->toBe('http://play.example.org:8002')
            ->and($settings['hypergrid'])
            ->toBeFalse()
            ->and($constants['OPENSIM_MOTD'])
            ->toBe('Hi')
            ->and($constants['OPENSIM_DB_USER'])
            ->toBe('helpers');
    });

    test('are the ones the grid says, the defaults are for the helpers', function () {
        $constants = grid_constants();

        expect($constants)
            ->toHaveKeys(['OPENSIM_GRID_NAME', 'OPENSIM_LOGIN_URI', 'OPENSIM_DB_HOST', 'SEARCH_DB_NAME', 'CURRENCY_DB_USER', 'OFFLINE_DB_PASS', 'ROBUST_DB_HOST', 'CURRENCY_HELPER_URL'])
            ->not->toHaveKeys(['OPENSIM_USE_UTC_TIME', 'CURRENCY_MONEY_TBL', 'CURRENCY_RATE', 'HYPEVENTS_URL', 'OPENSIM_GUIDE_SOURCE'])
            ->and($constants['OPENSIM_MOTD'])
            ->toBe('Welcome to Alpha, <USERNAME>!')
            ->and($constants['CURRENCY_HELPER_URL'])
            ->toBe('https://play.example.org/helpers/currency.php');
    });

    test('have a place of their own in [Helpers] when the official files have none, a file name being in the folder of the grid', function () {
        [$conf, $grid] = grid_config_tree(
            <<<'INI'
            [Helpers]
            GuideSource = "destinations.txt"
            HypEventsURL = "https://2do.directory/events/"
            ThemePath = "/srv/theme"
            LogoURL = "https://play.example.org/logo.png"
            MailSender = "no-reply@play.example.org"
            INI
            ,
        );

        $constants = OpenSim_Helpers_GridConfig::constants(OpenSim_Helpers_GridConfig::settings(null, $conf));

        expect($constants['OPENSIM_GUIDE_SOURCE'])
            ->toBe("$grid/destinations.txt")
            ->and($constants['HYPEVENTS_URL'])
            ->toBe('https://2do.directory/events')
            ->and($constants['OPENSIM_THEME_PATH'])
            ->toBe('/srv/theme')
            ->and($constants['OPENSIM_GRID_LOGO_URL'])
            ->toBe('https://play.example.org/logo.png')
            ->and($constants['OPENSIM_MAIL_SENDER'])
            ->toBe('no-reply@play.example.org');
    });

    test('have a guide source that is a path or a URL kept as it is, and a theme folder in the grid folder by default', function () {
        $guide = fn(string $source) => grid_constants("[Helpers]\nGuideSource = \"$source\"\n")['OPENSIM_GUIDE_SOURCE'];

        expect($guide('/srv/guide/places.txt'))
            ->toBe('/srv/guide/places.txt')
            ->and($guide('https://example.org/places.txt'))
            ->toBe('https://example.org/places.txt');

        [$conf, $grid] = grid_config_tree();
        expect(OpenSim_Helpers_GridConfig::constants(OpenSim_Helpers_GridConfig::settings(null, $conf))['OPENSIM_THEME_PATH'])->toBe("$grid/theme");
    });

    test('tell the currency from the economy module of OpenSim.ini: the helpers relay to any module but the beta one', function () {
        $module = fn(string $name) => grid_constants('', true, ['OpenSim.ini' => "[Economy]\neconomymodule = $name\n"]);

        // Gloebit has an addon (its rates, its sandbox), the other modules the grid may use are relayed as well
        expect($module('Gloebit'))
            ->toMatchArray(['CURRENCY_PROVIDER' => 'gloebit', 'CURRENCY_USE_MONEYSERVER' => true])
            ->and($module('DTLNSLMoneyModule'))
            ->toMatchArray(['CURRENCY_USE_MONEYSERVER' => true])
            ->not->toHaveKey('CURRENCY_PROVIDER')
            // The beta module, and the grids without module, make everything free: no currency to handle
            ->and($module('BetaGridLikeMoneyModule'))
            ->not->toHaveKeys(['CURRENCY_PROVIDER', 'CURRENCY_USE_MONEYSERVER'])
            ->and(grid_constants())
            ->not->toHaveKeys(['CURRENCY_PROVIDER', 'CURRENCY_USE_MONEYSERVER'])
            // The rates are not settings of the grid: the module (its addon) and the helpers have their own
            ->and(grid_constants())
            ->not->toHaveKeys(['CURRENCY_RATE', 'CURRENCY_RATE_PER', 'GLOEBIT_SANDBOX']);
    });

    test('have the script key of the MoneyServer, where it has it: [MoneyServer] of its MoneyServer.ini', function () {
        $key = fn(string $helpers = '', array $files = []) => grid_constants($helpers, true, $files)['CURRENCY_SCRIPT_KEY'] ?? null;

        expect($key('', ['MoneyServer.ini' => "[MoneyServer]\nMoneyScriptAccessKey = \"from-money-server\"\n"]))
            ->toBe('from-money-server')
            // Nothing is computed, nor has another name or place: a grid without the setting has none, and Helpers.ini has no such one
            ->and($key())
            ->toBeNull()
            ->and($key("[Helpers]\nScriptKey = \"from-helpers\"\n"))
            ->toBeNull();
    });

    test('keep UTC time unless Helpers.ini says otherwise', function () {
        expect(grid_constants("[Helpers]\nUseUTCTime = false\n")['OPENSIM_USE_UTC_TIME'])->toBeFalse();
    });
});

describe('addon of a module', function () {
    /** Load the Gloebit addon against the config of a test grid, in its own process: it defines constants. */
    function gloebit_addon(string $ini): array
    {
        [$conf] = grid_config_tree('', true, [
            'OpenSim.ini' => "[Economy]\neconomymodule = Gloebit\n[Architecture]\nInclude-Gloebit = \"config-include/Gloebit.ini\"\n",
            'config-include/Gloebit.ini' => $ini,
        ]);
        $root = dirname(__DIR__, 2);
        $script =
            "putenv('OPENSIM_CONF=$conf'); require '$root/vendor/autoload.php'; require '$root/classes/class-grid-config.php'; OpenSim_Helpers_GridConfig::load();" .
            "define('CURRENCY_PROVIDER', 'gloebit'); require '$root/addons/gloebit.php';" .
            'echo json_encode([GLOEBIT_SANDBOX, GLOEBIT_CONVERSION_THRESHOLD]);';
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = (string) stream_get_contents($pipes[1]);
        proc_close($process);

        return json_decode($out, true) ?? [];
    }

    test('Gloebit is the sandbox or the real one as its own settings say, in the file OpenSim.ini includes', function () {
        expect(gloebit_addon("[Gloebit]\nGLBEnvironment = sandbox\n")[0])
            ->toBeTrue()
            ->and(gloebit_addon("[Gloebit]\nGLBEnvironment = production\n")[0])
            ->toBeFalse()
            ->and(gloebit_addon("[Gloebit]\n")[0])
            ->toBeFalse();
    });
});

describe('databases', function () {
    test('are Robust\'s, one for every service that says nothing', function () {
        $constants = grid_constants();

        expect($constants['OPENSIM_DB_NAME'])
            ->toBe('alpha_robust')
            ->and($constants['SEARCH_DB_NAME'])
            ->toBe('alpha_robust')
            ->and($constants['OFFLINE_DB_NAME'])
            ->toBe('alpha_robust')
            ->and($constants['OPENSIM_DB_SERVICES'])
            ->toBe([]);
    });

    test('are the one a service has of its own, the way OpenSimulator chooses them', function () {
        [$conf] = grid_config_tree(
            <<<'INI'
            [UserAccountService]
            ConnectionString = "Data Source=db2;Database=accounts;User ID=acc;Password=pw2;"
            [Messaging]
            ConnectionString = "Data Source=db3;Database=offline;User ID=off;Password=pw3;"
            INI
            ,
        );

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);
        $constants = OpenSim_Helpers_GridConfig::constants($settings);

        expect(OpenSim_Helpers_GridConfig::connection_for($settings, 'UserAccountService')['prefix'])
            ->toBe('accounts')
            ->and(OpenSim_Helpers_GridConfig::connection_for($settings, 'GridUserService')['prefix'])
            ->toBe('alpha_robust')
            ->and($constants['OFFLINE_DB_HOST'])
            ->toBe('db3')
            ->and($constants['OFFLINE_DB_NAME'])
            ->toBe('offline')
            ->and($constants['OPENSIM_DB_NAME'])
            ->toBe('alpha_robust')
            ->and(array_keys($constants['OPENSIM_DB_SERVICES']))
            ->toBe(['UserAccountService', 'Messaging'])
            ->and($constants['OPENSIM_DB_SERVICES']['UserAccountService']['password'])
            ->toBe('pw2');
    });

    test('are their own for the search registry of the helpers, with its own name', function () {
        $constants = grid_constants(
            <<<'INI'
            [Helpers]
            SearchConnectionString = "Data Source=db4;Database=ossearch;User ID=search;Password=pw4;"
            INI
            ,
        );

        expect([$constants['SEARCH_DB_HOST'], $constants['SEARCH_DB_NAME'], $constants['SEARCH_DB_USER']])
            ->toBe(['db4', 'ossearch', 'search'])
            ->and($constants['CURRENCY_DB_NAME'])
            ->toBe('alpha_robust')
            ->and($constants['OPENSIM_DB_NAME'])
            ->toBe('alpha_robust');
    });

    test('are read from a connection string of .NET', function () {
        $string = 'Data Source=db;Database=x;User ID=u;Password=p;Old Guids=true;';

        expect(OpenSim_Helpers_GridConfig::connection($string))->toBe([
            'hostname' => 'db',
            'prefix' => 'x',
            'user' => 'u',
            'password' => 'p',
        ]);
    });
});

describe('routes', function () {
    $routes = fn(string $helpers = '', array $files = [], ?string $robust = null) => (function () use ($helpers, $files) {
        [$conf] = grid_config_tree($helpers, true, $files);

        return OpenSim_Helpers_GridConfig::routes(OpenSim_Helpers_GridConfig::settings(null, $conf));
    })();

    test('are the default ones, under /helpers, for a grid that says nothing', function () use ($routes) {
        expect($routes())
            ->toMatchArray([
                '/' => '@home',
                '/health' => '@health',
                '/welcome' => '@splash',
                '/profile' => '@profile',
                '/helpers/currency.php' => 'currency.php',
                '/helpers/landtool.php' => 'landtool.php',
                '/helpers/guide.php' => '@destination_guide',
                '/helpers/motd.php' => 'motd.php',
                '/helpers/query.php' => 'query.php',
                '/helpers/register.php' => 'register.php',
                '/helpers/offline.php' => 'offline.php',
                '/helpers/directory_info.php' => '@directory_info',
                '/helpers' => '@teapot!',
            ])
            ->not->toHaveKey('/helpers/health');
    });

    test('have the address of the helpers as a page only when the grid says it is the one of a page', function () use ($routes) {
        // The welcome page of the grid is at the address of the helpers: it is a page, not a teapot
        expect($routes("[GridInfoService]\nwelcome = \"https://play.example.org/helpers/\"\n"))
            ->toMatchArray(['/helpers' => '@splash'])
            ->and($routes("[GridInfoService]\nwelcome = \"https://play.example.org/welcome\"\n"))
            ->toMatchArray(['/helpers' => '@teapot!', '/welcome' => '@splash']);
    });

    test('have the public pages of the grid (what it is, its state) by default, and where Helpers.ini says', function () use ($routes) {
        expect($routes())
            ->toMatchArray(['/helpers/grid_info' => '@grid_info', '/helpers/grid_status' => '@grid_status']);

        $moved = $routes("[Helpers]\nGridStatusURL = \"https://play.example.org/status\"\nGridInfoURL = \"https://elsewhere.example.net/info\"\n");

        // The one that is on this site is where it says, the one that is on another site has no ghost here
        expect($moved)->toMatchArray(['/status' => '@grid_status'])->not->toHaveKeys(['/helpers/grid_status', '/helpers/grid_info']);
    });

    test('have the web search where the grid says it (search of the grid info, else SearchURL of the login service), and by default', function () use ($routes) {
        expect($routes())
            ->toMatchArray(['/search' => '@search'])
            ->and($routes("[GridInfoService]\nsearch = \"https://play.example.org/find\"\n"))
            ->toMatchArray(['/find' => '@search'])
            ->not->toHaveKey('/search')
            ->and($routes("[LoginService]\nSearchURL = \"https://play.example.org/where\"\n"))
            ->toMatchArray(['/where' => '@search']);
    });

    test('are the addresses the grid gives, on this site: the path, nothing else', function () use ($routes) {
        $table = $routes('', [
            'sims/one.ini' => "[Search]\nSearchURL = \"https://play.example.org/v3/query\"\n[DataSnapshot]\nDATA_SRV_Alpha = \"https://play.example.org/v3/register.php\"\n[Messaging]\nOfflineMessageURL = \"https://play.example.org/offline\"\n",
        ]);

        expect($table)
            ->toMatchArray(['/v3/query' => 'query.php', '/v3/register.php' => 'register.php', '/offline' => 'offline.php'])
            // The default is only the default: a service the grid put elsewhere on this site is not also at its default path
            ->not->toHaveKeys(['/helpers/query.php', '/helpers/register.php', '/helpers/offline.php']);
    });

    test('go by the name of the script an address has: a registration that points to query.php does not take the place of the search', function () use ($routes) {
        $table = $routes('', [
            'sims/one.ini' => "[Search]\nSearchURL = \"https://play.example.org/helpers/query.php\"\n[DataSnapshot]\nDATA_SRV_Alpha = \"https://play.example.org/helpers/query.php\"\n",
        ]);

        // The search keeps its address, the registration, which has none that is right, is served at the default one
        expect($table)->toMatchArray(['/helpers/query.php' => 'query.php', '/helpers/register.php' => 'register.php']);

        // On another site it is not ours to serve, and nothing is served in its place
        $elsewhere = $routes('', ['sims/one.ini' => "[DataSnapshot]\nDATA_SRV_2do = \"https://2do.directory/helpers/register.php\"\n"]);

        expect($elsewhere)->not->toHaveKey('/helpers/register.php');
    });

    test('are none for a service the grid has set up on another site: no ghost service on this one', function () use ($routes) {
        $table = $routes('', [
            'sims/one.ini' => "[Search]\nSearchURL = \"https://2do.directory/helpers/query.php\"\n[DataSnapshot]\nDATA_SRV_Alpha = \"https://play.example.org/helpers/register.php\"\nDATA_SRV_2do = \"https://2do.directory/helpers/register.php\"\n",
            'OpenSim.ini' => "[Messaging]\nOfflineMessageURL = \"https://elsewhere.example.net/Offline.php\"\n",
        ]);

        expect($table)
            ->toHaveKey('/helpers/register.php')
            ->not->toHaveKey('/helpers/query.php')
            ->not->toHaveKey('/helpers/offline.php');
    });

    test('follow the address of the economy for currency.php and landtool.php, which only have their name fixed', function () use ($routes) {
        $table = $routes("[GridInfoService]\neconomy = \"https://play.example.org/currency/\"\n");

        expect($table)
            ->toMatchArray(['/currency/currency.php' => 'currency.php', '/currency/landtool.php' => 'landtool.php'])
            ->not->toHaveKey('/helpers/currency.php');

        $elsewhere = $routes("[GridInfoService]\neconomy = \"https://money.example.net/helpers/\"\n");
        expect($elsewhere)->not->toHaveKeys(['/helpers/currency.php', '/currency/currency.php']);
    });

    test('are the ones of the host of the page served, whatever the web address of the grid says', function () use ($routes) {
        $files = ['sims/one.ini' => "[Search]\nSearchURL = \"https://play.example.org/v3/query\"\n[DataSnapshot]\nDATA_SRV_Alpha = \"http://play.example.org:8090/register.php\"\n"];
        $served = function (string $host, ?string $port = null) use ($routes, $files) {
            $before = [$_SERVER['HTTP_HOST'] ?? null, $_SERVER['SERVER_PORT'] ?? null];
            $_SERVER['HTTP_HOST'] = $host;
            $port === null ? null : ($_SERVER['SERVER_PORT'] = $port);
            $table = $routes('', $files);
            [$_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT']] = $before;
            unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT']);

            return $table;
        };

        // On the host of the addresses, and on its port when an address gives one
        expect($served('play.example.org', '443'))
            ->toHaveKey('/v3/query')
            ->not->toHaveKey('/register.php')
            ->and($served('play.example.org:8090'))
            ->toHaveKey('/register.php')
            ->and($served('PLAY.example.org:8090'))
            ->toHaveKey('/v3/query')
            // Another host serving the same files (another site, a test server): nothing of what the grid sent to play.example.org
            ->and($served('127.0.0.1:8090'))
            ->not->toHaveKeys(['/v3/query', '/register.php', '/helpers/query.php', '/helpers/register.php'])
            // The address of the web site of the grid is not what counts
            ->and($served('other.example.net'))
            ->toHaveKey('/helpers/currency.php');
    });

    test('are the profiles where web_profile_url says, the place of the name left out', function () use ($routes) {
        expect($routes("[GridInfoService]\nweb_profile_url = \"https://play.example.org/members/[AGENT_NAME]\"\n"))
            ->toHaveKey('/members')
            ->not->toHaveKey('/profile')
            ->and($routes("[GridInfoService]\nweb_profile_url = \"https://play.example.org/profile?name=[AGENT_NAME]\"\n"))
            ->toHaveKey('/profile')
            ->and($routes("[GridInfoService]\nweb_profile_url = \"https://other.example.net/profile/[AGENT_NAME]\"\n"))
            ->not->toHaveKey('/profile')
            // Nothing said: the default
            ->and($routes())
            ->toHaveKey('/profile');
    });

    test('follow the pages the login service and the grid info give', function () use ($routes) {
        $table = $routes("[LoginService]\nDestinationGuide = \"https://play.example.org/guide\"\nMessageUrl = \"https://other.example.org/motd.php\"\n[GridInfoService]\nwelcome = \"https://play.example.org/splash\"\n");

        expect($table)
            ->toMatchArray(['/guide' => '@destination_guide', '/splash' => '@splash'])
            ->not->toHaveKeys(['/helpers/guide.php', '/helpers/motd.php', '/welcome']);
    });
});

describe('config for the engine', function () {
    test('is made from the constants of includes/config.php: Robust, the services that have their own, the search and the offline messages', function () {
        $config = OpenSim_Helpers_GridConfig::config_from_constants([
            'OPENSIM_DB_HOST' => 'localhost', 'OPENSIM_DB_NAME' => 'robust', 'OPENSIM_DB_USER' => 'os', 'OPENSIM_DB_PASS' => 'pw',
            'OPENSIM_DB_SERVICES' => ['UserAccountService' => ['hostname' => 'db2', 'prefix' => 'accounts', 'user' => 'acc', 'password' => 'pw2']],
            'SEARCH_DB_HOST' => 'localhost', 'SEARCH_DB_NAME' => 'search', 'SEARCH_DB_USER' => 'os', 'SEARCH_DB_PASS' => 'pw',
            'OFFLINE_DB_HOST' => 'localhost', 'OFFLINE_DB_NAME' => 'robust', 'OFFLINE_DB_USER' => 'os', 'OFFLINE_DB_PASS' => 'pw',
        ]);

        expect(\OpenSim\Data\Database::Robust->connection($config)->database)->toBe('robust')
            ->and(\OpenSim\Data\Database::service($config, 'UserAccountService')->database)->toBe('accounts')
            ->and(\OpenSim\Data\Database::service($config, 'UserAccountService')->host)->toBe('db2')
            ->and(\OpenSim\Data\Database::Search->connection($config)->database)->toBe('search')
            ->and(\OpenSim\Data\Database::Offline->connection($config)->database)->toBe('robust');
    });

    test('has nothing without a database', function () {
        $config = OpenSim_Helpers_GridConfig::config_from_constants([]);

        expect(\OpenSim\Data\Database::Robust->connection($config))->toBeNull();
    });
});
