<?php

declare(strict_types=1);

namespace OpenSim\Helpers;

use OpenSim\Config\Layout;
use OpenSim\Support\FileCache;

/**
 * The layout of the install, as the helpers see it: the `OPENSIM_*` settings the web server gives them (FastCGI parameters,
 * `SetEnv`) and the ones of the process over opensim.conf (Layout::resolve()). The helpers run as the user of the web server, who
 * cannot write the folders of the install (they are its account's): the state of the tasks (`TasksRoot`) and the cache
 * (`CacheRoot`) are then kept in the helpers' own folder, which their package makes for the web server. A setting the web server
 * gives wins; a folder nobody can write is told by the health page.
 *
 *     Environment::system(HelpersLayout::resolve($_SERVER));
 */
final class HelpersLayout
{
    /** The folder of the helpers the web server writes: `tasks` and `cache` in it (see packaging/opensim-helpers.postinst). */
    public const FOLDER = '/var/cache/opensim-helpers';

    /**
     * @param array<string,mixed> $server the parameters of the request ($_SERVER), over the environment of the process
     * @param string $folder the helpers' own folder (FOLDER)
     */
    public static function resolve(array $server = [], string $folder = self::FOLDER): Layout
    {
        $environment = array_filter($server + getenv(), 'is_string');
        $layout = Layout::resolve($environment);
        $own = [];
        foreach (['TasksRoot' => $layout->tasksRoot(), 'CacheRoot' => $layout->cacheRoot()] as $key => $current) {
            $given = (string) ($environment[Layout::environmentName($key)] ?? '') !== '';
            $mine = $folder . '/' . ($key === 'TasksRoot' ? 'tasks' : 'cache');
            if (!$given && !(new FileCache($current))->writable() && (new FileCache($mine))->writable()) {
                $own[$key] = $mine;
            }
        }

        return $own === [] ? $layout : new Layout($own + $layout->all(), $layout->file(), $layout->profile());
    }
}
