<?php
/**
 * classes/class-grid-config.php: the config of a grid as OpenSimulator has it (Robust, OpenSim.ini, the simulators), the
 * settings Helpers.ini adds, the databases of each service, the routes the grid's own addresses make, and the constants.
 */

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

describe('Reading the files', function () {
    test('is as OpenSimulator reads them: sections, quotes, constants expanded', function () {
        [, $grid] = grid_config_tree();

        $ini = OpenSim_Helpers_GridConfig::read_ini("$grid/Robust.HG.ini");

        expect($ini['Const']['BaseURL'])
            ->toBe('http://play.example.org')
            ->and($ini['Hypergrid']['GatekeeperURI'])
            ->toBe('http://play.example.org:8002')
            ->and($ini['GridInfoService']['gridname'])
            ->toBe('Alpha World')
            ->and(OpenSim_Helpers_GridConfig::read_ini('/nonexistent.ini'))
            ->toBe([]);
    });

    test('keeps the first of a key given twice in a section, as OpenSimulator does', function () {
        [, $grid] = grid_config_tree('', true, ['twice.ini' => "[DatabaseService]\nConnectionString = \"first\"\nConnectionString = \"second\"\n"]);

        expect(OpenSim_Helpers_GridConfig::read_ini("$grid/twice.ini")['DatabaseService']['ConnectionString'])->toBe('first');
    });

    test('expands the constants of Robust in the files of the simulators', function () {
        [$conf] = grid_config_tree('', true, ['sims/one.ini' => "[Messaging]\nOfflineMessageURL = \"\${Const|WebURL}/helpers/offline.php\"\n"]);

        $settings = OpenSim_Helpers_GridConfig::settings(null, $conf);

        expect(OpenSim_Helpers_GridConfig::value($settings['sources'], 'Messaging', 'OfflineMessageURL'))->toBe(
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

describe('The settings', function () {
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
            CurrencyRate = 4
            CurrencyRatePer = 1000
            CurrencyScriptKey = "secret"
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
            ->toBe('no-reply@play.example.org')
            ->and([$constants['CURRENCY_RATE'], $constants['CURRENCY_RATE_PER'], $constants['CURRENCY_SCRIPT_KEY']])
            ->toBe(['4', '1000', 'secret']);
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

    test('tell the currency from the economy module of OpenSim.ini', function () {
        $module = fn(string $name) => grid_constants('', true, ['OpenSim.ini' => "[Economy]\neconomymodule = $name\n"]);

        $gloebit = $module('Gloebit');
        $money = $module('DTLNSLMoneyModule');

        expect($gloebit['CURRENCY_PROVIDER'])
            ->toBe('gloebit')
            ->and($gloebit['CURRENCY_USE_MONEYSERVER'])
            ->toBeTrue()
            ->and($money)
            ->not->toHaveKey('CURRENCY_PROVIDER')
            ->and($money['CURRENCY_USE_MONEYSERVER'])
            ->toBeTrue()
            ->and($module('BetaGridLikeMoneyModule'))
            ->not->toHaveKey('CURRENCY_USE_MONEYSERVER');
    });
});

describe('The example Helpers.ini', function () {
    test('is a valid file that sets nothing, every setting being commented out', function () {
        $ini = OpenSim_Helpers_GridConfig::read_ini(dirname(__DIR__, 2) . '/includes/Helpers.example.ini');

        expect($ini)->toBe(['Helpers' => []]);
    });
});

describe('The databases', function () {
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
            [OfflineIMService]
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
            ->toBe(['UserAccountService', 'OfflineIMService'])
            ->and($constants['OPENSIM_DB_SERVICES']['UserAccountService']['password'])
            ->toBe('pw2');
    });

    test('are their own for the search registry and the currency of the helpers, with their own names', function () {
        $constants = grid_constants(
            <<<'INI'
            [Helpers]
            SearchConnectionString = "Data Source=db4;Database=ossearch;User ID=search;Password=pw4;"
            CurrencyConnectionString = "Data Source=db5;Database=money;User ID=money;Password=pw5;"
            INI
            ,
        );

        expect([$constants['SEARCH_DB_HOST'], $constants['SEARCH_DB_NAME'], $constants['SEARCH_DB_USER']])
            ->toBe(['db4', 'ossearch', 'search'])
            ->and([$constants['CURRENCY_DB_HOST'], $constants['CURRENCY_DB_NAME']])
            ->toBe(['db5', 'money'])
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

describe('The routes', function () {
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
                '/helpers/guide.php' => '@guide',
                '/helpers/motd.php' => 'motd.php',
                '/helpers/query.php' => 'query.php',
                '/helpers/register.php' => 'register.php',
                '/helpers/offline.php' => 'offline.php',
                '/helpers/directory_info.php' => '@directory_info',
            ]);
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

    test('follow the pages the login service and the grid info give', function () use ($routes) {
        $table = $routes("[LoginService]\nDestinationGuide = \"https://play.example.org/guide\"\nMessageUrl = \"https://other.example.org/motd.php\"\n[GridInfoService]\nwelcome = \"https://play.example.org/splash\"\n");

        expect($table)
            ->toMatchArray(['/guide' => '@guide', '/splash' => '@splash'])
            ->not->toHaveKeys(['/helpers/guide.php', '/helpers/motd.php', '/welcome']);
    });
});
