<?php
/**
 * What a grid tells about itself at its login address (`/get_grid_info`), as the pages show it: its name, whether it answers.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once __DIR__ . '/class-cache.php';

class OpenSim_Helpers_GridInfo
{
    /** How long what a grid said is kept: a day for a grid that answered, a minute for one that did not. */
    const TTL_ONLINE = 86400;
    const TTL_OFFLINE = 60;

    /**
     * The information of a grid, kept for a while.
     *
     * @param  string $uri the login address of the grid (http://host:port)
     * @return array<string,mixed> `online`, `login`, `gridname` (the address when the grid does not say), and what the grid tells
     */
    public static function get($uri)
    {
        // A grid that did not answer is asked again soon, not tomorrow
        return OpenSim_Helpers_Cache::remember(
            'grid-info-' . $uri,
            fn($info) => empty($info['online']) ? self::TTL_OFFLINE : self::TTL_ONLINE,
            fn() => self::fetch($uri),
        );
    }

    /**
     * Ask a grid.
     *
     * @param  string $uri
     * @return array<string,mixed>
     */
    public static function fetch($uri)
    {
        $info = ['online' => false, 'login' => $uri];
        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        set_error_handler(static fn() => true);
        $body = file_get_contents(rtrim((string) $uri, '/') . '/get_grid_info', false, $context);
        restore_error_handler();
        $xml = $body ? @simplexml_load_string($body) : false;
        if ($xml) {
            $info = ['online' => true] + array_map('strval', (array) $xml) + $info;
        }
        if (empty($info['gridname'])) {
            $info['gridname'] = opensim_format_tp($uri, TPLINK_TXT);
        }

        return $info;
    }
}
