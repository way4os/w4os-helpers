<?php

declare(strict_types=1);

use W4OS\Helpers\App;
use W4OS\Helpers\View\View;
use W4OS\Engine\Models\Grid;
use Slim\Psr7\Factory\ServerRequestFactory;

/** The answer of the helpers of a demo grid to a GET. */
function notices_get(string $path): Psr\Http\Message\ResponseInterface
{
    $grid = Grid::at(helpers_environment(), sys_get_temp_dir() . '/demo');

    return App::create(static fn(string $nick) => $grid)->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
}

// The notices other tests left are not this one's
beforeEach(fn() => View::notices());
afterEach(fn() => View::notices());

describe('The notices to the visitor', function () {
    test('show at the start of the content of a page, once', function () {
        View::notify('warning', 'A new viewer is out.');
        View::notify('shout', 'Unknown levels are information.');
        View::notify('error', ' ');
        $page = (string) notices_get('/')->getBody();

        expect($page)->toContain('<div class="notices" role="status">', '<p class="notice notice-warning">A new viewer is out.</p>', '<p class="notice notice-info">Unknown levels are information.</p>')
            ->not->toContain('notice-error')
            ->and(strpos($page, 'class="notices"'))->toBeGreaterThan(strpos($page, '<main id="main">'))
            ->and((string) notices_get('/')->getBody())->not->toContain('class="notices"');
    });

    test('of a block go with it, for the zone of messages of the site that shows it', function () {
        View::notify('error', 'The search is closed.');
        $block = notices_get('/blocks/motd');

        expect(json_decode($block->getHeaderLine('X-Helpers-Notices'), true))->toBe([['level' => 'error', 'text' => 'The search is closed.']])
            ->and((string) $block->getBody())->not->toContain('notice')
            ->and(notices_get('/blocks/motd')->hasHeader('X-Helpers-Notices'))->toBeFalse();
    });
});
