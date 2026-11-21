<?php

use W4OS\Engine\Config\Layout;
use W4OS\Helpers\Block\Blocks;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Runtime\Environment;

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
function guide_shown(string $list, string $login, array $args = []): array
{
    $grid = guide_grid($list, $login);
    $shown = ['categories' => [], 'destinations' => []];
    foreach (Blocks::data('destination_guide', $grid, $args)['items'] as $category) {
        $shown['categories'][] = ['name' => $category['name'], 'data' => $category['data']];
        $items = Blocks::data('destination_guide', $grid, ['category' => $category['name']] + $args)['items'];
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

    test('the same destinations in each section; a card of another grid titled by its region now, linked for where it is shown', function () {
        $counts = fn (array $sections) => array_map('count', $sections);

        expect($counts(guide_shown('big-display', 'http://speculoos.world:8002')['destinations']))->toBe($counts(guide_old('big-display')['destinations']));
    });

    test('the lines as the board reads them: comments left out, the old format, trailing pipes, an address once', function () {
        expect(guide_shown('edge-cases', 'http://grid.example.org:8002')['destinations']['Hypergrid'])->toBe([
            ['name' => 'Old format', 'url' => 'hop://grid.example.org:8002/Sandbox/10/20/30'],
            ['name' => 'Trailing pipes', 'url' => 'hop://grid.example.org:8002/Trailing'],
            ['name' => 'Hop address', 'url' => 'hop://grid.example.org:8002/Hop/10/20/30'],
            ['name' => 'Http address', 'url' => 'hop://grid.example.org:8002/Http'],
            // After a column jump without a title of its own: the section goes on
            ['name' => 'After the dash', 'url' => 'hop://grid.example.org:8002/After'],
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
            ['name' => 'Local places', 'url' => 'hop://grid.example.org:8002/Welcome'],
            ['name' => 'Welcome landing', 'url' => 'hop://grid.example.org:8002/Plaza/128/128/25'],
        ]);
    });

    test('out of a viewer, a destination is a hop:// link: its grid, its region after a slash, its spaces %20', function () {
        $shown = guide_shown('big-display', 'http://speculoos.world:8002')['destinations'];

        expect($shown['Speculoos'][0])->toBe(['name' => 'Grand Place', 'url' => 'hop://speculoos.world:8002/Grand%20Place'])
            ->and($shown['Hang out'][0])->toBe(['name' => 'Exupery', 'url' => 'hop://hg.osgrid.org:80/Exupery'])
            // A grid without its region, an address without its port
            ->and($shown['Hang out'][2])->toBe(['name' => 'Wyldwood Bayou', 'url' => 'hop://wyldwoodbayou.com:8002'])
            ->and($shown['Find out'][0])->toBe(['name' => 'RuthAndRoth', 'url' => 'hop://login.osgrid.org:8002/RuthAndRoth']);
    });

    test('in a viewer, a region of this grid or of another is a secondlife://host:port/secondlife/Region link (the form of the viewer)', function () {
        $shown = guide_shown('big-display', 'http://speculoos.world:8002', ['agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)'])['destinations'];

        expect($shown['Speculoos'][0])->toBe(['name' => 'Grand Place', 'url' => 'secondlife://speculoos.world:8002/secondlife/Grand%20Place'])
            ->and($shown['Speculoos'][2])->toBe(['name' => 'Residences', 'url' => 'secondlife://speculoos.world:8002/secondlife/Residences/128/128/30'])
            ->and($shown['Hang out'][0])->toBe(['name' => 'Exupery', 'url' => 'secondlife://hg.osgrid.org:80/secondlife/Exupery'])
            ->and($shown['Find out'][0])->toBe(['name' => 'RuthAndRoth', 'url' => 'secondlife://login.osgrid.org:8002/secondlife/RuthAndRoth']);
    });
});

/**
 * A search that knows the parcels of a grid: two in Welcome (one with a picture), one in Plaza and one in Sandbox (with a
 * picture each), one of another grid.
 */
function guide_search(): W4OS\Engine\Data\OSPDO
{
    $pdo = new W4OS\Engine\Data\OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE regionsregister (regionUUID CHAR(36), regionname VARCHAR(255))');
    $pdo->exec('CREATE TABLE parcels (parcelUUID CHAR(36), regionUUID CHAR(36), parcelname VARCHAR(255), landingpoint VARCHAR(255), imageUUID CHAR(36), gatekeeperURL VARCHAR(255), dwell FLOAT DEFAULT 0)');
    $pdo->exec('CREATE TABLE popularplaces (parcelUUID CHAR(36), name VARCHAR(255), dwell FLOAT, has_picture INT, gatekeeperURL VARCHAR(255))');
    $pdo->exec("INSERT INTO regionsregister VALUES ('r1', 'Welcome'), ('r2', 'Sandbox'), ('r3', 'Elsewhere'), ('r4', 'Plaza')");
    $pdo->exec("INSERT INTO parcels VALUES
        ('p1', 'r1', 'Local places', '128/128/25', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'http://grid.example.org:8002', 40),
        ('p2', 'r1', 'Garden', '64/64/22', '00000000-0000-0000-0000-000000000000', 'http://grid.example.org:8002', 10),
        ('p3', 'r2', 'Sandbox', '10/20/30', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'http://grid.example.org:8002/', 0),
        ('p4', 'r3', 'Elsewhere', '128/128/25', 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'http://other.example.org:8002', 0),
        ('p5', 'r4', 'Square', '128/128/25', 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'http://grid.example.org:8002', 0)");
    $pdo->exec("INSERT INTO popularplaces VALUES ('p1', 'Local places', 40, 1, 'http://grid.example.org:8002'), ('p2', 'Garden', 10, 0, 'http://grid.example.org:8002')");

    return $pdo;
}

describe('the hypergrid destinations the pictures are tried with', function () {
    test('are the guide itself, one category: each titled by its region, linked by hop:// on a page', function () {
        $items = Blocks::data('destination_guide', guide_grid('hypergrid-examples', 'http://grid.example.org:8002'), ['client' => 'browser'])['items'];

        expect(array_column($items, 'name'))
            ->toBe(['Welcome', 'Grand Place', 'Ivory City', 'Paramour Shopping', 'Alternate Metaverse', 'Lbsa Plaza', 'AI Dreams in Art'])
            ->and(array_column($items, 'url'))
            ->toBe([
                'hop://watta.world:8002/Welcome',
                'hop://speculoos.world:8002/Grand%20Place/128/128/25',
                'hop://craft-world.org:8002/Ivory%20City',
                'hop://lfgrid.com:8002/Paramour%20Shopping',
                'hop://alternatemetaverse.com:8002/Alternate%20Metaverse/298/322/63',
                'hop://hg.osgrid.org:80/Lbsa%20Plaza',
                'hop://grid.kitely.com:8002/AI%20Dreams%20in%20Art',
            ]);
    });
});

describe('the pictures of the guide and of the popular places: the parcel, never the region', function () {
    afterEach(fn () => W4OS\Engine\Data\Connections::using(null));

    test('each card shows the picture the engine finds (Grid::guide()): a destination its parcel, a section its first place with one, else the placeholder', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        $categories = array_column(Blocks::data('destination_guide', $grid)['items'], 'picture', 'name');
        $places = array_column(Blocks::data('destination_guide', $grid, ['category' => 'Destinations Guide'])['items'], 'picture', 'name');
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('destination_guide', $grid, ['assets' => '/pictures']);

        expect($places)->toBe(['Local places' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'Welcome landing' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd'])
            ->and($categories['Destinations Guide'])->toBe('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
            ->and($categories['Hypergrid'])->toBe('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')
            ->and($categories['Apart'])->toBeNull()
            ->and($html)->toContain('src="/pictures/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa.jpg"')
            ->and($html)->toContain('src="/pictures/' . W4OS\Engine\Assets\WebAssets::PLACEHOLDER . '.jpg"');
    });

    test('the popular places are cards with the picture of their parcel, the placeholder without one', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('popular_places', guide_grid('edge-cases', 'http://grid.example.org:8002'), ['assets' => '/pictures']);

        expect($html)->toContain('class="places"')
            ->and($html)->toMatch('#<a class="place" href="hop://grid.example.org:8002/Welcome/128/128/25">\s*<img class="place-picture" src="/pictures/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa.jpg"#')
            ->and($html)->toContain('src="/pictures/' . W4OS\Engine\Assets\WebAssets::PLACEHOLDER . '.jpg"');
    });

    test('the popular places take the options of w4os-wp 2.x and its defaults: the other grids and the land for sale, both left out unless asked', function () {
        $search = guide_search();
        $search->exec("INSERT INTO popularplaces VALUES ('p4', 'Elsewhere', 50, 1, 'http://other.example.org:8002')");
        $search->exec('CREATE TABLE parcelsales (parcelUUID CHAR(36), regionUUID CHAR(36))');
        $search->exec("INSERT INTO parcelsales VALUES ('p2', 'r1')");
        W4OS\Engine\Data\Connections::using(fn () => $search);
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        $labels = fn (array $args): array => array_column(Blocks::data('popular_places', $grid, $args)['rows'], 'label');

        expect($labels([]))->toBe(['Local places'])
            ->and($labels(['include_hypergrid' => 'true']))->toBe(['Elsewhere', 'Local places'])
            ->and($labels(['include_landsales' => '1']))->toBe(['Local places', 'Garden'])
            ->and($labels(['include_hypergrid' => true, 'include_landsales' => 'yes']))->toBe(['Elsewhere', 'Local places', 'Garden'])
            ->and($labels(['include_hypergrid' => 'false']))->toBe(['Local places']);
    });

    test('an open category is headed by its name, whatever title the page gives the guide', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('destination_guide', guide_grid('edge-cases', 'http://grid.example.org:8002'), ['category' => 'Hypergrid', 'title' => 'The guide', 'client' => 'viewer']);

        expect($html)->toContain('<h2>Hypergrid</h2>')->and($html)->toContain('class="back"');
    });

    test('in a browser, the guide is a block of the page as the popular places are: its title as theirs, the class of its client for its style', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $view = new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates');
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');

        $browser = $view->block('destination_guide', $grid, ['client' => 'browser', 'level' => 'h2']);
        $places = $view->block('popular_places', $grid, ['client' => 'browser', 'level' => 'h2']);
        $viewer = $view->block('destination_guide', $grid, ['agent' => 'Mozilla/5.0 SecondLife/7.1 (Firestorm)']);

        expect($browser)
            ->toContain('class="block block-guide client-browser"')
            ->toContain('<h2 class="block-title">Destinations Guide</h2>')
            ->and($places)
            ->toContain('<h2 class="block-title">')
            // The viewer keeps the look of its Destinations panel
            ->and($viewer)
            ->toContain('class="block block-guide client-viewer"')
            ->toContain('<h1>Destinations Guide</h1>');
    });

    test('the picture of nothing is lighter than a picture: half seen, in the guide and the popular places, set in one place', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $view = new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates');
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        // The categories: one of them has no picture (Apart)
        $guide = $view->block('destination_guide', $grid, ['assets' => '/pictures']);
        $places = $view->block('popular_places', $grid, ['assets' => '/pictures']);
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/blocks.css');
        $placeholder = W4OS\Engine\Assets\WebAssets::PLACEHOLDER;

        expect($guide)
            ->toMatch('#<img class="thumbnail no-picture" src="/pictures/' . $placeholder . '\.jpg"#')
            ->toMatch('#<img class="thumbnail" src="/pictures/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa\.jpg"#')
            ->and($places)
            ->toMatch('#<img class="place-picture no-picture" src="/pictures/' . $placeholder . '\.jpg"#')
            ->and($css)
            ->toContain('--helpers-placeholder-opacity: 0.5')
            ->toContain('opacity: var(--helpers-placeholder-opacity)');
    });

    test('the dark look of the viewers is the one of the viewer only: in a browser the colours are the theme\'s', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/guide.css');
        $base = substr($css, (int) strpos($css, '*/'), (int) strpos($css, '/* In a viewer') - (int) strpos($css, '*/'));

        expect($base)
            ->not->toContain('#2b2b2b')
            ->toContain('color: inherit')
            ->and($css)
            ->toContain('#guide.client-viewer')
            ->toContain('body.viewer #guide');
    });

    test('a site that shows the blocks in its pages knows the files they need', function () {
        expect(W4OS\Helpers\View\View::blockFiles('destination_guide'))->toBe(['css' => ['css/blocks.css', 'css/guide.css'], 'js' => ['js/guide.js']])
            ->and(W4OS\Helpers\View\View::blockFiles('popular-places'))->toBe(['css' => ['css/blocks.css', 'css/places.css'], 'js' => []])
            ->and(W4OS\Helpers\View\View::blockFiles('motd'))->toBe(['css' => [], 'js' => []]);
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
        $parsed = array_merge(...array_values(W4OS\Engine\Text\DestinationList::parse($text)));

        // The board leaves out the http:// of an address, the engine reads it (Destination)
        $parsed = array_map(fn (array $d) => ['name' => $d['name'], 'url' => str_replace('http://', '', $d['url'])], $parsed);

        expect($parsed)->toBe(array_map(fn (array $d) => ['name' => trim($d['name']), 'url' => $d['url']], $board));
    });

    test('a title of the board is a category of the guide, in the same order', function () {
        $text = (string) file_get_contents(__DIR__ . '/../fixtures/guide/big-display.txt');
        // A title inside a column of the board (Hypergrid Safari Tour, under Hang out) is a category of its own in the guide, as in 2.x
        $titles = array_column(array_filter(board_cells($text), fn (array $cell) => $cell['kind'] === 'title'), 'name');

        expect(array_keys(W4OS\Engine\Text\DestinationList::parse($text)))->toBe($titles);
    });

    test('the old format: the region name is the name, the 4th column the address, the 5th the landing point', function () {
        $line = "Speculoos|Grand Place|1000,1000|speculoos.world:8002:Grand Place|128,128,25\n";

        expect(W4OS\Engine\Text\DestinationList::parse($line))->toBe(['Destinations Guide' => [['name' => 'Grand Place', 'url' => 'speculoos.world:8002:Grand Place/128/128/25']]]);
    });

    test('an address listed already is left out, as the board does', function () {
        $text = "Places\nOne|grid.example.org:8002:Sandbox\nTwo|grid.example.org:8002:Sandbox\n";

        expect(W4OS\Engine\Text\DestinationList::parse($text))->toBe(['Places' => [['name' => 'One', 'url' => 'grid.example.org:8002:Sandbox']]]);
    });

    test('the list for the boards (format=list) gives them their columns back: the landing point in its own column, the markers kept', function () {
        $text = "Places\nResidences|speculoos.world:8002:Residences|128,128,30\n|\n-\nOther\nLab|speculoos.world:8002:Lab\n";

        expect(W4OS\Engine\Text\DestinationList::format(W4OS\Engine\Text\DestinationList::parse($text, layout: true)))->toBe($text);
    });
});

describe('the number of destinations of a category', function () {
    afterEach(fn () => W4OS\Helpers\I18n::use('en'));

    test('one destination, several destinations, in the language of the page', function () {
        $categories = fn () => array_column(Blocks::data('destination_guide', guide_grid('edge-cases', 'http://grid.example.org:8002'))['items'], 'data', 'name');

        W4OS\Helpers\I18n::use('en');
        expect($categories()['Hypergrid'])->toBe('5 destinations')
            ->and($categories()['Apart'])->toBe('2 destinations')
            ->and($categories()['A long title, written on two lines because it did not fit on one'])->toBe('1 destination');
        W4OS\Helpers\I18n::use('fr');
        expect($categories()['A long title, written on two lines because it did not fit on one'])->toBe('1 destination')
            ->and($categories()['Hypergrid'])->toBe('5 destinations');
        W4OS\Helpers\I18n::use('de');
        expect($categories()['A long title, written on two lines because it did not fit on one'])->toBe('1 Ziel')
            ->and($categories()['Hypergrid'])->toBe('5 Ziele');
    })->skip(fn () => setlocale(LC_MESSAGES, ['fr_FR.UTF-8', 'fr_FR.utf8']) === false || setlocale(LC_MESSAGES, ['de_DE.UTF-8', 'de_DE.utf8']) === false, 'the system has no French or German locale');
});

describe('the teleport links of the blocks, for where they are shown (Destination::AUTO)', function () {
    afterEach(fn () => W4OS\Engine\Data\Connections::using(null));

    test('the popular places: hop:// on a page, secondlife://host:port/secondlife/Region in a viewer', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');

        expect(Blocks::data('popular_places', $grid)['rows'][0]['url'])->toBe('hop://grid.example.org:8002/Welcome/128/128/25')
            ->and(Blocks::data('popular_places', $grid, ['agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)'])['rows'][0]['url'])->toBe('secondlife://grid.example.org:8002/secondlife/Welcome/128/128/25');
    });

    test('the grid info: its login address linked by hop:// on a page; in a viewer, already on the grid, no link', function () {
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        $login = fn (array $args) => array_column(Blocks::data('grid-info', $grid, $args)['rows'], 'url', 'label')['Login URI'];

        expect($login([]))->toBe('hop://grid.example.org:8002')
            ->and($login(['agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)']))->toBeNull();
    });

    test('the picks and the classifieds of a profile: the hop:// of the engine, as the link for where they are shown', function () {
        $row = ['name' => 'Beach', 'teleport' => 'hop://grid.example.org:8002/My%20Region/10/20/30'];
        $item = fn (string $login, array $args) => W4OS\Helpers\Block\ProfileBlock::item($row, guide_grid('edge-cases', $login), $args)['url'];

        expect($item('http://grid.example.org:8002', []))->toBe('hop://grid.example.org:8002/My%20Region/10/20/30')
            ->and($item('http://grid.example.org:8002', ['agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)']))->toBe('secondlife://grid.example.org:8002/secondlife/My%20Region/10/20/30')
            ->and($item('http://other.example.org:8002', ['agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)']))->toBe('secondlife://grid.example.org:8002/secondlife/My%20Region/10/20/30');
    });

    test('a page knows where it is shown from the user agent, and its blocks link for it: the login screen of a viewer', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        $view = fn () => new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates');

        expect($view()->page('splash', $grid, 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)'))->toContain('href="secondlife://grid.example.org:8002/secondlife/Welcome/128/128/25"')
            ->and($view()->page('splash', $grid, 'Mozilla/5.0 Chrome/131'))->toContain('href="hop://grid.example.org:8002/Welcome/128/128/25"');
    });

    test('a block asked by a site gives the user agent of its visitor (agent), else the one of the request is read', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        $app = W4OS\Helpers\App::create(static fn (string $nick) => $grid);
        $get = fn (string $path, string $agent) => (string) $app->handle((new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('User-Agent', $agent))->getBody();

        expect($get('/blocks/popular_places?agent=' . rawurlencode('Mozilla/5.0 SecondLife/7.1.9 (Firestorm)'), 'Grav'))->toContain('href="secondlife://grid.example.org:8002/secondlife/Welcome/128/128/25"')
            ->and($get('/blocks/popular_places', 'Grav'))->toContain('href="hop://grid.example.org:8002/Welcome/128/128/25"')
            ->and($get('/blocks/popular_places', 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)'))->toContain('href="secondlife://grid.example.org:8002/secondlife/Welcome/128/128/25"');
    });

    test('a site may give the class of its visitor (client: viewer or browser) instead of its user agent; the class wins', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $grid = guide_grid('edge-cases', 'http://grid.example.org:8002');
        $link = fn (array $args) => Blocks::data('popular_places', $grid, $args)['rows'][0]['url'];
        $app = W4OS\Helpers\App::create(static fn (string $nick) => $grid);
        $get = fn (string $path) => (string) $app->handle((new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', $path)->withHeader('User-Agent', 'Grav'))->getBody();

        expect($link(['client' => 'viewer']))->toBe('secondlife://grid.example.org:8002/secondlife/Welcome/128/128/25')
            ->and($link(['client' => 'browser', 'agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)']))->toBe('hop://grid.example.org:8002/Welcome/128/128/25')
            ->and($get('/blocks/popular_places?client=viewer'))->toContain('href="secondlife://grid.example.org:8002/secondlife/Welcome/128/128/25"')
            ->and($get('/blocks/popular_places?client=browser'))->toContain('href="hop://grid.example.org:8002/Welcome/128/128/25"');
    });

    test('a place of the grid with a landing point in decimals keeps its region in a viewer', function () {
        $search = new W4OS\Helpers\Block\SearchData(guide_grid('edge-cases', 'http://grid.example.org:8002'));

        expect($search->teleport('http://grid.example.org:8002', 'Welcome', '116/136/21.75', ['client' => 'viewer']))->toBe('secondlife://grid.example.org:8002/secondlife/Welcome/116/136/21.75');
    });

    test('a card shows what you go to, then where: on this grid the name of the list and its region and point, on another its region and its grid', function () {
        $grid = guide_grid('big-display', 'http://speculoos.world:8002');
        $cards = fn (string $section) => array_map(fn (array $item) => [$item['name'], $item['data']], Blocks::data('destination_guide', $grid, ['category' => $section])['items']);

        expect($cards('Speculoos'))->toContain(['Grand Place', 'Grand Place'], ['Residences', 'Residences/128/128/30'])
            ->and($cards('Hang out'))->toContain(['Exupery', 'hg.osgrid.org:80'], ['Wyldwood Bayou', 'wyldwoodbayou.com:8002']);
    });

    test('a popular place shows the label of its destination under its name, not the bare name of its region', function () {
        W4OS\Engine\Data\Connections::using(fn () => guide_search());
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('popular_places', guide_grid('edge-cases', 'http://grid.example.org:8002'), ['assets' => '/pictures']);

        expect($html)->toMatch('#<span class="place-name">Local places</span>\s*<span class="place-region">Welcome/128/128/25</span>#');
    });
});

describe('the destination guide without destinations', function () {
    test('says where its list is read: its source, else the guide of 2do.directory', function () {
        $dir = sys_get_temp_dir() . '/helpers-guide-empty-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[GridInfoService]\ngridname = \"Example\"\ngridnick = \"example\"\nlogin = \"http://example.org:8002/\"\n");
        file_put_contents("$dir/empty.txt", '');
        file_put_contents("$dir/Helpers.ini", "[Helpers]\nGuideSource = \"$dir/empty.txt\"\n");

        $data = Blocks::data('destination_guide', Grid::at(Environment::system(Layout::load()), $dir));

        expect($data['reason'] ?? '')->toContain('GuideSource')->toContain('2do.directory');
    });
});
