<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Hypergrid\Destination;

/**
 * The teleport link of a destination for where a block is shown, as the engine writes it (Destination::AUTO): in a viewer (its
 * browser, its login screen), a local secondlife:// link for a region of the grid and the viewer's teleport for another grid; out of
 * it, hop://. A block knows where it is shown from its arguments: `viewer` (the page asked by a viewer, View::isViewer(), or a site
 * that says so), `splash` (the login screen).
 */
final class Links
{
    /**
     * @param string $destination host:port/Region/x/y/z, host:port:Region, hop://..., a region of the grid by its name
     * @param ?string $login the login address of the grid, for its own regions
     * @param array<string,mixed> $args the arguments of the block
     * @return ?string null when there is no link to give
     */
    public static function to(string $destination, ?string $login, array $args): ?string
    {
        $link = (string) Destination::format($destination, Destination::AUTO, "\n", [
            'viewer' => !empty($args['viewer']),
            'splash' => !empty($args['splash']),
            'login' => (string) $login,
        ]);

        return $link === '' ? null : $link;
    }
}
