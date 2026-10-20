<?php

declare(strict_types=1);

use OpenSim\Config\Layout;
use OpenSim\Helpers\App;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use Slim\Psr7\Factory\ServerRequestFactory;

function helpers_get(Slim\App $app, string $path): Psr\Http\Message\ResponseInterface
{
    return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
}

test('health answers for the grid found', function () {
    $grid = Grid::at(Environment::system(Layout::load()), sys_get_temp_dir() . '/demo');
    $response = helpers_get(App::create(static fn(string $nick) => $grid), '/health');

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string) $response->getBody(), true))->toBe(['status' => 'ok', 'grid' => 'demo']);
});

test('no grid is a 503', function () {
    expect(helpers_get(App::create(static fn(string $nick) => null), '/health')->getStatusCode())->toBe(503);
});

test('home page and grid info are served', function () {
    $grid = Grid::at(Environment::system(Layout::load()), sys_get_temp_dir() . '/demo');
    $app = App::create(static fn(string $nick) => $grid);

    $home = helpers_get($app, '/');
    expect($home->getStatusCode())->toBe(200);
    expect($home->getHeaderLine('Content-Type'))->toContain('text/html');
    $info = helpers_get($app, '/get_grid_info');
    expect($info->getStatusCode())->toBe(200);
    expect(simplexml_load_string((string) $info->getBody()))->not->toBeFalse();
});
