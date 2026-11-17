<?php

declare(strict_types=1);

use OpenSim\Config\Layout;
use OpenSim\Helpers\App;
use OpenSim\Helpers\Http\Action\Engine;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use Slim\Psr7\Factory\ServerRequestFactory;

function helpers_get(Slim\App $app, string $path): Psr\Http\Message\ResponseInterface
{
    return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
}

function helpers_app(): Slim\App
{
    $grid = Grid::at(Environment::system(Layout::load()), sys_get_temp_dir() . '/demo');

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
    $grid = OpenSim\Models\Grid::find((new OpenSim\Testing\Machine())->environment, 'demo');
    $request = (new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/grids')
        ->withAttribute('grid', $grid)
        ->withHeader('Authorization', 'Bearer osk_anything');

    expect(Engine::handler($request)->handle($request)->getStatusCode())->toBe(401);
});

test('a profile that does not exist says so', function () {
    $grid = Grid::at(Environment::system(Layout::load()), sys_get_temp_dir() . '/demo');
    $data = OpenSim\Helpers\Block\Blocks::data('profile', $grid, ['name' => 'Nobody.Here']);

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
    $paths = array_keys(OpenSim\Api\Contract::document()['paths']);
    $served = ['/search', '/welcome', '/guide', '/profile/{name}', '/health', '/directory_info', '/grid_status', '/grid_status.rss', '/get_grid_info', '/blocks/{name}', '/assets/{file}'];

    expect(array_values(array_diff($served, $paths)))->toBe([]);
});
