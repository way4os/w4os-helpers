<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Hypergrid\Destination;
use W4OS\Engine\Hypergrid\Reachable;
use W4OS\Engine\I18n\Catalog;
use W4OS\Helpers\I18n;

/**
 * The teleport link of a destination for where a block is shown, asked of it (its tpLink() in Destination::AUTO): it
 * tells a viewer by the user agent, and gives hop:// out of it, the form of the viewer in it (and on its login screen). A block gives the engine what it knows of where it is shown:
 * `client` (the class of the visitor a site gives, `viewer` or `browser`, Destination::client(): it wins), else `agent` (the user
 * agent of the page that shows it, the one of the request without it).
 */
final class Links
{
    /**
     * @param Reachable $place a grid, a region, a parcel, or a place of the grid (Grid::place())
     * @param array<string,mixed> $args the arguments of the block
     * @return ?string null when there is no link to give
     */
    public static function to(Reachable $place, array $args): ?string
    {
        $context = array_filter([
            'agent' => isset($args['agent']) ? (string) $args['agent'] : null,
            'client' => isset($args['client']) ? (string) $args['client'] : null,
        ], static fn(?string $value): bool => $value !== null);
        $link = $place->tpLink(Destination::AUTO, $context);

        return $link === null || $link === '' ? null : $link;
    }

    /**
     * The links of a destination for both clients, as a teleport link that fits where it is seen shows them (W4OS\Views\TeleportLink):
     * its tpLinks() for a browser and for the viewer, each `format`, `link`, `label` (its text in the language in use) and `action`.
     *
     * @return array{browser:list<array{format:int,link:string,label:string,action:string}>,viewer:list<array{format:int,link:string,label:string,action:string}>}
     */
    public static function all(Reachable $place): array
    {
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        $links = [];
        foreach (Destination::CLIENTS as $client) {
            $links[$client] = [];
            foreach ($place->tpLinks($client) as $format => $link) {
                $links[$client][] = ['format' => $format, 'link' => $link['link'], 'label' => (string) ($texts[$link['label']] ?? $link['label']), 'action' => $link['action']];
            }
        }

        return $links;
    }
}
