<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Grid;

/** The destinations of the grid, by category; one category is the guide itself, several are a list of them. */
final class DestinationGuideBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $destinations = $grid->destinations();
        $data = ['title' => _('Destinations Guide'), 'disclaimer' => _('This is a work in progress, please be indulgent.')];
        if ($destinations === []) {
            return $data + [
                'reason' => 'no destination: GuideSource of Helpers.ini is not set, not readable or empty',
                'text' => _(
                    'The realm of destinations you seek has eluded our grasp, spirited away by elusive knomes. Rally the grid managers, let them venture forth to curate a grand tapestry of remarkable places for your exploration!',
                ),
            ];
        }
        $category = count($destinations) === 1 ? array_key_first($destinations) : ($args['category'] ?? null);
        $category = isset($destinations[$category]) ? $category : null;
        $image = '/assets/images/no-img.jpg';
        if ($category === null) {
            $items = [];
            foreach ($destinations as $name => $list) {
                $items[] = [
                    'name' => $name,
                    'url' => '?' . http_build_query(['category' => $name]),
                    'image' => $image,
                    'data' => sprintf(_('%s destinations'), count($list)),
                ];
            }

            return $data + ['items' => $items];
        }

        return $data + [
            'title' => $category,
            'items' => array_map(
                static fn(array $d): array => ['name' => $d['name'], 'url' => $d['url'], 'image' => $image, 'data' => ''],
                $destinations[$category],
            ),
            'back' => count($destinations) > 1 ? ['url' => '?', 'label' => _('Back to categories')] : null,
        ];
    }
}
