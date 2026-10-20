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

test('fixed-format services and the API are the engine\'s', function () {
    $grid = Grid::at(Environment::system(Layout::load()), sys_get_temp_dir() . '/demo');
    $app = App::create(static fn(string $nick) => $grid);

    $api = helpers_get($app, '/api/openapi.json');
    expect($api->getStatusCode())->toBe(200);
    expect(json_decode((string) $api->getBody(), true))->toHaveKey('paths');

    // The former address of the offline messages reaches the engine: the grid has no such database, the answer is its XML failure
    $offline = $app->handle(
        (new ServerRequestFactory())
            ->createServerRequest('POST', '/offline.php/SaveMessage/')
            ->withBody((new Slim\Psr7\Factory\StreamFactory())->createStream('<x/>')),
    );
    expect($offline->getStatusCode())->toBeIn([404, 503]);
    expect((string) $offline->getBody())->toContain('<?xml');
});
