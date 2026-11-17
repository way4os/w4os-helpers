<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
use OpenSim\Hypergrid\Destination;
use OpenSim\Models\Grid;

/**
 * The destinations of the grid, by category, as the viewers' guide shows them: a card for each category (the picture of one of its
 * places, its name, how many), then a card for each destination (the picture of its parcel, its name), linked as the viewers open
 * it (secondlife://host:port Region/x/y/z), a region named alone being one of the grid. One category is the guide itself.
 */
final class DestinationGuideBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $destinations = $grid->destinations();
        $data = [
            'title' => _('Destinations Guide'),
            'disclaimer' => _('This is a work in progress, please be indulgent.'),
            'placeholder' => WebAssets::PLACEHOLDER,
        ];
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
        $search = new SearchData($grid);
        $login = $grid->loginUrl();
        $place = static fn(array $d): array => [
            'name' => $d['name'],
            'url' => Destination::format(Destination::uri($d['url'], $login), Destination::HG),
            'picture' => $search->picture($d['url'], $d['name']),
            'data' => '',
        ];
        if ($category === null) {
            $items = [];
            foreach ($destinations as $name => $list) {
                // The picture of a category: the first of its places that has one
                $picture = null;
                foreach ($list as $d) {
                    $picture ??= $search->picture($d['url'], $d['name']);
                }
                $items[] = [
                    'name' => $name,
                    'url' => '?' . http_build_query(['category' => $name]),
                    'picture' => $picture,
                    'data' => sprintf(_('%s destinations'), count($list)),
                ];
            }

            return $data + ['items' => $items];
        }

        return $data + [
            // The title of the guide may be the page's: the category has its own heading
            'category' => $category,
            'items' => array_map($place, $destinations[$category]),
            'back' => count($destinations) > 1 ? ['url' => '?', 'label' => _('Back to categories')] : null,
        ];
    }
}
