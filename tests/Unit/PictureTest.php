<?php

use W4OS\Helpers\View\Picture;

describe('A picture of the grid', function () {
    test('is never broken: the initials of its name under it, the picture hidden when it cannot be loaded', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/blocks.css');

        expect(Picture::tag('/assets/a1.jpg', 'Ada Love', 'profile-photo'))
            ->toBe('<span class="picture profile-photo" data-initials="AL"><img src="/assets/a1.jpg" alt="" loading="lazy" onerror="this.hidden=true"></span>')
            ->and(Picture::tag('/x.jpg?a=1&b="2"', '<B>'))->toContain('class="picture"', 'src="/x.jpg?a=1&amp;b=&quot;2&quot;"', 'data-initials="&lt;"')
            ->and(Picture::initials('Bart.Simpson'))->toBe('BS')
            ->and(Picture::initials('plaza'))->toBe('P')
            ->and(Picture::initials('Élodie de la Tour'))->toBe('ÉD')
            ->and(Picture::initials(null))->toBe('')
            ->and($css)->toContain('content: attr(data-initials);', '.picture img[hidden] {', 'color-mix(in srgb, currentColor 10%, transparent)');
    });

    test('of a place has no initials: its picture is always one, the image of its title when it has none of its own', function () {
        $templates = implode('', array_map(
            static fn(string $name): string => (string) file_get_contents(dirname(__DIR__, 2) . "/templates/components/$name.twig"),
            ['destination_guide-block', 'popular_places-block', 'profile-items'],
        ));

        expect(Picture::tag('/assets/title-50.jpg', null, 'thumbnail'))
            ->toBe('<span class="picture thumbnail"><img src="/assets/title-50.jpg" alt="" loading="lazy" onerror="this.hidden=true"></span>')
            // The cards of the places name no one in their picture, nor ask a placeholder
            ->and(preg_match_all('/picture\([^)]*,\s*null,/', $templates))->toBe(3)
            ->and($templates)->not->toContain('placeholder')
            // The thumbnails of the search: initials for a person only
            ->and((string) file_get_contents(dirname(__DIR__, 2) . '/src/View/View.php'))->toContain("\$default === 'person' ? \$name : null, 'search-picture'");
    });

    test('is the one tag of the pictures of the blocks: no bare img of a picture of the grid', function () {
        $found = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/templates', FilesystemIterator::SKIP_DOTS)) as $file) {
            preg_match('#<img[^>]*\{\{\s*assets\b#', (string) file_get_contents($file->getPathname())) === 1 && ($found[] = $file->getFilename());
        }

        expect($found)->toBe([]);
    });
});
