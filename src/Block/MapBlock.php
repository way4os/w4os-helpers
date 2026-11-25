<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\I18n\Catalog;
use W4OS\Engine\Models\Grid;
use W4OS\Helpers\I18n;

/**
 * The map of the grid: its tiles in a browser (js/map.js, Leaflet), the list of its regions with their teleport links without a
 * script and in a viewer (it has its own world map). The regions and their places on the map are the engine's (Grid::mapRegions():
 * region units, the bounds and the center of the grid), its tiles too, at the address the site gives (`map_tiles`), else under the
 * helpers (`/map/{z}/{x}/{y}.jpg`). Nothing when the grid has no map, or no region.
 */
final class MapBlock implements Block
{
    /** The size of a region on the map, in meters: one tile of the closest zoom. */
    public const REGION = 256;

    public function data(Grid $grid, array $args = []): array
    {
        try {
            $map = $grid->mapRegions();
        } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
            Blocks::unreachable($e);

            return ['title' => self::title(), 'regions' => [], 'unavailable' => true];
        }
        if ($map === null) {
            return [];
        }
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        $regions = array_map(static fn(array $region): array => [
            'name' => (string) ($region['name'] ?? ''),
            'x' => (int) ($region['x'] ?? 0),
            'y' => (int) ($region['y'] ?? 0),
            // Its size in regions (a varregion covers several)
            'width' => max(1, intdiv((int) ($region['sizeX'] ?? self::REGION), self::REGION)),
            'height' => max(1, intdiv((int) ($region['sizeY'] ?? self::REGION), self::REGION)),
            'online' => (bool) ($region['online'] ?? true),
            'links' => self::links((array) ($region['tpLinks'] ?? []), $texts),
        ], array_values(array_filter((array) ($map['regions'] ?? []), 'is_array')));
        // A map without a region shows nothing either
        if ($regions === []) {
            return [];
        }
        usort($regions, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $base = trim((string) ($args['map_tiles'] ?? ''));
        $base = $base !== '' ? $base : rtrim((string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH), '/') . '/map';

        return [
            'title' => self::title(),
            'regions' => $regions,
            'tiles' => rtrim($base, '/') . '/{z}/{x}/{y}.jpg',
            'bounds' => (array) ($map['bounds'] ?? []),
            'center' => (array) ($map['center'] ?? []),
            'texts' => ['zoom_in' => I18n::text('Zoom in'), 'zoom_out' => I18n::text('Zoom out'), 'find_region' => I18n::text('Find a region'), 'no_region' => I18n::text('No region found'), 'location' => I18n::text('Location', 'map'), 'free_location' => I18n::text('Free location', 'map')],
            // The whole height left in the window, when the map is the page (`fill`)
            'fill' => in_array(strtolower(trim((string) ($args['fill'] ?? ''))), ['true', '1', 'yes', 'on'], true),
        ];
    }

    /** Its title, the engine's (the title of the map of the sites too), in the language of the page. */
    private static function title(): string
    {
        return (string) ((Catalog::messages(I18n::current()) + Catalog::MESSAGES)['map_title'] ?? 'Map');
    }

    /**
     * The teleport links of a region, as the engine gives them by client, their labels in the language of the page (as Links::all()).
     *
     * @param array<string,mixed> $links
     * @param array<string,string> $texts
     * @return array<string,list<array<string,mixed>>>
     */
    private static function links(array $links, array $texts): array
    {
        $all = [];
        foreach ($links as $client => $list) {
            foreach ((array) $list as $format => $link) {
                $label = is_array($link) ? (string) ($link['label'] ?? '') : '';
                is_array($link) && ($all[(string) $client][] = ['format' => (string) ($link['format'] ?? $format), 'label' => (string) ($texts[$label] ?? $label)] + $link);
            }
        }

        return $all;
    }
}
