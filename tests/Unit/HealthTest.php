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
    /** What a grid says, as the settings give it. */
    $settings = fn(array $sections, array $helpers = []) => [
        'sources' => ['Robust.HG.ini' => $sections],
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
    $by = fn(array $groups) => array_column(array_map(fn($g) => ['title' => $g['title'], 'rows' => $g['rows']], $groups), 'rows', 'title');

    test('has the sections of the grid, then what is the helpers', function () use ($settings, $routes) {
        $groups = OpenSim_Helpers_Block_Health::plan($settings(['GridInfoService' => ['login' => 'http://play.example.org:8002/']], ['HypEventsURL' => 'https://2do.directory/events']), 'http://play.example.org:8002/', [], $routes, '');

        expect(array_column($groups, 'title'))
            ->toBe(['[GridInfoService]', '[LoginService]', '[Search]', '[DataSnapshot]', '[Messaging]', 'opensim-helpers', '[Helpers]', '[MoneyServer]'])
            ->and(array_column($groups[6]['rows'], 'label'))
            ->toBe(['GuideSource', 'HypEventsURL']);
    });

    test('asks every script of the helpers where it is served, a service with both ways', function () use ($settings, $routes, $by) {
        $rows = $by(OpenSim_Helpers_Block_Health::plan($settings(['GridInfoService' => []]), null, [], $routes, ''))['opensim-helpers'];
        $of = fn(string $label) => array_values(array_filter($rows, fn($row) => $row['label'] === $label));

        // The wrong method must refuse, the right one must answer, for the services that are called with XML-RPC
        expect(array_column($rows, 'label'))
            ->toBe(['query.php', 'query.php', 'register.php', 'offline.php', 'currency.php', 'currency.php', 'landtool.php', 'landtool.php', 'textgen.php'])
            ->and($of('currency.php')[0])
            ->toMatchArray(['url' => 'https://play.example.org/helpers/currency.php', 'method' => 'get', 'expect' => [400, 405]])
            ->and($of('currency.php')[1])
            ->toMatchArray(['url' => 'https://play.example.org/helpers/currency.php', 'method' => 'xmlrpc'])
            ->and($of('currency.php')[1]['body'])
            ->toContain('getCurrencyQuote')
            ->and($of('landtool.php')[1]['body'])
            ->toContain('preflightBuyLandPrep')
            // A script called without what it needs is a bad request
            ->and($of('register.php')[0])
            ->toMatchArray(['method' => 'get', 'expect' => [400, 405]])
            // A page is asked to answer
            ->and($of('textgen.php')[0])
            ->not->toHaveKey('expect')
            // No script is said twice: the grid has none of these addresses, so they are all here
            ->and(count($of('query.php')))
            ->toBe(2);
    });

    test('does not ask twice what the grid already gives an address to', function () use ($settings, $routes, $by) {
        $rows = $by(
            OpenSim_Helpers_Block_Health::plan(
                $settings([
                    'Search' => ['SearchURL' => 'https://play.example.org/helpers/query.php'],
                    'Messaging' => ['OfflineMessageURL' => 'https://play.example.org/helpers/offline.php'],
                ]),
                null,
                [],
                $routes,
                '',
            ),
        );

        expect(array_column($rows['opensim-helpers'], 'label'))
            ->not->toContain('query.php')
            ->not->toContain('offline.php')
            ->toContain('register.php')
            ->and(array_column($rows['[Search]'], 'label'))
            ->toBe(['SearchURL', 'SearchURL']);
    });

    test('has no row for a script the helpers do not serve here (the grid has its economy elsewhere)', function () use ($settings, $by) {
        $rows = $by(OpenSim_Helpers_Block_Health::plan($settings(['GridInfoService' => []]), null, [], ['/helpers/query.php' => 'query.php'], ''))['opensim-helpers'];

        expect(array_column($rows, 'label'))->toBe(['query.php', 'query.php']);
    });

    test('names the guide source as a setting: an address is asked, a folder is read, nothing is not defined', function () use ($settings, $routes, $by) {
        $row = fn(string $guide) => $by(OpenSim_Helpers_Block_Health::plan($settings([]), null, [], $routes, $guide))['[Helpers]'][0];

        expect($row('https://play.example.org/guide.json'))
            ->toMatchArray(['label' => 'GuideSource', 'url' => 'https://play.example.org/guide.json'])
            ->and($row(''))
            ->toMatchArray(['label' => 'GuideSource', 'status' => 'not defined', 'level' => 'none'])
            ->and($row(__DIR__))
            ->toMatchArray(['label' => 'GuideSource', 'level' => 'ok']);
    });
});
