<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Config\GridConfig;
use OpenSim\Data\Connections;
use OpenSim\Data\Database;
use OpenSim\Data\OSPDO;
use OpenSim\Exceptions\ChannelUnavailable;
use OpenSim\Hypergrid\Destination;
use OpenSim\Models\Grid;
use OpenSim\Models\Region;

/**
 * What the blocks read of the search database of the grid (the parcels, the events, the classifieds the simulators registered). A
 * grid that sets no search has none to read; one whose search cannot be reached or read is unavailable (ChannelUnavailable), never
 * an empty answer.
 */
final class SearchData
{
    public readonly ?GridConfig $config;
    private ?OSPDO $db = null;
    /** Why the search the grid sets cannot be reached, null when it can or when the grid sets none */
    private ?string $unreachable = null;

    public function __construct(public readonly Grid $grid)
    {
        $this->config = $grid->config();
        $db = $this->config === null ? null : Connections::pdo($this->config, Database::Search);
        $this->db = $db !== null && $db->connected ? $db : null;
        if ($this->db === null && $this->config !== null && Connections::key($this->config, Database::Search) !== null) {
            $this->unreachable = Connections::reason($this->config, Database::Search) ?? 'the database cannot be reached';
        }
    }

    public function available(): bool
    {
        return $this->db !== null;
    }

    /**
     * The table of the regions of the search.
     *
     * @throws ChannelUnavailable when the search the grid sets cannot be reached
     */
    public function regions(): string
    {
        $this->reachable();

        return $this->db === null ? 'regionsregister' : Region::searchTable($this->db);
    }

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>> none when the grid sets no search
     * @throws ChannelUnavailable when the search the grid sets cannot be reached or read
     */
    public function rows(string $sql, array $params = []): array
    {
        $this->reachable();
        if ($this->db === null) {
            return [];
        }
        $statement = $this->db->prepareAndExecute($sql, $params);
        $statement || throw ChannelUnavailable::for('read the search', 'the query failed');

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @throws ChannelUnavailable when the search the grid sets cannot be reached */
    private function reachable(): void
    {
        $this->unreachable === null || throw ChannelUnavailable::for('read the search', $this->unreachable);
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
