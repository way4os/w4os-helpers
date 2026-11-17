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
use OpenSim\Text\Uuid;

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
     * The picture of the parcel a destination leads to (its snapshot, as the search has it), null when the search does not know
     * that parcel or it has none: the parcel of that name in the region, else the one whose landing point it is, else the only
     * parcel of the region; never one of another parcel of the region.
     *
     * @param string $destination host:port:Region/x/y/z, host:port/Region..., or a region of the grid by its name
     */
    public function picture(string $destination, string $name = ''): ?string
    {
        if ($this->db === null) {
            return null;
        }
        $login = (string) $this->grid->loginUrl();
        $at = Destination::parse($destination, $login !== '' ? $login : null);
        if ($at['region'] === '' || $at['host'] === '') {
            return null;
        }
        $gatekeeper = "http://{$at['host']}:{$at['port']}";
        // The tables keep the address with or without its last slash; the places of the grid itself may have none
        $own = Destination::loginParts($login);
        $local = $own !== null && strtolower($own['host']) === $at['host'] && (string) ($own['port'] ?? 80) === (string) $at['port'];
        $parcels = $this->rows(
            "SELECT p.parcelname AS name, p.landingpoint AS landing, p.imageUUID AS picture FROM parcels AS p INNER JOIN {$this->regions()} AS r ON r.regionUUID = p.regionUUID WHERE LOWER(r.regionname) = LOWER(:region) AND p.gatekeeperURL IN (:one, :two, :three)",
            ['region' => $at['region'], 'one' => $gatekeeper, 'two' => "$gatekeeper/", 'three' => $local ? '' : $gatekeeper],
        );
        $point = static fn(string $at): string => implode('/', array_map(static fn($v) => (string) round((float) $v), array_slice(preg_split('#[/,]#', $at) ?: [], 0, 2)));
        $found = null;
        foreach ($parcels as $parcel) {
            if ($name !== '' && strcasecmp((string) $parcel['name'], $name) === 0) {
                $found = $parcel;
                break;
            }
            if ($at['pos'] !== '' && $point((string) $parcel['landing']) === $point($at['pos'])) {
                $found ??= $parcel;
            }
        }
        $found ??= count($parcels) === 1 ? $parcels[0] : null;
        $picture = (string) ($found['picture'] ?? '');

        return $picture === '' || in_array($picture, Uuid::NULL_KEYS, true) ? null : $picture;
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

        return (string) Destination::format("$host/$region$at", Destination::HOP);
    }
}
