<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Assets\WebAssets;
use W4OS\Engine\Hypergrid\Destination;
use W4OS\Engine\Models\Grid;

/**
 * The destinations of the grid, by category, as the viewers' guide shows them: a card for each category (the picture of one of its
 * places, its name, how many), then a card for each destination (the picture of its parcel, its name), linked as the viewers open
 * it where it is shown (Links::to(): the engine's AUTO), a region named alone being one of the grid. One category is the guide itself. The
 * engine finds the pictures (Grid::guide()); the block shows them, the grid's placeholder where there is none.
 */
final class DestinationGuideBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        // The sections of the guide with their pictures, the engine's to find (Grid::guide())
        $data = [
            'title' => _('Destinations Guide'),
            'disclaimer' => _('This is a work in progress, please be indulgent.'),
            'placeholder' => WebAssets::PLACEHOLDER,
        ];
        try {
            $sections = $grid->guide();
        } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
            Blocks::unreachable($e);

            return $data + ['unavailable' => true, 'items' => []];
        }
        if ($sections === []) {
            return $data + [
                'reason' => 'no destination: the source of the guide (GuideSource of Helpers.ini, else the guide of 2do.directory) is not readable or empty',
                'text' => _(
                    'The realm of destinations you seek has eluded our grasp, spirited away by elusive knomes. Rally the grid managers, let them venture forth to curate a grand tapestry of remarkable places for your exploration!',
                ),
            ];
        }
        $category = count($sections) === 1 ? array_key_first($sections) : ($args['category'] ?? null);
        $category = isset($sections[$category]) ? $category : null;
        $login = $grid->loginUrl();
        if ($category === null) {
            $items = [];
            foreach ($sections as $name => $section) {
                $count = count($section['destinations']);
                $items[] = [
                    'name' => $name,
                    'url' => '?' . http_build_query(['category' => $name]),
                    'picture' => $section['picture'],
                    'data' => sprintf(ngettext('%s destination', '%s destinations', $count), $count),
                ];
            }

            return $data + ['items' => $items];
        }

        return $data + [
            // The title of the guide may be the page's: the category has its own heading
            'category' => $category,
            'items' => array_map(
                static fn(array $d): array => [
                    // What you go to, then where, as the engine says it (Grid::card()): the title above, its label under it
                    'name' => (string) ($d['title'] ?? $d['name']),
                    'url' => Links::to($grid, Destination::uri($d['url'], $login), $args),
                    'picture' => $d['picture'],
                    'data' => (string) ($d['label'] ?? ''),
                ],
                $sections[$category]['destinations'],
            ),
            'back' => count($sections) > 1 ? ['url' => '?', 'label' => _('Back to categories')] : null,
        ];
    }
}
