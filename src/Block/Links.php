<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Grid;

/**
 * The teleport link of a destination for where a block is shown, the engine's to choose (Grid::link(), Destination::AUTO): it
 * tells a viewer by the user agent, and gives hop:// out of it, the form of the viewer in it (and on its login screen). A block gives the engine what it knows of where it is shown:
 * `client` (the class of the visitor a site gives, `viewer` or `browser`, Destination::client(): it wins), else `agent` (the user
 * agent of the page that shows it, the one of the request without it), and `splash` (the login screen).
 */
final class Links
{
    /**
     * @param string $destination host:port/Region/x/y/z, host:port:Region, hop://..., a region of the grid by its name
     * @param array<string,mixed> $args the arguments of the block
     * @return ?string null when there is no link to give
     */
    public static function to(Grid $grid, string $destination, array $args): ?string
    {
        $agent = isset($args['agent']) ? (string) $args['agent'] : null;
        $client = isset($args['client']) ? (string) $args['client'] : null;
        $link = $grid->link($destination, $agent, !empty($args['splash']), $client);

        return $link === '' ? null : $link;
    }
}
