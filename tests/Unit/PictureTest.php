<?php

use W4OS\Helpers\View\Picture;

describe('A picture of the grid', function () {
    test('of a person: its picture, else the engine\'s silhouette, faint; never a name or its initials', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/blocks.css');
        $person = W4OS\Engine\Assets\WebAssets::PERSON;

        expect(Picture::tag('/assets/a1b2c3d4-0000-4000-8000-000000000001.jpg', 'person', 'profile-photo'))
            ->toBe(W4OS\Views\Picture::tag('/assets/a1b2c3d4-0000-4000-8000-000000000001.jpg', "/assets/$person.jpg", 'picture profile-photo'))
            ->toContain('class="w4os-picture picture profile-photo"', "this.src='/assets/$person.jpg'")
            // Without a picture (the engine's silhouette, or nothing): the silhouette, at half its opacity
            ->and(Picture::tag("/assets/$person.jpg", 'person', 'profile-photo'))->toContain('w4os-picture-none', "src=\"/assets/$person.jpg\"", 'style="opacity: 0.5"')
            ->and(Picture::tag('/assets/00000000-0000-0000-0000-000000000000.jpg', 'person'))->toContain('w4os-picture-none', "src=\"/assets/$person.jpg\"")
            ->and(Picture::tag('/x.jpg?a=1&b="2"'))->toContain('src="/x.jpg?a=1&amp;b=&quot;2&quot;"')
            ->and($css . (string) file_get_contents(dirname(__DIR__, 2) . '/src/View/Picture.php'))->not->toContain('data-initials', 'initials(')
            ->and($css)->toContain('.picture[hidden] {', 'color-mix(in srgb, currentColor 10%, transparent)');
    });

    test('of a place: its picture, always one (the image of its title when it has none of its own), gone when it cannot be loaded', function () {
        $templates = implode('', array_map(
            static fn(string $name): string => (string) file_get_contents(dirname(__DIR__, 2) . "/templates/components/$name.twig"),
            ['destination_guide-block', 'popular_places-block', 'profile-items'],
        ));

        expect(Picture::tag('/assets/title-50.jpg', null, 'thumbnail'))
            ->toBe('<span class="w4os-picture picture thumbnail"><img src="/assets/title-50.jpg" alt="" loading="lazy" onerror="this.parentNode.hidden=true"></span>')
            // The cards of the places name no one in their picture, nor ask a placeholder
            ->and(preg_match_all('/picture\([^)]*,\s*null,/', $templates))->toBe(3)
            ->and($templates)->not->toContain('placeholder')
            // The thumbnails of the search: the silhouette for a person only
            ->and((string) file_get_contents(dirname(__DIR__, 2) . '/src/View/View.php'))->toContain("\$default === 'person' ? 'person' : null, 'search-picture'");
    });

    test('is the one tag of the pictures of the blocks: no bare img of a picture of the grid', function () {
        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/templates', FilesystemIterator::SKIP_DOTS)) as $file) {
            preg_match('#<img[^>]*\{\{\s*assets\b#', (string) file_get_contents($file->getPathname())) === 1 && ($found[] = $file->getFilename());
        }

        expect($found)->toBe([]);
    });
});
