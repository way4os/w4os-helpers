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

test('the API is the engine\'s', function () {
    $app = helpers_app();

    $api = helpers_get($app, '/api/openapi.json');
    expect($api->getStatusCode())->toBe(200);
    expect(json_decode((string) $api->getBody(), true))->toHaveKey('paths');

});

test('a service is found at the address the grid has for it', function () {
    $paths = ['query' => '/q.php', 'offline' => '/helpers/off', 'currency' => '/eco/currency.php'];

    expect(Engine::target($paths, '/q.php'))->toBe('/search/query');
    expect(Engine::target($paths, '/helpers/off/SaveMessage/'))->toBe('/offline-messages/SaveMessage');
    expect(Engine::target($paths, '/eco/currency.php'))->toBe('/currency.php');
    expect(Engine::target($paths, '/query.php'))->toBeNull();
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
