<?php
/**
 * Block destination_guide: the destinations of the grid, by category, as the viewers (V3 destination guide) and the browsers show them.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once dirname(__DIR__) . '/class-guide.php';

class OpenSim_Helpers_Block_DestinationGuide
{
    /** The files this block needs in the page. */
    public static function assets()
    {
        return ['css' => ['guide.css'], 'js' => ['guide.js']];
    }

    /**
     * @param  array<string,mixed> $args `category`: the one to list (the query of the page when not given)
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        $source = defined('OPENSIM_GUIDE_SOURCE') && !empty(OPENSIM_GUIDE_SOURCE) ? OPENSIM_GUIDE_SOURCE : $args['source'] ?? null;
        $destinations = OpenSim_Helpers_Guide::parse(OpenSim_Helpers_Guide::read($source));
        $data = ['title' => _('Destinations Guide'), 'disclaimer' => _('This is a work in progress, please be indulgent.')];

        if (empty($destinations)) {
            // The visitor gets the joke, the one who runs the grid gets the reason (here, in the log, and in the health page)
            error_log(
                'opensim-helpers guide: ' .
                    (empty($source)
                        ? 'no source for the guide, set GuideSource in [Helpers] of Helpers.ini (or OPENSIM_GUIDE_SOURCE in config.php)'
                        : 'no destination in the source of the guide, which is not readable or empty: ' . $source),
            );

            return $data + [
                'reason' => 'no destination: GuideSource of Helpers.ini is not set, not readable or empty',
                'text' => _(
                    'The realm of destinations you seek has eluded our grasp, spirited away by elusive knomes. Rally the grid managers, let them venture forth to curate a grand tapestry of remarkable places for your exploration!',
                ),
            ];
        }

        // A single category is the guide itself; with several, the one asked, else the list of them
        $category = count($destinations) === 1 ? array_key_first($destinations) : $args['category'] ?? null;
        $category = isset($destinations[$category]) ? $category : null;
        $thumbnail = OpenSim_Helpers_View::assets_url('images/no-img.jpg');
        $query = array_filter(['source' => $args['source'] ?? null], fn($value) => $value !== null);
        $link = fn($name = null) => '?' . http_build_query($query + ($name === null ? [] : ['category' => $name]));

        if ($category === null) {
            $items = [];
            foreach ($destinations as $name => $list) {
                $items[] = [
                    'name' => $name,
                    'url' => $link($name),
                    'image' => $thumbnail,
                    'data' => sprintf(_('%s destinations'), count($list)),
                ];
            }

            return $data + ['items' => $items];
        }

        $items = [];
        foreach ($destinations[$category] as $destination) {
            $items[] = [
                'name' => $destination['name'],
                'url' => opensim_format_tp($destination['url'], TPLINK_HG),
                'image' => $thumbnail,
                'data' => '',
            ];
        }

        return array_merge($data, [
            'title' => $category,
            'items' => $items,
            'back' => count($destinations) > 1 ? ['url' => $link(), 'label' => _('Back to categories')] : null,
        ]);
    }
}
