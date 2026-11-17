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
        expect(guide_shown('big-display', 'http://speculoos.world:8002')['categories'])->toBe(guide_old('big-display')['categories']);
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

    test('the same lines left out: comments, empty lines, the old format of the 4th and 5th columns, trailing pipes', function () {
        $shown = guide_shown('edge-cases', 'http://grid.example.org:8002');
        $old = guide_old('edge-cases');

        expect($shown['destinations']['Hypergrid'])->toBe([...$old['destinations']['Hypergrid'], ...$old['destinations']['-']])
            ->and($shown['destinations']['Empty category'])->toBe($old['destinations']['Empty category']);
    });

    test('differs on purpose: a dash alone is a layout hint of the boards, not a category; a category keeps its whole name', function () {
        $names = array_column(guide_shown('edge-cases', 'http://grid.example.org:8002')['categories'], 'name');

        expect($names)->not->toContain('-')
            ->and($names)->toContain('A category name longer than a hundred characters, written by someone who had a lot to say about it, really a lot');
    });

    test('a region of the grid, by its name alone, is linked on the grid', function () {
        expect(guide_shown('edge-cases', 'http://grid.example.org:8002')['destinations']['Destinations Guide'])->toBe([
            ['name' => 'Local places', 'url' => 'secondlife://grid.example.org:8002%20Welcome'],
            ['name' => 'Welcome alone', 'url' => 'secondlife://grid.example.org:8002%20Welcome'],
            ['name' => 'Welcome landing', 'url' => 'secondlife://grid.example.org:8002%20Welcome/128/128/25'],
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
