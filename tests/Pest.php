<?php
/**
 * Pest setup. Run the suite with ./vendor/bin/pest.
 *
 * - tests/Environment: what the project needs from its environment (PHP version, compatibility)
 */

// No gatekeeper of another grid is asked over the network (the grids alone of the guides): it does not answer
W4OS\Engine\Hypergrid\Gatekeeper::using(static fn(string $url, string $body): ?string => null);
