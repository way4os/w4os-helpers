<?php

declare(strict_types=1);

use W4OS\Helpers\App;
use W4OS\Helpers\Http\Action\Engine;
use W4OS\Engine\Models\Grid;
use Slim\Psr7\Factory\ServerRequestFactory;

function helpers_get(Slim\App $app, string $path): Psr\Http\Message\ResponseInterface
{
    return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
}

function helpers_app(): Slim\App
{
    $grid = Grid::at(helpers_environment(), sys_get_temp_dir() . '/demo');

    return App::create(static fn(string $nick) => $grid);
}

test('no grid is a 503', function () {
    expect(helpers_get(App::create(static fn(string $nick) => null), '/health')->getStatusCode())->toBe(503);
});

test('the home page and a block are served, the health is closed, the rest is not found', function () {
    $app = helpers_app();

    $home = helpers_get($app, '/');
    expect($home->getStatusCode())->toBe(200);
    expect($home->getHeaderLine('Content-Type'))->toContain('text/html');
    expect(helpers_get($app, '/blocks/motd')->getStatusCode())->toBe(200);
    expect(helpers_get($app, '/blocks/nothing')->getStatusCode())->toBe(404);
    expect(helpers_get($app, '/health')->getStatusCode())->toBe(401);
    expect(helpers_get($app, '/get_grid_info')->getStatusCode())->toBe(200);
    expect(helpers_get($app, '/nothing-here')->getStatusCode())->toBe(404);
});

test('the API is the engine\'s, served by its own entry point: the helpers do not answer /api', function () {
    $app = helpers_app();

    expect(helpers_get($app, '/api/openapi.json')->getStatusCode())->toBe(404);
    expect(helpers_get($app, '/api/grids')->getStatusCode())->toBe(404);
});

test('the pictures of the grid are served at /pictures, by the engine', function () {
    expect(Engine::target([], '/pictures/c14a1985-c549-4485-8779-7103d74ef5e0.jpg'))->toBe('/assets/c14a1985-c549-4485-8779-7103d74ef5e0.jpg');
    expect(Engine::target([], '/pictures/nothing.exe'))->toBeNull();
    // The image of a title, the picture of a place that has none, is asked as any picture
    expect(Engine::target([], '/pictures/title-48616e67206f7574.jpg'))->toBe('/assets/title-48616e67206f7574.jpg');
    expect(Engine::target([], '/pictures/title-Hang.jpg'))->toBeNull();
});

test('the pictures of the grid are served at the address of its web assets too, the helpers\' own files left theirs', function () {
    $paths = ['assets' => '/assets'];

    expect(Engine::target($paths, '/assets/c14a1985-c549-4485-8779-7103d74ef5e0.jpg'))->toBe('/assets/c14a1985-c549-4485-8779-7103d74ef5e0.jpg');
    expect(Engine::target($paths, '/assets/css/guide.css'))->toBeNull();
    expect(Engine::target($paths, '/assets'))->toBeNull();
});

test('a service is found at the address the grid has for it', function () {
    $paths = ['query' => '/q.php', 'offline' => '/helpers/off', 'currency' => '/eco/currency.php'];

    expect(Engine::target($paths, '/q.php'))->toBe('/search/query');
    expect(Engine::target($paths, '/helpers/off/SaveMessage/'))->toBe('/offline-messages/SaveMessage');
    expect(Engine::target($paths, '/eco/currency.php'))->toBe('/currency.php');
    expect(Engine::target($paths, '/query.php'))->toBeNull();
    // Only the two methods of the offline messages: nothing else of the engine through their address
    expect(Engine::target($paths, '/helpers/off/../../grids'))->toBeNull();
    expect(Engine::target($paths, '/helpers/off/RetrieveMessages'))->toBe('/offline-messages/RetrieveMessages');
});

test('the engine answers the helpers only what it says is public, whatever key a request brings', function () {
    $grid = W4OS\Engine\Models\Grid::find((new W4OS\Engine\Testing\Machine())->environment, 'demo');
    $request = (new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/grids')
        ->withAttribute('grid', $grid)
        ->withHeader('Authorization', 'Bearer osk_anything');

    expect(Engine::handler($request)->handle($request)->getStatusCode())->toBe(401);
});

test('a profile that does not exist says so', function () {
    $grid = Grid::at(helpers_environment(), sys_get_temp_dir() . '/demo');
    $data = W4OS\Helpers\Block\Blocks::data('profile', $grid, ['name' => 'Nobody.Here']);

    expect($data['found'])->toBeFalse();
});

test('the assets are served, the theme first, and only known types', function () {
    $app = helpers_app();

    $css = helpers_get($app, '/assets/css/page.css');
    expect($css->getStatusCode())->toBe(200);
    expect($css->getHeaderLine('Content-Type'))->toBe('text/css');
    expect(helpers_get($app, '/assets/../composer.json')->getStatusCode())->toBe(404);
    expect(helpers_get($app, '/assets/css/missing.css')->getStatusCode())->toBe(404);
});

test('every page the site serves is in the OpenAPI document of the engine', function () {
    $paths = array_keys(W4OS\Engine\Api\Contract::document()['paths']);
    $served = ['/search', '/welcome', '/guide', '/profile/{name}', '/health', '/directory_info', '/grid_status', '/grid_status.rss', '/get_grid_info', '/blocks/{name}', '/assets/{file}'];

    expect(array_values(array_diff($served, $paths)))->toBe([]);
});

test('the health takes the API keys of the grid it is given, never the ones of the install of the server', function () {
    $machine = new W4OS\Engine\Testing\Machine();
    $grid = Grid::find($machine->environment, 'demo');
    $key = W4OS\Engine\Security\ApiKeys::for($machine->environment)->create('site')['key'];
    $app = App::create(static fn(string $nick) => $grid);
    $asked = (new ServerRequestFactory())->createServerRequest('GET', '/health')->withHeader('Authorization', "Bearer $key");

    expect($app->handle($asked)->getStatusCode())->not->toBe(401)
        ->and(helpers_get($app, '/health')->getStatusCode())->toBe(401);
});

test('a picture asked at the address of the web assets the helpers serve is the engine\'s, not a missing file of the helpers', function () {
    $machine = new W4OS\Engine\Testing\Machine();
    $machine->write($machine->grid . '/Robust.HG.ini', file_get_contents($machine->grid . '/Robust.HG.ini') . "\n[Const]\n    WebURL = \"https://play.example.org\"\n");
    $machine->write($machine->grid . '/Helpers.ini', "[Helpers]\n    HelpersURL = \"https://play.example.org/helpers\"\n");
    $grid = Grid::find($machine->environment, 'demo');
    $app = App::create(static fn(string $nick) => $grid);

    $picture = helpers_get($app, '/helpers/assets/cb2052ae-d161-43e9-b11b-c834217823cd.jpg');
    $style = helpers_get($app, '/helpers/assets/css/guide.css');

    // The engine's answer, as at /pictures (here its 404 without a picture: the asset is not in the grid), never the page of the helpers
    $pictures = helpers_get($app, '/helpers/pictures/cb2052ae-d161-43e9-b11b-c834217823cd.jpg');

    expect($grid->helperUrl('assets'))->toBe('https://play.example.org/helpers/assets')
        ->and([$picture->getStatusCode(), $picture->getHeaderLine('Content-Type')])->toBe([404, W4OS\Engine\Api\Problem::CONTENT_TYPE])
        ->and([$picture->getStatusCode(), $picture->getHeaderLine('Content-Type')])->toBe([$pictures->getStatusCode(), $pictures->getHeaderLine('Content-Type')])
        ->and($style->getStatusCode())->toBe(200)
        ->and($style->getHeaderLine('Content-Type'))->toStartWith('text/css');
});

test('a picture the grid sends to its external web assets service is sent there, not a missing picture', function () {
    $machine = new W4OS\Engine\Testing\Machine();
    $machine->write($machine->grid . '/Helpers.ini', "[Helpers]\n    HelpersURL = \"https://play.example.org/helpers\"\n    AssetsURL = \"https://assets.example.org/a\"\n");
    $grid = Grid::find($machine->environment, 'demo');
    $picture = helpers_get(App::create(static fn(string $nick) => $grid), '/helpers/pictures/cb2052ae-d161-43e9-b11b-c834217823cd.jpg');

    expect([$picture->getStatusCode(), $picture->getHeaderLine('Location')])
        ->toBe([302, 'https://assets.example.org/a/cb2052ae-d161-43e9-b11b-c834217823cd.jpg']);
})->skip(fn() => W4OS\Engine\Assets\ImagickConverter::available(), 'imagick reads JPEG 2000 here');
