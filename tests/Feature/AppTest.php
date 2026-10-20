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
