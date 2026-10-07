<?php
/**
 * Blocks: the pieces of content a page is made of (grid info, grid status, popular places...). A block gives its data, the
 * template components/<name>-block.twig shows it. A template uses it as a variable (dashes as underscores), `{{ grid_status }}`;
 * a site that embeds the grid reads it as a fragment at <helpers>/blocks/<name> (HTML, or `?format=json` for the data alone).
 * A block has the name it has everywhere else: grid-info, directory_info...
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once __DIR__ . '/class-cache.php';
require_once __DIR__ . '/blocks/class-block-grid-info.php';
require_once __DIR__ . '/blocks/class-block-grid-status.php';
require_once __DIR__ . '/blocks/class-block-motd.php';
require_once __DIR__ . '/blocks/class-block-health.php';
require_once __DIR__ . '/blocks/class-block-destination-guide.php';
require_once __DIR__ . '/blocks/class-block-directory-info.php';
require_once __DIR__ . '/blocks/class-block-popular-places.php';
require_once __DIR__ . '/blocks/class-block-profile.php';
require_once __DIR__ . '/blocks/class-block-search.php';

class OpenSim_Helpers_Blocks
{
    /** The blocks: name => the class that gives their data. */
    const BLOCKS = [
        'grid-info' => 'OpenSim_Helpers_Block_GridInfo',
        'grid-status' => 'OpenSim_Helpers_Block_GridStatus',
        'motd' => 'OpenSim_Helpers_Block_Motd',
        'health' => 'OpenSim_Helpers_Block_Health',
        'destination_guide' => 'OpenSim_Helpers_Block_DestinationGuide',
        'directory_info' => 'OpenSim_Helpers_Block_DirectoryInfo',
        'popular_places' => 'OpenSim_Helpers_Block_PopularPlaces',
        'profile' => 'OpenSim_Helpers_Block_Profile',
        'search' => 'OpenSim_Helpers_Block_Search',
    ];

    /** The css and js files the blocks shown in this request need, for the layout to link them in its head. */
    private static $assets = ['css' => [], 'js' => []];

    /** The levels a title can have. */
    const LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'];

    public static function exists($name)
    {
        return isset(self::BLOCKS[$name]);
    }

    /**
     * The block a page shows: the one of its slug, which may have dashes where the slug has underscores (the page grid_status
     * shows the block grid-status).
     *
     * @return string|null null when the slug is no block
     */
    public static function name($slug)
    {
        return self::exists($slug) ? $slug : self::from_variable($slug);
    }

    /**
     * The name of a block from the name of a variable of a template (grid_info is grid-info, directory_info is itself).
     *
     * @return string|null null when no block is called so
     */
    public static function from_variable($variable)
    {
        foreach (array_keys(self::BLOCKS) as $name) {
            if (str_replace('-', '_', $name) === $variable) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The files the blocks shown so far need.
     *
     * @return array{css:string[],js:string[]}
     */
    public static function assets()
    {
        return self::$assets;
    }

    /**
     * What a block shows: a title and rows, or a text when it has nothing to show.
     *
     * @param  string               $name
     * @param  array<string,mixed>  $args title, level, what the block takes
     * @return array<string,mixed>|null null for a block that does not exist
     */
    public static function data($name, $args = [])
    {
        if (!self::exists($name)) {
            return null;
        }
        $data = (self::BLOCKS[$name])::data($args);
        $level = $args['level'] ?? 'h3';
        $data['level'] = in_array($level, self::LEVELS, true) ? $level : 'h3';
        // A title of the call wins, an empty one leaves the title out
        $data['title'] = array_key_exists('title', $args) ? $args['title'] : $data['title'] ?? null;
        $data['name'] = $name;

        return $data;
    }

    /**
     * The block as an HTML fragment, empty for a block that does not exist or has nothing to show.
     *
     * @param  string               $name
     * @param  array<string,mixed>  $args
     * @param  string[]|null        $dirs
     * @return string
     */
    public static function render($name, $args = [], $dirs = null)
    {
        $data = self::data($name, $args);
        if (
            $data === null ||
            (empty($data['rows']) &&
                empty($data['groups']) &&
                empty($data['items']) &&
                empty($data['text']) &&
                empty($data['summary']) &&
                // A form is something to show, and what a page is about that is not there is a page that says so
                empty($data['form']) &&
                !array_key_exists('found', $data))
        ) {
            return '';
        }
        $class = self::BLOCKS[$name];
        if (method_exists($class, 'assets')) {
            foreach ($class::assets() as $kind => $files) {
                self::$assets[$kind] = array_values(array_unique(array_merge(self::$assets[$kind], $files)));
            }
        }

        return OpenSim_Helpers_View::render("components/$name-block.twig", $data, $dirs);
    }
}
