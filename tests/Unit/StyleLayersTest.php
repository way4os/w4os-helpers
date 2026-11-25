<?php

declare(strict_types=1);

use W4OS\Helpers\View\View;

/** The styles of the helpers, by their layer: what the file is for (common, web, viewer). */
function style_layer(string $file): string
{
    return preg_match('/\.(web|viewer)\.css$/', $file, $m) === 1 ? $m[1] : 'common';
}

describe('The styles of the helpers', function () {
    $assets = dirname(__DIR__, 2) . '/assets/';

    test('of a block are its common ones, then the ones of its client alone: the web for a browser, the viewer for a viewer', function () use ($assets) {
        foreach (['destination_guide', 'popular_places', 'profile', 'search', 'grid_info', 'map'] as $block) {
            $web = View::blockFiles($block, 'browser')['css'];
            $viewer = View::blockFiles($block, 'viewer')['css'];
            expect(array_map(style_layer(...), $web))->not->toContain('viewer')
                ->and(array_map(style_layer(...), $viewer))->not->toContain('web')
                ->and(View::blockFiles($block)['css'])->toBe($web);
            // A library's from its package (View::LIBRARIES, MapTest), the others among the assets
            foreach (array_merge($web, $viewer) as $file) {
                expect(is_file($assets . $file) || array_filter(array_keys(View::LIBRARIES), fn(string $prefix) => str_starts_with($file, $prefix)) !== [])->toBeTrue($file);
            }
        }

        expect(View::blockFiles('destination_guide', 'viewer')['css'])->toBe(['css/blocks.css', 'css/guide.css', 'css/guide.viewer.css'])
            ->and(View::blockFiles('destination_guide', 'browser')['css'])->toBe(['css/blocks.css', 'css/guide.css', 'css/guide.web.css'])
            ->and(View::blockFiles('search', 'browser')['css'])->toBe(['css/blocks.css', 'css/search.css', 'css/search.web.css']);
    });

    test('of a page are its common ones, then the ones of its client', function () use ($assets) {
        expect(View::pageStyles('viewer'))->toBe(['css/page.css', 'css/page.viewer.css'])
            ->and(View::pageStyles('browser'))->toBe(['css/page.css', 'css/page.web.css'])
            ->and(is_file($assets . 'css/page.viewer.css') && is_file($assets . 'css/page.web.css'))->toBeTrue();
    });

    test('never undo each other: the layer is the client, no rule asks it again (body.viewer, client-viewer, client-browser)', function () use ($assets) {
        foreach (glob($assets . 'css/*.css') as $file) {
            $css = (string) file_get_contents($file);
            expect($css)->not->toMatch('/\.client-(viewer|browser)\b/', basename($file));
            style_layer($file) === 'viewer' || expect($css)->not->toMatch('/body\.viewer\b/', basename($file));
        }
    });

    test('leave the choices of the web to the web: the frame of the blocks, the top bar and the footer, the colours of a page', function () use ($assets) {
        $common = (string) file_get_contents($assets . 'css/page.css');
        $web = (string) file_get_contents($assets . 'css/page.web.css');
        $viewer = (string) file_get_contents($assets . 'css/page.viewer.css');

        expect($common)->not->toContain('.topbar')->not->toMatch('/\.block[^{]*\{[^}]*border:/')->not->toContain('--bg:')
            ->and($web)->toContain('.topbar', '--bg:')->toMatch('/\.block[^{]*\{[^}]*border:/')
            ->and($viewer)->not->toContain('.topbar')->not->toMatch('/\.block[^{]*\{[^}]*border:/')->toContain('--bg:');
    });
});
