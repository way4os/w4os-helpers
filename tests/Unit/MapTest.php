<?php

declare(strict_types=1);

use W4OS\Engine\Data\Connections;
use W4OS\Engine\Data\OSPDO;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Models\Region;
use W4OS\Engine\Testing\Machine;
use W4OS\Helpers\Block\Blocks;
use W4OS\Helpers\View\View;

/**
 * A grid whose Robust serves the map (the engine's WebMapTest): three regions side by side (a varregion of 512 m), a link to another
 * grid far away; or a grid without a map.
 */
function helpers_map_grid(bool $map = true): Grid
{
    $machine = new Machine();
    $robust = $machine->grid . '/Robust.HG.ini';
    $ini = (string) file_get_contents($robust);
    $map && ($ini = str_replace("[ServiceList]\n", "[ServiceList]\n    MapGetServiceConnector = \"\${Const|PublicPort}/OpenSim.Server.Handlers.dll:MapGetServiceConnector\"\n", $ini)
        . "\n[MapImageService]\n    LocalServiceModule = \"OpenSim.Services.MapImageService.dll:MapImageService\"\n    TilesStoragePath = \"\${Const|DataDirectory}/maptiles\"\n");
    $machine->write($robust, $ini . "\n[Hypergrid]\n    GatekeeperURI = \"http://grid.example.org:8002\"\n");
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE regions (uuid VARCHAR(36) PRIMARY KEY, regionHandle BIGINT, regionName VARCHAR(128), locX INT, locY INT, sizeX INT DEFAULT 0, sizeY INT DEFAULT 0, serverIP VARCHAR(64), serverPort INT, serverURI VARCHAR(255), flags INT DEFAULT 0, last_seen INT DEFAULT 0, owner_uuid CHAR(36), access INT DEFAULT 21, ScopeID CHAR(36), regionMapTexture VARCHAR(36))');
    $insert = $pdo->prepare('INSERT INTO regions (uuid, regionName, locX, locY, sizeX, sizeY, flags) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ([
        ['11111111-0000-0000-0000-000000000001', 'Welcome', 1000, 1000, 256, 256, Region::DEFAULT_REGION | Region::REGION_ONLINE],
        ['11111111-0000-0000-0000-000000000002', 'Plaza', 1001, 1000, 0, 0, Region::REGION_ONLINE],
        ['11111111-0000-0000-0000-000000000003', 'Big Island', 1002, 1000, 512, 512, 0],
        ['11111111-0000-0000-0000-000000000004', 'Elsewhere', 5000, 5000, 256, 256, Region::HYPERLINK],
    ] as [$uuid, $name, $x, $y, $sizeX, $sizeY, $flags]) {
        $insert->execute([$uuid, $name, $x * 256, $y * 256, $sizeX, $sizeY, $flags]);
    }
    Connections::using(static fn() => $pdo);

    return Grid::find($machine->environment, 'demo');
}

describe('The map of the grid', function () {
    $assets = dirname(__DIR__, 2) . '/assets/';

    test('is a block of the helpers, its tiles and its script for a browser alone: a viewer has the list of its regions', function () {
        expect(W4OS\Helpers\Block\Blocks::BLOCKS['map'])->toBe(W4OS\Helpers\Block\MapBlock::class)
            ->and(View::blockFiles('map', 'browser'))->toBe(['css' => ['css/map.css', 'lib/leaflet/leaflet.css', 'lib/tom-select/css/tom-select.min.css', 'css/map.web.css'], 'js' => ['lib/leaflet/leaflet.js', 'lib/tom-select/js/tom-select.popular.min.js', 'js/map.js']])
            ->and(View::blockFiles('map', 'viewer'))->toBe(['css' => ['css/map.css', 'css/map.viewer.css'], 'js' => []]);
    });

    test('draws on Leaflet, served by the helpers from its pinned release, never from a CDN', function () use ($assets) {
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $grid = W4OS\Engine\Models\Grid::at(helpers_environment(), sys_get_temp_dir() . '/demo');
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        expect($composer['require']['npm-asset/leaflet'])->toBe('1.9.4')
            ->and($view->asset($grid, 'lib/leaflet/leaflet.js'))->toEndWith('npm-asset/leaflet/dist/leaflet.js')
            ->and($view->asset($grid, 'lib/leaflet/leaflet.css'))->toEndWith('npm-asset/leaflet/dist/leaflet.css')
            ->and($view->asset($grid, 'lib/leaflet/images/layers.png'))->toBeString()
            // Tom Select, the region picker, from its package as well
            ->and($view->asset($grid, 'lib/tom-select/js/tom-select.popular.min.js'))->toEndWith('npm-asset/tom-select/dist/js/tom-select.popular.min.js')
            ->and($view->asset($grid, 'lib/tom-select/css/tom-select.min.css'))->toEndWith('npm-asset/tom-select/dist/css/tom-select.min.css')
            ->and($view->asset($grid, 'lib/leaflet/../package.json'))->toBeNull()
            ->and((string) file_get_contents($assets . 'js/map.js'))->not->toMatch('#https?://#');
    });

    test('places its tiles as the maps of Second Life do: a region a unit, the zoom of the tiles reversed, enlarged from closer', function () use ($assets) {
        $js = (string) file_get_contents($assets . 'js/map.js');

        expect($js)->toContain('crs: L.CRS.Simple', 'const ZOOM = { min: 1, native: 8, max: 10 };', 'ZOOM.native + 1 - Math.min(this._tileZoom, ZOOM.native)', 'Math.pow(2, zoom - 1)', '(Math.abs(coords.y) - 1) * regions', 'maxNativeZoom: ZOOM.native', 'maxBounds')
            // Closer until a region fills the smaller side of the map, whatever its size, its tile enlarged
            ->toContain('Math.max(ZOOM.max, Math.ceil(Math.log2(Math.max(1, Math.min(view.clientWidth, view.clientHeight)))))', 'maxZoom: closest()', 'map.setMaxZoom(closest())')
            // It opens on its regions fitting it, never smaller than 128 px a region, on the center the engine gives then
            ->toContain('const VIEW = { farthest: 7, margin: 16 };', 'map.getBoundsZoom(grid, false, L.point(2 * VIEW.margin, 2 * VIEW.margin))', 'fitted >= VIEW.farthest ? map.setView(grid.getCenter(), fitted) : map.setView(middle, VIEW.farthest)', '[Number(center.y), Number(center.x)]', 'zoomSnap: 0.25')
            // The name of each region above it, from 64 px a region
            ->toContain('const LABELS = 6;', 'direction: "top", className: "map-label"', 'map.getZoom() < LABELS')
            // The whole height left in the window when the page asks it
            ->toContain('block.dataset.mapFill && fill();', 'window.innerHeight - top)')
            // A picker of the regions by their names, top right (Tom Select): the one picked fills the map, its popup open
            ->toContain('new TomSelect(', 'view.after(box)', 'map.flyToBounds(area', 'map.once("moveend", () => popup(region, area.getCenter()))', 'texts.find_region', 'texts.no_region')
            // A click gives the teleport link of the list, its menu with it
            ->toContain('.map-region', 'W4OSTeleportLinks?.setup(content)')
            // Its layers (Leaflet's panes, z-index 400 and up) in a stacking context of their own, never over the menus of the site
            ->and((string) file_get_contents($assets . 'css/map.css'))->toContain(".block-map .map-view {\n\tisolation: isolate;\n}")
            // Its buttons Leaflet's, whatever a theme does with [role=button]
            ->and((string) file_get_contents($assets . 'css/map.web.css'))->toContain(".block-map .leaflet-bar a {\n\tmargin: 0;\n\tpadding: 0;\n\tborder-radius: 0;\n}", ".block-map .leaflet-bar a:first-child {\n\tborder-top-left-radius: 2px;", ".block-map .leaflet-bar a:last-child {\n\tborder-bottom-left-radius: 2px;");
    });

    test('lists its regions with their teleport links, the list the map takes its popups from, readable without a script', function () {
        $twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(dirname(__DIR__, 2) . '/templates'), ['autoescape' => 'html']);
        $twig->addFunction(new Twig\TwigFunction('tp_link', fn(array $links, string $label) => '<span data-w4os-tp>' . htmlspecialchars($label) . '</span>', ['is_safe' => ['html']]));
        $html = $twig->render('components/map-block.twig', [
            'name' => 'map', 'helpers_block' => 'map', 'title' => 'Map', 'level' => 'h2', 'labels' => [],
            'tiles' => '/grid-map/{z}/{x}/{y}.jpg', 'bounds' => ['minX' => 1000, 'minY' => 1000, 'maxX' => 1001, 'maxY' => 1000], 'center' => ['x' => 1000, 'y' => 1000],
            'texts' => ['zoom_in' => 'Zoom in', 'zoom_out' => 'Zoom out', 'find_region' => 'Find a region', 'no_region' => 'No region found'],
            'regions' => [['name' => 'Welcome', 'x' => 1000, 'y' => 1000, 'width' => 1, 'height' => 1, 'online' => true, 'links' => []], ['name' => 'Sea', 'x' => 1001, 'y' => 1000, 'width' => 2, 'height' => 2, 'online' => false, 'links' => []]],
        ]);

        expect($html)->toContain('<section class="block block-map" data-helpers-block="map">', '<h2 class="block-title">Map</h2>', 'data-map-tiles="/grid-map/{z}/{x}/{y}.jpg"', '<div class="map-view" hidden></div>')
            ->toContain('<li class="map-region" data-name="Welcome" data-x="1000" data-y="1000" data-width="1" data-height="1"><span data-w4os-tp>Welcome</span></li>')
            ->toContain('<li class="map-region is-offline" data-name="Sea" data-x="1001" data-y="1000" data-width="2" data-height="2">')
            ->not->toContain('data-map-fill');
    });

    test('shows the regions of the engine, their size in regions and their teleport links, at the address of the tiles the site gives', function () {
        $data = Blocks::data('map', helpers_map_grid(), ['map_tiles' => '/grid-map', 'lang' => 'en']);
        $regions = array_column($data['regions'], null, 'name');

        expect(array_keys($regions))->toBe(['Big Island', 'Plaza', 'Welcome'])
            ->and($regions['Big Island'])->toMatchArray(['x' => 1002, 'y' => 1000, 'width' => 2, 'height' => 2, 'online' => false])
            ->and($regions['Plaza'])->toMatchArray(['width' => 1, 'height' => 1, 'online' => true])
            ->and(array_column($regions['Welcome']['links']['browser'], 'link'))->toContain('hop://grid.example.org:8002/Welcome')
            ->and($data['tiles'])->toBe('/grid-map/{z}/{x}/{y}.jpg')
            ->and($data['fill'])->toBeFalse()
            ->and(Blocks::data('map', helpers_map_grid(), ['fill' => 'true'])['fill'])->toBeTrue()
            ->and($data['bounds'])->toBe(['minX' => 1000, 'minY' => 1000, 'maxX' => 1004, 'maxY' => 1002])
            ->and($data['center'])->toBe(['x' => 1000.5, 'y' => 1000.5])
            // Its title the engine's, in the language of the page
            ->and($data['title'])->toBe('Map')
            ->and((function () {
                W4OS\Helpers\I18n::use('fr');
                $title = Blocks::data('map', helpers_map_grid())['title'];
                W4OS\Helpers\I18n::use('en');

                return $title;
            })())->toBe('Carte');
    });

    test('shows nothing on a grid without a map, its title neither', function () {
        expect(Blocks::data('map', helpers_map_grid(false), ['lang' => 'en']))->toBe([])
            ->and((new View(dirname(__DIR__, 2) . '/templates'))->block('map', helpers_map_grid(false), ['lang' => 'en']))->toBe('');
    });

    test('gives its tiles under the helpers, as the engine has them; none on a grid without a map', function () {
        $app = W4OS\Helpers\App::create(static fn(string $nick) => helpers_map_grid());
        $tile = $app->handle((new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/map/1/1000/1000.jpg'));
        $none = W4OS\Helpers\App::create(static fn(string $nick) => helpers_map_grid(false))
            ->handle((new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/map/1/1000/1000.jpg'));

        expect([$tile->getStatusCode(), $tile->getHeaderLine('Content-Type')])->toBe([200, 'image/jpeg'])
            ->and((string) $tile->getBody())->toStartWith("\xFF\xD8")
            ->and($none->getStatusCode())->toBe(404);
    });
});

afterEach(fn() => Connections::using(null));
