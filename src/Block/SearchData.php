<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Config\GridConfig;
use W4OS\Engine\Data\Connections;
use W4OS\Engine\Data\Database;
use W4OS\Engine\Data\OSPDO;
use W4OS\Engine\Exceptions\ChannelUnavailable;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Models\Region;

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
}
