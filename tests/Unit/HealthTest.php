<?php
/**
 * Block health: what the helpers add to the checks of the engine (OpenSim_Health): their own scripts, at the addresses they are
 * served, what has no place in the ini files, and the page that shows it all.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';

describe('Block health', function () {
    /** What a grid says, as the settings give it: the config of the engine, its Robust file and Helpers.ini. */
    $settings = fn(array $sections, array $helpers = []) => [
        'config' => new OpenSim_Config(['Robust' => $sections, 'Helpers' => ['Helpers' => $helpers]], ['Robust' => '/etc/opensim/grids/alpha/Robust.HG.ini']),
        'web_url' => 'https://play.example.org',
        'helpers' => $helpers,
    ];
    $routes = [
        '/helpers/currency.php' => 'currency.php',
        '/helpers/landtool.php' => 'landtool.php',
        '/helpers/query.php' => 'query.php',
        '/helpers/register.php' => 'register.php',
        '/helpers/offline.php' => 'offline.php',
        '/helpers/textgen.php' => 'textgen.php',
    ];
    $by = fn(array $groups) => array_column(array_map(fn($g) => ['title' => trim($g['file'] . ' ' . $g['title']), 'rows' => $g['rows']], $groups), 'rows', 'title');
    $scripts = fn(array $groups) => array_values(array_filter($groups, fn($g) => $g['file'] === 'opensim-helpers'))[0]['rows'];

    test('has what the grid says first, then the files of the config with their sections, then what is the helpers', function () use ($settings, $routes) {
        $groups = OpenSim_Helpers_Block_Health::plan($settings(['GridInfoService' => ['login' => 'http://play.example.org:8002/']], ['HypEventsURL' => 'https://2do.directory/events']), 'http://play.example.org:8002/', [], $routes, '');

        expect(array_map(fn($g) => trim($g['file'] . ' ' . $g['title']), $groups))
            ->toBe(['get_grid_info', 'Robust.HG.ini [GridInfoService]', 'Robust.HG.ini [LoginService]', 'OpenSim.ini [Search]', 'OpenSim.ini [DataSnapshot]', 'OpenSim.ini [Messaging]', 'MoneyServer.ini [MoneyServer]', 'Helpers.ini [Helpers]', 'opensim-helpers'])
            ->and(array_column($groups[7]['rows'], 'label'))
            ->toBe(['GuideSource', 'HypEventsURL']);
    });

    test('has what the grid says without any config here, and the scripts of the helpers all the same', function () use ($routes, $scripts) {
        $groups = OpenSim_Helpers_Block_Health::plan(['web_url' => 'https://play.example.org'], 'http://play.example.org:8002/', ['gridname' => 'Alpha'], $routes, '');

        expect(array_column($groups, 'file'))
            ->toBe(['get_grid_info', 'Helpers.ini', 'opensim-helpers'])
            ->and(array_column($groups[0]['rows'], 'label'))
            ->toBe(['login', 'gridname']);
    });

    test('calls every script of the helpers where it is served, twice: with nothing, and with what it expects', function () use ($settings, $routes, $scripts) {
        $rows = $scripts(OpenSim_Helpers_Block_Health::plan($settings(['GridInfoService' => []]), null, [], $routes, ''));
        $tags = fn(string $label) => array_column(array_filter($rows, fn($row) => $row['label'] === $label), 'tag');

        expect(array_column($rows, 'label'))
            ->toBe(['query.php', 'query.php', 'register.php', 'register.php', 'offline.php', 'offline.php', 'currency.php', 'currency.php', 'landtool.php', 'landtool.php', 'textgen.php'])
            ->and(array_values($tags('query.php')))
            ->toBe(['without data', 'with valid XMLRPC request'])
            ->and(array_values($tags('register.php')))
            ->toBe(['without data', 'with valid query'])
            ->and(array_values($tags('offline.php')))
            ->toBe(['without data', 'with valid post data'])
            ->and(array_values($tags('currency.php')))
            ->toBe(['without data', 'with valid XMLRPC request'])
            // A page is asked to answer
            ->and(array_values(array_column(array_filter($rows, fn($row) => $row['label'] === 'textgen.php'), 'method')))
            ->toBe(['get']);

        $by = fn(string $label, string $tag) => array_values(array_filter($rows, fn($row) => $row['label'] === $label && ($row['tag'] ?? '') === $tag))[0];

        expect($by('currency.php', 'with valid XMLRPC request')['body'])
            ->toContain('getCurrencyQuote')
            ->and($by('landtool.php', 'with valid XMLRPC request')['body'])
            ->toContain('preflightBuyLandPrep')
            ->and($by('query.php', 'with valid XMLRPC request')['body'])
            ->toContain('dir_places_query')
            ->and($by('offline.php', 'with valid post data')['probe'])
            ->toBe('https://play.example.org/helpers/offline.php/RetrieveMessages/')
            ->and($by('currency.php', 'without data'))
            ->toMatchArray(['url' => 'https://play.example.org/helpers/currency.php', 'expect' => [400, 405], 'refuse' => true]);
    });

    test('does not ask twice what the grid already gives an address to', function () use ($settings, $routes, $scripts, $by) {
        $groups = OpenSim_Helpers_Block_Health::plan(
            $settings([
                'Search' => ['SearchURL' => 'https://play.example.org/helpers/query.php'],
                'Messaging' => ['OfflineMessageURL' => 'https://play.example.org/helpers/offline.php'],
            ]),
            null,
            [],
            $routes,
            '',
        );
        $rows = $by($groups);

        expect(array_column($scripts($groups), 'label'))
            ->not->toContain('query.php')
            ->not->toContain('offline.php')
            ->toContain('register.php')
            ->and(array_column($rows['OpenSim.ini [Search]'], 'label'))
            ->toBe(['SearchURL', 'SearchURL']);
    });

    test('has no row for a script the helpers do not serve here (the grid has its economy elsewhere)', function () use ($settings, $scripts) {
        $rows = $scripts(OpenSim_Helpers_Block_Health::plan($settings(['GridInfoService' => []]), null, [], ['/helpers/query.php' => 'query.php'], ''));

        expect(array_column($rows, 'label'))->toBe(['query.php', 'query.php']);
    });

    test('names the guide source as a setting: an address is asked, a folder is read, nothing is not shown', function () use ($settings, $routes, $by) {
        $row = fn(string $guide) => $by(OpenSim_Helpers_Block_Health::plan($settings([]), null, [], $routes, $guide))['Helpers.ini [Helpers]'][0];

        expect($row('https://play.example.org/guide.json'))
            ->toMatchArray(['label' => 'GuideSource', 'url' => 'https://play.example.org/guide.json'])
            ->and($row(''))
            ->toMatchArray(['label' => 'GuideSource', 'status' => '', 'level' => 'none'])
            ->and($row(__DIR__))
            ->toMatchArray(['label' => 'GuideSource', 'level' => 'ok']);
    });

    test('puts the sections of a file together, files in the order they come, the file named once', function () {
        $groups = OpenSim_Helpers_Block_Health::arrange([
            ['file' => 'Robust.ini', 'title' => '[GridInfoService]', 'rows' => []],
            ['file' => 'OpenSim.ini', 'title' => '[Search]', 'rows' => []],
            ['file' => 'Robust.ini', 'title' => '[DatabaseService]', 'rows' => []],
        ]);

        expect(array_map(fn($g) => $g['title'], $groups))
            ->toBe(['[GridInfoService]', '[DatabaseService]', '[Search]'])
            ->and(array_column($groups, 'first_of_file'))
            ->toBe([true, false, true]);
    });
});
