<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Assets\WebAssets;
use W4OS\Helpers\Http\Action\Engine;
use W4OS\Helpers\I18n;
use W4OS\Engine\Health\Health;
use W4OS\Engine\Models\Grid;

/** The diagnosis of the grid, address by address (the checks are the engine's). */
final class HealthBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $config = $grid->config();
        $groups = Health::answers(
            Health::grid(['config' => $config, 'live' => $grid->gridInfo(), 'login' => (string) $grid->loginUrl()]),
        );
        $groups[] = ['file' => 'PHP', 'title' => '', 'rows' => Health::extensions()];
        // The copy of the guide of another server, its age and the last failure of its server
        ($copy = $grid->guideCopy()) === null || ($groups[] = ['file' => 'guide', 'title' => '', 'rows' => [Health::guideCopy($copy, time())]]);
        // The pools of the suite, each reached by the web server or not
        ($pools = Health::pools()) === [] || ($groups[] = ['file' => 'PHP-FPM', 'title' => '', 'rows' => $pools]);
        $files = Health::files($config?->files() ?? []);
        $files === [] || ($groups[] = ['file' => 'files', 'title' => '', 'rows' => $files]);
        // The folders the helpers write as the web server: the pictures, and the state of the tasks (without it the search index never fills)
        $groups[] = [
            'file' => 'cache',
            'title' => '',
            'rows' => [
                Health::writable('assets', WebAssets::folder($grid)),
                Health::writable('tasks', Engine::scheduler($grid)->directory()),
            ],
        ];
        // What the health says, in the language of the page
        $groups = Health::translate(Health::withTexts($groups), I18n::current());
        $seen = [];
        foreach ($groups as &$group) {
            $group['first_of_file'] = !isset($seen[$group['file']]);
            $seen[$group['file']] = true;
        }

        return [
            'title' => I18n::text('Diagnostics'),
            'groups' => $groups,
            'when' => I18n::date(time(), 'long', 'UTC') . ' UTC',
            'cert_title' => I18n::text('Certificate'),
        ];
    }
}
