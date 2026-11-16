<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
use OpenSim\Config\GridConfig;
use OpenSim\Helpers\Http\Action\Engine;
use OpenSim\Health\Health;
use OpenSim\Models\Grid;

/** The diagnosis of the grid, address by address (the checks are the engine's). */
final class HealthBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $config = is_dir($grid->directory())
            ? GridConfig::fromFolder($grid->directory(), ['Helpers' => 'Helpers.ini'])
            : null;
        $groups = Health::answers(
            Health::grid(['config' => $config, 'live' => $grid->gridInfo(), 'login' => (string) $grid->loginUrl()]),
        );
        $groups[] = ['file' => 'PHP', 'title' => '', 'rows' => Health::extensions()];
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
        $seen = [];
        foreach ($groups as &$group) {
            $group['first_of_file'] = !isset($seen[$group['file']]);
            $seen[$group['file']] = true;
        }

        return [
            'title' => _('Health'),
            'groups' => $groups,
            'when' => gmdate('Y-m-d H:i:s') . ' UTC',
            'cert_title' => _('Certificate'),
        ];
    }
}
