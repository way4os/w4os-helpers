<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Helpers\I18n;
use W4OS\Engine\Models\Avatar;
use W4OS\Engine\Models\Grid;

/** The web search: places, land, events, classifieds and people. */
final class SearchBlock implements Block
{
    public const TOPICS = ['places', 'land', 'events', 'classifieds', 'people'];
    public const LIMIT = 30;

    /** Whether a part of the search could not be done (a database of the grid that cannot be reached) */
    private bool $unavailable = false;

    public function data(Grid $grid, array $args = []): array
    {
        $this->unavailable = false;
        $search = new SearchData($grid);
        $q = trim((string) ($args['q'] ?? ''));
        $topic = in_array($args['topic'] ?? '', self::TOPICS, true) ? $args['topic'] : '';
        // On a page of its own it shows results; embedded in another page it is the form alone, until it is used
        $embedded = !isset($args['level']) && $q === '';
        $kinds = $embedded ? [] : ($topic === '' ? self::TOPICS : [$topic]);
        $groups = [];
        foreach ($kinds as $kind) {
            // Nobody lists the people of a grid: only a name asked for gives some
            if ($kind === 'people' && $q === '') {
                continue;
            }
            try {
                $items = $this->find($search, $kind, $q, $args);
            } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
                // The places, the land, the events and the classifieds share the search: told once
                $this->unavailable || Blocks::unreachable($e);
                $this->unavailable = true;
                $items = [];
            }
            $items === [] ||
                ($groups[] = ['topic' => $kind, 'title' => self::title($kind), 'items' => $items, 'url' => self::url($grid, $kind, $q, $args)]);
        }

        return [
            'title' => I18n::text('Search'),
            'form' => true,
            'embedded' => $embedded,
            'action' => self::page($grid, $args),
            'q' => $q,
            'topic' => $topic,
            'topics' => array_map(
                fn(string $kind): array => ['topic' => $kind, 'title' => self::title($kind), 'url' => self::url($grid, $kind, $q, $args), 'current' => $kind === $topic],
                self::TOPICS,
            ),
            'all_url' => self::url($grid, '', $q, $args),
            'groups' => $groups,
            // What could not be searched (a database of the grid that cannot be reached): unavailable for now, not nothing found
            'unavailable' => $this->unavailable,
        ];
    }

    /** The page of the search: the one of the site that shows it (its language), else the one of the grid. */
    private static function page(Grid $grid, array $args = []): string
    {
        $site = Blocks::siteAddress($args, 'search_url');

        return $site !== null ? rtrim($site, '/') : (rtrim((string) parse_url((string) $grid->searchUrl(), PHP_URL_PATH), '/') ?: '/search');
    }

    private static function url(Grid $grid, string $topic, string $q, array $args = []): string
    {
        return self::page($grid, $args) . ($topic === '' ? '' : "/$topic/") . ($q === '' ? '' : '?q=' . rawurlencode($q));
    }

    private static function title(string $kind): string
    {
        return [
            'places' => I18n::text('Places'),
            'land' => I18n::text('Land for sale'),
            'events' => I18n::text('Events'),
            'classifieds' => I18n::text('Classifieds'),
            'people' => I18n::text('People'),
        ][$kind];
    }

    /** @return list<array<string,mixed>> */
    private function find(SearchData $search, string $kind, string $q, array $args = []): array
    {
        $like = ['q' => '%' . addcslashes($q, '%_\\') . '%'];
        $params = $q === '' ? [] : $like;
        // The table of the regions, for the kinds that join it
        $regions = in_array($kind, ['places', 'land'], true) ? $search->regions() : '';
        $limit = self::LIMIT;
        switch ($kind) {
            case 'places':
                $where = $q === '' ? '' : 'WHERE p.parcelname LIKE :q OR p.description LIKE :q OR r.regionname LIKE :q';
                $rows = $search->rows(
                    "SELECT p.parcelname AS name, p.description, p.landingpoint, p.gatekeeperURL AS gatekeeper, p.dwell, r.regionname AS region FROM parcels AS p LEFT JOIN $regions AS r ON r.regionUUID = p.regionUUID $where ORDER BY p.dwell DESC, p.parcelname LIMIT $limit",
                    $params,
                );

                return array_map(fn($row) => $this->place($search, $row, '', $row['description'], $args), $rows);
            case 'land':
                $where = $q === '' ? '' : 'WHERE s.parcelname LIKE :q OR r.regionname LIKE :q';
                $rows = $search->rows(
                    "SELECT s.parcelname AS name, s.area, s.saleprice, s.landingpoint, s.gatekeeperURL AS gatekeeper, r.regionname AS region FROM parcelsales AS s LEFT JOIN $regions AS r ON r.regionUUID = s.regionUUID $where ORDER BY s.saleprice, s.parcelname LIMIT $limit",
                    $params,
                );

                return array_map(
                    fn($row) => $this->place($search, $row, sprintf('%s m², %s', number_format((int) $row['area']), number_format((int) $row['saleprice'])), '', $args),
                    $rows,
                );
            case 'events':
                // The events that are not over
                // The time written as a number, as the engine does (a database compares a bound one as text)
                $where = 'WHERE e.dateUTC + e.duration * 60 > ' . time() . ($q === '' ? '' : ' AND (e.name LIKE :q OR e.description LIKE :q OR e.simname LIKE :q)');
                // Every column the table has: one of an older version has no landing point until the engine adds it
                $rows = $search->rows("SELECT e.* FROM events AS e $where ORDER BY e.dateUTC LIMIT $limit", $params);

                return array_map(
                    fn($row) => $this->place(
                        $search,
                        ['region' => $row['simname'] ?? '', 'gatekeeper' => $row['gatekeeperURL'] ?? '', 'landingpoint' => $row['landingpoint'] ?? ''] + $row,
                        I18n::date((int) $row['dateUTC'], 'long', 'UTC') . ' UTC, ' . sprintf(I18n::text('%d min'), (int) $row['duration']),
                        $row['description'],
                        $args,
                    ),
                    $rows,
                );
            case 'classifieds':
                $where = 'WHERE expirationdate > :now' . ($q === '' ? '' : ' AND (name LIKE :q OR description LIKE :q OR parcelname LIKE :q OR simname LIKE :q)');
                $rows = $search->rows(
                    "SELECT name, description, simname AS region, parcelname FROM classifieds $where ORDER BY creationdate DESC LIMIT $limit",
                    ['now' => time()] + $params,
                );

                return array_map(
                    fn($row) => [
                        'title' => (string) $row['name'],
                        'detail' => trim($row['parcelname'] . ($row['region'] === '' ? '' : ', ' . $row['region']), ', '),
                        'text' => trim((string) $row['description']),
                        'url' => null,
                    ],
                    $rows,
                );
            case 'people':
                if ($search->config === null) {
                    return [];
                }
                try {
                    // Only who published their profile ("show in search" in the viewer), as the search of the viewers does
                    $people = Avatar::all($search->config, ['search' => $q, 'filters' => ['active' => 'yes', 'technical' => 'no', 'published' => 'yes'], 'perPage' => $limit])->array();
                } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
                    Blocks::unreachable($e);
                    $this->unavailable = true;
                    $people = [];
                }
                $profile = Blocks::siteAddress($args, 'web_profile_url') ?? (string) $search->grid->address('web-profile');

                return array_map(
                    fn($p) => [
                        'title' => $p['name'],
                        'detail' => '',
                        'text' => '',
                        'url' => Avatar::profileAt($profile, $p['firstName'] . ' ' . $p['lastName']),
                    ],
                    $people,
                );
        }

        return [];
    }

    /** @param array<string,mixed> $row */
    private function place(SearchData $search, array $row, string $detail, mixed $text = '', array $args = []): array
    {
        $region = (string) ($row['region'] ?? '');

        return [
            'title' => (string) $row['name'],
            'detail' => trim($region !== '' && $detail !== '' ? "$region · $detail" : $region . $detail),
            'text' => trim((string) $text),
            'url' => $search->teleport((string) ($row['gatekeeper'] ?? ''), $region, (string) ($row['landingpoint'] ?? ''), $args),
            'links' => $search->links((string) ($row['gatekeeper'] ?? ''), $region, (string) ($row['landingpoint'] ?? '')),
        ];
    }
}
