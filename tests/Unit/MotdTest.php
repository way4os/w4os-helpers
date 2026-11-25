<?php

declare(strict_types=1);

use W4OS\Engine\Models\Grid;
use W4OS\Helpers\Block\Blocks;

describe('The message of the day', function () {
    test('is a title alone: a welcome to the avatar signed in on the site, else the grid\'s own', function () {
        $grid = Grid::at(helpers_environment(), sys_get_temp_dir() . '/demo');
        $signed = Blocks::data('motd', $grid, ['avatar' => 'Way Forest', 'lang' => 'en']);
        $nobody = Blocks::data('motd', $grid, ['lang' => 'en']);
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('motd', $grid, ['avatar' => 'Way Forest', 'lang' => 'en', 'level' => 'h3']);

        expect($signed['title'])->toBe($grid->welcome('Way Forest', 'en'))->toContain('Way Forest')
            ->and($nobody['title'])->toBe($grid->welcome('', 'en'))->not->toContain('Way Forest')
            ->and($html)->toContain('<h3 class="block-title">' . htmlspecialchars($grid->welcome('Way Forest', 'en')) . '</h3>')
            ->not->toContain('Message of the day')->not->toContain('class="motd"');
    });
});
