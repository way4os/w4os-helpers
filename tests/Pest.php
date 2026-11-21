<?php
/**
 * Pest setup. Run the suite with ./vendor/bin/pest.
 *
 * - tests/Environment: what the project needs from its environment (PHP version, compatibility)
 */

// No gatekeeper of another grid is asked over the network (the grids alone of the guides): it does not answer
W4OS\Engine\Hypergrid\Gatekeeper::using(static fn(string $url, string $body): ?string => null);

/** The environment of this system, its cache in a temporary folder: a test never writes in the caches of the machine. */
function helpers_environment(): W4OS\Engine\Runtime\Environment
{
    $layout = W4OS\Engine\Config\Layout::load();

    return W4OS\Engine\Runtime\Environment::system(new W4OS\Engine\Config\Layout(['CacheRoot' => sys_get_temp_dir() . '/helpers-tests-cache'] + $layout->all(), $layout->file(), $layout->profile(), $layout->grids()));
}
