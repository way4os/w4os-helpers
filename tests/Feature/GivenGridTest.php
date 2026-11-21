<?php

use W4OS\Engine\Config\GridConfig;
use W4OS\Engine\Config\Layout;
use W4OS\Helpers\App;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Runtime\Environment;
use W4OS\Engine\Security\ApiKeys;

/**
 * The helpers in the folder of a site: the grid is the one the site gives (its settings, its databases over the network), the
 * keys, the cache and the tasks in the folders of the site; nothing read from the machine.
 */
describe('the helpers of a site', function () {
    test('serve the grid the site gives, its health opened by a key of the site', function () {
        $site = sys_get_temp_dir() . '/helpers-site-' . bin2hex(random_bytes(4));
        mkdir($site);
        $environment = Environment::system(new Layout(['EtcRoot' => "$site/etc", 'CacheRoot' => "$site/cache", 'TasksRoot' => "$site/tasks", 'StateRoot' => "$site/state"]));
        $config = new GridConfig(['Robust' => [
            'Const' => ['WebURL' => 'https://site.example.org'],
            'GridInfoService' => ['economy' => 'https://site.example.org/helpers', 'gridname' => 'Far', 'login' => 'http://127.0.0.1:9/'],
        ]]);
        $grid = Grid::described($environment, ['nick' => 'far', 'name' => 'Far', 'remote' => true], $config);
        $app = App::create(static fn(string $nick) => $grid);
        $key = ApiKeys::for($environment)->create('site')['key'];
        $health = static fn(string $token) => $app->handle((new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', '/helpers/health')->withHeader('Authorization', "Bearer $token"));

        expect($health('osk_nothing')->getStatusCode())
            ->toBe(401)
            ->and($health($key)->getStatusCode())
            ->toBe(200)
            // The machine untouched: its configuration never made
            ->and(is_dir("$site/etc"))
            ->toBeFalse();
    });
});
