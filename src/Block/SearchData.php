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

    /** The link a viewer follows to go to a place (`hop://`), null without a region. */
    public function hop(string $gatekeeper, string $region, string $landing = ''): ?string
    {
        $host = preg_replace('#^https?://#i', '', rtrim($gatekeeper !== '' ? $gatekeeper : (string) $this->grid->loginUrl(), '/'));
        if ($region === '' || $host === '') {
            return null;
        }
        $at = preg_match('/([\d.]+)\s*[\/,]\s*([\d.]+)\s*[\/,]\s*([\d.]+)/', $landing, $m)
            ? sprintf('/%d/%d/%d', (float) $m[1], (float) $m[2], (float) $m[3])
            : '';

        return (string) Destination::format("$host:$region$at", 8);
    }
}
