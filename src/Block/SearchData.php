<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Config\GridConfig;
use OpenSim\Data\Connections;
use OpenSim\Data\Database;
use OpenSim\Data\OSPDO;
use OpenSim\Hypergrid\Destination;
use OpenSim\Models\Grid;
use OpenSim\Models\Region;

/** What the blocks read of the search database of the grid (the parcels, the events, the classifieds the simulators registered). */
final class SearchData
{
    public readonly ?GridConfig $config;
    private ?OSPDO $db = null;

    public function __construct(public readonly Grid $grid)
    {
        $this->config = is_dir($grid->directory())
            ? GridConfig::fromFolder($grid->directory(), ['Helpers' => 'Helpers.ini'])
            : null;
        $db = $this->config === null ? null : Connections::pdo($this->config, Database::Search);
        $this->db = $db !== null && $db->connected ? $db : null;
    }

    public function available(): bool
    {
        return $this->db !== null;
    }

    /** The table of the regions of the search. */
    public function regions(): string
    {
        return $this->db === null ? 'regionsregister' : Region::searchTable($this->db);
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        $statement = $this->db?->prepareAndExecute($sql, $params);

        return $statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /**
     * The link to a place the search knows, for where the block is shown (Links::to()), as the engine writes it: its grid (the
     * grid's own when the row has none), its region, its landing point (Destination::position()); null without a region.
     *
     * @param array<string,mixed> $args the arguments of the block (`client`, `agent`)
     */
    public function teleport(string $gatekeeper, string $region, string $landing = '', array $args = []): ?string
    {
        $login = (string) $this->grid->loginUrl();
        $grid = $gatekeeper !== '' ? $gatekeeper : $login;
        if ($region === '' || $grid === '') {
            return null;
        }
        $point = Destination::position($landing);
        $uri = Destination::uri(rtrim($grid, '/') . '/' . $region . ($point === '' ? '' : '/' . str_replace(',', '/', $point)), $login !== '' ? $login : null);

        return Links::to($this->grid, $uri, $args);
    }
}
