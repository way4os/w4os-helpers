<?php

use W4OS\Helpers\HelpersLayout;

/** A folder the tests make, closed or open to this user. */
function helpers_layout_folder(bool $writable): string
{
    $folder = sys_get_temp_dir() . '/helpers-layout-' . bin2hex(random_bytes(4));
    mkdir($folder, $writable ? 0o755 : 0o555, true);

    return $folder;
}

describe('The layout of the helpers', function () {
    test('keeps the state of the tasks and the cache where the web server can write, when the install\'s cannot be', function () {
        $install = helpers_layout_folder(false);
        $own = helpers_layout_folder(true);
        mkdir("$own/tasks");
        mkdir("$own/cache");
        // The folders of the install, as w4os.conf says them, which the web server cannot write
        $conf = helpers_layout_folder(true) . '/w4os.conf';
        file_put_contents($conf, "[Defaults]\nVarRoot = $install/var\nCacheRoot = $install/cache\n");
        $server = ['OPENSIM_CONF' => $conf];

        $layout = HelpersLayout::resolve($server, $own);
        $given = HelpersLayout::resolve($server + ['OPENSIM_TASKS_ROOT' => "$install/tasks"], $own);
        $nowhere = HelpersLayout::resolve($server, helpers_layout_folder(false));
        chmod($install, 0o755);

        expect([$layout->tasksRoot(), $layout->cacheRoot()])
            ->toBe(["$own/tasks", "$own/cache"])
            ->and($given->tasksRoot())
            ->toBe("$install/tasks")
            ->and([$nowhere->tasksRoot(), $nowhere->cacheRoot()])
            ->toBe(["$install/var/cron", "$install/cache"]);
    })->skip(fn() => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root writes everywhere');

    test('takes the settings the web server gives (FastCGI parameters) over the ones of the process', function () {
        $folder = helpers_layout_folder(true);

        expect(HelpersLayout::resolve(['OPENSIM_CONF' => '', 'OPENSIM_TASKS_ROOT' => "$folder/tasks"], $folder)->tasksRoot())
            ->toBe("$folder/tasks");
    });
});
