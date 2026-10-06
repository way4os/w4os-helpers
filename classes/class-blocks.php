<?php
/**
 * Blocks: the pieces of content a page is made of (grid info, grid status, popular places...). A block gives its data, a
 * template-block-<name>.twig shows it. A template uses it as a variable, `{{ grid_status }}`; a site that embeds the grid reads
 * it as a fragment at <helpers>/blocks/<name> (HTML, or `?format=json` for the data alone).
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once __DIR__ . '/class-cache.php';
require_once __DIR__ . '/blocks/class-block-grid-info.php';
require_once __DIR__ . '/blocks/class-block-grid-status.php';
require_once __DIR__ . '/blocks/class-block-motd.php';
require_once __DIR__ . '/blocks/class-block-health.php';

class OpenSim_Helpers_Blocks
{
    /** The blocks: name => the class that gives their data. */
    const BLOCKS = [
        'grid-info' => 'OpenSim_Helpers_Block_GridInfo',
        'grid-status' => 'OpenSim_Helpers_Block_GridStatus',
        'motd' => 'OpenSim_Helpers_Block_Motd',
        'health' => 'OpenSim_Helpers_Block_Health',
    ];

    /** The levels a title can have. */
    const LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'];

    public static function exists($name)
    {
        return isset(self::BLOCKS[$name]);
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
        if ($data === null || (empty($data['rows']) && empty($data['groups']) && empty($data['items']) && empty($data['text']))) {
            return '';
        }

        return OpenSim_Helpers_View::render("template-block-$name.twig", $data, $dirs);
    }
}
