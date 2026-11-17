<?php

use OpenSim\Config\Layout;
use OpenSim\Helpers\Block\Blocks;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;

/**
 * The guide of the helpers 2.x, on the same list (tests/fixtures/guide): what it showed is in the *.old.json files beside each
 * list (tests/fixtures/guide/old/run.php made them). The guide shows the same categories, in the same order, with the same
 * destinations linked the same way; where it differs on purpose, the test says so.
 */
function guide_grid(string $list, string $login): Grid
{
    $dir = sys_get_temp_dir() . '/helpers-guide-' . md5($list . $login);
    is_dir($dir) || mkdir($dir, 0o755, true);
    file_put_contents("$dir/Robust.ini", "[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\nlogin = \"$login/\"\n");
    file_put_contents("$dir/Helpers.ini", "[Helpers]\nGuideSource = \"" . __DIR__ . "/../fixtures/guide/$list.txt\"\n");

    return Grid::at(Environment::system(Layout::load()), $dir);
}

/** @return array{categories: list<array{name: string, data: string}>, destinations: array<string, list<array{name: string, url: string}>>} */
function guide_shown(string $list, string $login): array
{
    $grid = guide_grid($list, $login);
    $shown = ['categories' => [], 'destinations' => []];
    foreach (Blocks::data('destination_guide', $grid)['items'] as $category) {
        $shown['categories'][] = ['name' => $category['name'], 'data' => $category['data']];
        $items = Blocks::data('destination_guide', $grid, ['category' => $category['name']])['items'];
        $shown['destinations'][$category['name']] = array_map(fn (array $item) => ['name' => $item['name'], 'url' => $item['url']], $items);
    }

    return $shown;
}

/** What the guide 2.x showed; a link as the viewer reads it (a space and %20 are the same to it) */
function guide_old(string $list): array
{
    $old = json_decode((string) file_get_contents(__DIR__ . "/../fixtures/guide/$list.old.json"), true);
    foreach ($old['destinations'] as &$places) {
        $places = array_map(fn (array $place) => ['name' => $place['name'], 'url' => str_replace(' ', '%20', $place['url'])], $places);
    }

    return $old;
}

describe('the destination guide, as the helpers 2.x showed it', function () {
    test('the same categories, in the same order, with the same number of destinations', function () {
        // One destination is singular now (the 2.x said "1 destinations")
        $old = array_map(fn (array $c) => ['name' => $c['name'], 'data' => $c['data'] === '1 destinations' ? '1 destination' : $c['data']], guide_old('big-display')['categories']);

        expect(guide_shown('big-display', 'http://speculoos.world:8002')['categories'])->toBe($old);
    });

    test('the same destinations, linked as the viewers open them (secondlife://host:port Region)', function () {
        $old = guide_old('big-display')['destinations'];
        // A place in the region (third column) is now part of the link
        $old['Speculoos'][2]['url'] .= '/128/128/30';
        $old['Hang out'][1]['url'] .= '/28/11/26';
        // An address without its port keeps its region now (2.x linked the grid alone)
        $old['Find out'][0]['url'] = 'secondlife://login.osgrid.org:8002%20RuthAndRoth';
        $shown = guide_shown('big-display', 'http://speculoos.world:8002')['destinations'];
        foreach ($old as $category => $places) {
            expect($shown[$category])->toBe($places);
        }
    });

    test('the lines as the board reads them: comments left out, the old format, trailing pipes, an address once', function () {
        expect(guide_shown('edge-cases', 'http://grid.example.org:8002')['destinations']['Hypergrid'])->toBe([
            ['name' => 'Old format', 'url' => 'secondlife://grid.example.org:8002%20Sandbox/10/20/30'],
            ['name' => 'Trailing pipes', 'url' => 'secondlife://grid.example.org:8002%20Trailing'],
            ['name' => 'Hop address', 'url' => 'secondlife://grid.example.org:8002%20Hop/10/20/30'],
            ['name' => 'Http address', 'url' => 'secondlife://grid.example.org:8002%20Http'],
            // After a column jump without a title of its own: the section goes on
            ['name' => 'After the dash', 'url' => 'secondlife://grid.example.org:8002%20After'],
        ]);
    });

    test('the sections are the titles: a dash or a pipe is never one, titles that follow each other are one, a title without a destination is left out', function () {
        expect(array_column(guide_shown('edge-cases', 'http://grid.example.org:8002')['categories'], 'name'))->toBe([
            'Destinations Guide',
            'Hypergrid',
            'A long title, written on two lines because it did not fit on one',
            'Apart',
        ]);
    });

    test('a region of the grid, by its name alone, is linked on the grid', function () {
        expect(guide_shown('edge-cases', 'http://grid.example.org:8002')['destinations']['Destinations Guide'])->toBe([
            ['name' => 'Local places', 'url' => 'secondlife://grid.example.org:8002%20Welcome'],
            ['name' => 'Welcome landing', 'url' => 'secondlife://grid.example.org:8002%20Plaza/128/128/25'],
        ]);
    });

    test('a grid without its region is linked to the grid, as the 2.x did', function () {
        $shown = guide_shown('big-display', 'http://speculoos.world:8002')['destinations'];

        expect($shown['Hang out'][2])->toBe(['name' => 'Wyldwood Bayou', 'url' => 'secondlife://wyldwoodbayou.com:8002']);
    });

    test('an address without its port is the region of that grid, at its default port', function () {
        $shown = guide_shown('big-display', 'http://speculoos.world:8002')['destinations'];

        expect($shown['Find out'][0])->toBe(['name' => 'RuthAndRoth', 'url' => 'secondlife://login.osgrid.org:8002%20RuthAndRoth']);
    });
});

/**
 * A search that knows the parcels of a grid: two in Welcome (one with a picture), one in Plaza and one in Sandbox (with a
 * picture each), one of another grid.
 */
function guide_search(): OpenSim\Data\OSPDO
{
    $pdo = new OpenSim\Data\OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE regionsregister (regionUUID CHAR(36), regionname VARCHAR(255))');
    $pdo->exec('CREATE TABLE parcels (parcelUUID CHAR(36), regionUUID CHAR(36), parcelname VARCHAR(255), landingpoint VARCHAR(255), imageUUID CHAR(36), gatekeeperURL VARCHAR(255), dwell FLOAT DEFAULT 0)');
    $pdo->exec('CREATE TABLE popularplaces (parcelUUID CHAR(36), name VARCHAR(255), dwell FLOAT, has_picture INT)');
    $pdo->exec("INSERT INTO regionsregister VALUES ('r1', 'Welcome'), ('r2', 'Sandbox'), ('r3', 'Elsewhere'), ('r4', 'Plaza')");
    $pdo->exec("INSERT INTO parcels VALUES
        ('p1', 'r1', 'Local places', '128/128/25', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'http://grid.example.org:8002', 40),
        ('p2', 'r1', 'Garden', '64/64/22', '00000000-0000-0000-0000-000000000000', 'http://grid.example.org:8002', 10),
        ('p3', 'r2', 'Sandbox', '10/20/30', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'http://grid.example.org:8002/', 0),
        ('p4', 'r3', 'Elsewhere', '128/128/25', 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'http://other.example.org:8002', 0),
        ('p5', 'r4', 'Square', '128/128/25', 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'http://grid.example.org:8002', 0)");
    $pdo->exec("INSERT INTO popularplaces VALUES ('p1', 'Local places', 40, 1), ('p2', 'Garden', 10, 0)");

    return $pdo;
}

describe('the pictures of the guide and of the popular places: the parcel, never the region', function () {
    afterEach(fn () => OpenSim\Data\Connections::using(null));

    test('each card shows the picture the engine finds (Grid::guide()): a destination its parcel, a section its first place with one, else the placeholder', function () {
        OpenSim\Data\Connections::using(fn () => guide_search());
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        $categories = array_column(Blocks::data('destination_guide', $grid)['items'], 'picture', 'name');
        $places = array_column(Blocks::data('destination_guide', $grid, ['category' => 'Destinations Guide'])['items'], 'picture', 'name');
        $html = (new OpenSim\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('destination_guide', $grid, ['assets' => '/pictures']);

        expect($places)->toBe(['Local places' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Welcome landing' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd'])
            ->and($categories['Destinations Guide'])->toBe('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
            ->and($categories['Hypergrid'])->toBe('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')
            ->and($categories['Apart'])->toBeNull()
            ->and($html)->toContain('src="/pictures/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa.jpg"')
            ->and($html)->toContain('src="/pictures/' . OpenSim\Assets\WebAssets::PLACEHOLDER . '.jpg"');
    });

    test('the popular places are cards with the picture of their parcel, the placeholder without one', function () {
        OpenSim\Data\Connections::using(fn () => guide_search());
        $html = (new OpenSim\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('popular_places', guide_grid('edge-cases', 'http://grid.example.org:8002'), ['assets' => '/pictures']);

        expect($html)->toContain('class="places"')
            ->and($html)->toMatch('#<a class="place" href="hop://grid.example.org:8002/Welcome/128/128/25">\s*<img class="place-picture" src="/pictures/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa.jpg"#')
            ->and($html)->toContain('src="/pictures/' . OpenSim\Assets\WebAssets::PLACEHOLDER . '.jpg"');
    });

    test('an open category is headed by its name, whatever title the page gives the guide', function () {
        OpenSim\Data\Connections::using(fn () => guide_search());
        $html = (new OpenSim\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('destination_guide', guide_grid('edge-cases', 'http://grid.example.org:8002'), ['category' => 'Hypergrid', 'title' => 'The guide']);

        expect($html)->toContain('<h2>Hypergrid</h2>')->and($html)->toContain('class="back"');
    });

    test('a site that shows the blocks in its pages knows the files they need', function () {
        expect(OpenSim\Helpers\View\View::blockFiles('destination_guide'))->toBe(['css' => ['css/guide.css'], 'js' => ['js/guide.js']])
            ->and(OpenSim\Helpers\View\View::blockFiles('popular-places'))->toBe(['css' => ['css/places.css'], 'js' => []])
            ->and(OpenSim\Helpers\View\View::blockFiles('motd'))->toBe(['css' => [], 'js' => []]);
    });
});

/**
 * The cells of the Gudz Teleport Board 2 for a list, as its script reads it (parseDestination() and addDestination() of
 * "Gudz Teleport Board 2.ossl.lsl", written again here): a title (a name without an address), a destination (name, address,
 * landing point), a spacer (`|` alone), a column jump (`-` alone); `#` and `//` lines left out, an address listed already too.
 *
 * @return list<array{kind: string, name?: string, uri?: string, landing?: string}>
 */
function board_cells(string $text): array
{
    $cells = [];
    $uris = [];
    foreach (explode("\n", $text) as $line) {
        if (trim($line) === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
            continue;
        }
        if ($line === '-') {
            $cells[] = ['kind' => 'column'];
            continue;
        }
        $parse = explode('|', $line);
        if (count($parse) >= 4) {
            // The old format: grid name, region name, grid coordinates, address, landing point
            [$name, $uri, $landing] = [$parse[1] !== '' ? $parse[1] : $parse[0], $parse[3], $parse[4] ?? ''];
        } else {
            [$name, $uri, $landing] = [$parse[0], $parse[1] ?? '', $parse[2] ?? ''];
        }
        if ($uri !== '' && in_array($uri, $uris, true)) {
            continue;
        }
        $uris[] = $uri;
        $uri = str_replace('http://', '', $uri);
        $cells[] = $uri !== '' ? ['kind' => 'destination', 'name' => $name, 'uri' => $uri, 'landing' => $landing]
            : ['kind' => $name === '' ? 'spacer' : 'title', 'name' => $name];
    }

    return $cells;
}

describe('the list, as the Gudz Teleport Board reads it', function () {
    test('the same destinations, in the same order, at the same address and landing point', function () {
        $text = (string) file_get_contents(__DIR__ . '/../fixtures/guide/big-display.txt');
        $board = array_map(
            fn (array $cell) => ['name' => $cell['name'], 'url' => $cell['uri'] . ($cell['landing'] === '' ? '' : '/' . str_replace(',', '/', $cell['landing']))],
            array_values(array_filter(board_cells($text), fn (array $cell) => $cell['kind'] === 'destination')),
        );
        $parsed = array_merge(...array_values(OpenSim\Text\DestinationList::parse($text)));

        // The board leaves out the http:// of an address, the engine reads it (Destination)
        $parsed = array_map(fn (array $d) => ['name' => $d['name'], 'url' => str_replace('http://', '', $d['url'])], $parsed);

        expect($parsed)->toBe(array_map(fn (array $d) => ['name' => trim($d['name']), 'url' => $d['url']], $board));
    });

    test('a title of the board is a category of the guide, in the same order', function () {
        $text = (string) file_get_contents(__DIR__ . '/../fixtures/guide/big-display.txt');
        // A title inside a column of the board (Hypergrid Safari Tour, under Hang out) is a category of its own in the guide, as in 2.x
        $titles = array_column(array_filter(board_cells($text), fn (array $cell) => $cell['kind'] === 'title'), 'name');

        expect(array_keys(OpenSim\Text\DestinationList::parse($text)))->toBe($titles);
    });

    test('the old format: the region name is the name, the 4th column the address, the 5th the landing point', function () {
        $line = "Speculoos|Grand Place|1000,1000|speculoos.world:8002:Grand Place|128,128,25\n";

        expect(OpenSim\Text\DestinationList::parse($line))->toBe(['Destinations Guide' => [['name' => 'Grand Place', 'url' => 'speculoos.world:8002:Grand Place/128/128/25']]]);
    });

    test('an address listed already is left out, as the board does', function () {
        $text = "Places\nOne|grid.example.org:8002:Sandbox\nTwo|grid.example.org:8002:Sandbox\n";

        expect(OpenSim\Text\DestinationList::parse($text))->toBe(['Places' => [['name' => 'One', 'url' => 'grid.example.org:8002:Sandbox']]]);
    });

    test('the list for the boards (format=list) gives them their columns back: the landing point in its own column, the markers kept', function () {
        $text = "Places\nResidences|speculoos.world:8002:Residences|128,128,30\n|\n-\nOther\nLab|speculoos.world:8002:Lab\n";

        expect(OpenSim\Text\DestinationList::format(OpenSim\Text\DestinationList::parse($text, layout: true)))->toBe($text);
    });
});

describe('the number of destinations of a category', function () {
    afterEach(fn () => OpenSim\Helpers\I18n::use('en'));

    test('one destination, several destinations, in the language of the page', function () {
        $categories = fn () => array_column(Blocks::data('destination_guide', guide_grid('edge-cases', 'http://grid.example.org:8002'))['items'], 'data', 'name');

        OpenSim\Helpers\I18n::use('en');
        expect($categories()['Hypergrid'])->toBe('5 destinations')
            ->and($categories()['Apart'])->toBe('2 destinations')
            ->and($categories()['A long title, written on two lines because it did not fit on one'])->toBe('1 destination');
        OpenSim\Helpers\I18n::use('fr');
        expect($categories()['A long title, written on two lines because it did not fit on one'])->toBe('1 destination')
            ->and($categories()['Hypergrid'])->toBe('5 destinations');
        OpenSim\Helpers\I18n::use('de');
        expect($categories()['A long title, written on two lines because it did not fit on one'])->toBe('1 Ziel')
            ->and($categories()['Hypergrid'])->toBe('5 Ziele');
    })->skip(fn () => setlocale(LC_MESSAGES, ['fr_FR.UTF-8', 'fr_FR.utf8']) === false || setlocale(LC_MESSAGES, ['de_DE.UTF-8', 'de_DE.utf8']) === false, 'the system has no French or German locale');
});
