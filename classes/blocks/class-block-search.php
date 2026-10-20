<?php
/**
 * Block search: the search of the web, the page the viewers open for the web search (`search` of [GridInfoService]) and the
 * one a visitor uses: places, land for sale, events, classifieds and people, from the tables the search registry fills
 * (what the simulators tell, see includes/search-parser.php) and from the accounts of the grid.
 *
 * `?q=` is what is looked for, the first part of the path after the page (`/search/land/`) the kind of what is looked for.
 * Without a word the page shows what the grid has to offer: its popular places, its events to come, the land for sale. Each
 * result that is a place is a link a viewer follows (hop://).
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_Block_Search
{
    /** What can be looked for, in the order the results come in. */
    const TOPICS = ['places', 'land', 'events', 'classifieds', 'people'];

    /** How many results of each kind. */
    const LIMIT = 30;

    /**
     * @param  array<string,mixed> $args `q`: what to look for; `topic`: places, land, events, classifieds or people
     * @return array<string,mixed>
     */
    public static function data($args = [])
    {
        $q = trim((string) ($args['q'] ?? ''));
        $topic = self::topic($args);
        // On a page of its own it shows results; embedded in another page (the home page) it is the form alone, until it is used
        $embedded = !isset($args['level']) && $q === '';
        $kinds = $embedded ? [] : ($topic === '' ? self::TOPICS : [$topic]);
        $groups = [];
        foreach ($kinds as $kind) {
            // Nobody lists the people of a grid: only a name asked for gives some
            if ($kind === 'people' && $q === '') {
                continue;
            }
            $items = self::find($kind, $q);
            if ($items) {
                $groups[] = ['topic' => $kind, 'title' => self::title($kind), 'items' => $items, 'url' => self::url($kind, $q)];
            }
        }
        $page = OpenSim_Helpers_View::page_path('search') ?? '/search';

        return [
            'title' => _('Search'),
            'form' => true,
            'embedded' => $embedded,
            'action' => $page,
            'q' => $q,
            'topic' => $topic,
            'topics' => array_map(fn($kind) => ['topic' => $kind, 'title' => self::title($kind), 'url' => self::url($kind, $q), 'current' => $kind === $topic], self::TOPICS),
            'all_url' => self::url('', $q),
            'groups' => $groups,
        ];
    }

    /** The kind of what is looked for: `?topic=` or the first part of the path after the page. */
    public static function topic($args)
    {
        $path = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
        $topic = strtolower($args['topic'] ?? strtok($path, '/') ?: '');

        return in_array($topic, self::TOPICS, true) ? $topic : '';
    }

    /** The address of the search for a kind of results: the page, the kind, the words. */
    private static function url($topic, $q)
    {
        $page = rtrim(OpenSim_Helpers_View::page_path('search') ?? '/search', '/');

        return $page . ($topic === '' ? '' : "/$topic/") . ($q === '' ? '' : '?q=' . rawurlencode($q));
    }

    private static function title($kind)
    {
        return [
            'places' => _('Places'),
            'land' => _('Land for sale'),
            'events' => _('Events'),
            'classifieds' => _('Classifieds'),
            'people' => _('People'),
        ][$kind];
    }

    /**
     * What a word looks for in a column: the word anywhere in it, what has a meaning in LIKE taken as it is.
     *
     * @return string
     */
    public static function like($q)
    {
        return '%' . addcslashes($q, '%_\\') . '%';
    }

    /**
     * The results of one kind.
     *
     * @return list<array<string,mixed>>
     */
    public static function find($kind, $q)
    {
        global $SearchDB;
        if (empty($SearchDB) && defined('SEARCH_DB_HOST') && defined('SEARCH_DB_NAME') && defined('SEARCH_DB_USER') && defined('SEARCH_DB_PASS') && is_file(dirname(__DIR__, 2) . '/includes/search.php')) {
            require_once dirname(__DIR__, 2) . '/includes/search.php';
        }
        $like = self::like($q);
        $regions = defined('SEARCH_REGION_TABLE') ? SEARCH_REGION_TABLE : 'regionsregister';
        $limit = (int) self::LIMIT;
        switch ($kind) {
            case 'places':
                $where = $q === '' ? '' : 'WHERE p.parcelname LIKE :q OR p.description LIKE :q OR r.regionname LIKE :q';
                $rows = self::rows($SearchDB ?? null, "SELECT p.parcelname AS name, p.description, p.landingpoint, p.gatekeeperURL AS gatekeeper, p.dwell, r.regionname AS region FROM parcels AS p LEFT JOIN $regions AS r ON r.regionUUID = p.regionUUID $where ORDER BY p.dwell DESC, p.parcelname LIMIT $limit", $q === '' ? [] : ['q' => $like]);

                return array_map(fn($row) => self::place($row, '', $row['description']), $rows);
            case 'land':
                $where = $q === '' ? '' : 'WHERE s.parcelname LIKE :q OR r.regionname LIKE :q';
                $rows = self::rows($SearchDB ?? null, "SELECT s.parcelname AS name, s.area, s.saleprice, s.landingpoint, s.gatekeeperURL AS gatekeeper, r.regionname AS region FROM parcelsales AS s LEFT JOIN $regions AS r ON r.regionUUID = s.regionUUID $where ORDER BY s.saleprice, s.parcelname LIMIT $limit", $q === '' ? [] : ['q' => $like]);

                return array_map(fn($row) => self::place($row, sprintf('%s m², %s', number_format((int) $row['area']), number_format((int) $row['saleprice']))), $rows);
            case 'events':
                // The events that are not over
                $where = 'WHERE e.dateUTC + e.duration * 60 > :now' . ($q === '' ? '' : ' AND (e.name LIKE :q OR e.description LIKE :q OR e.simname LIKE :q)');
                $rows = self::rows($SearchDB ?? null, "SELECT e.name, e.description, e.dateUTC, e.duration, e.simname AS region, e.landingpoint, e.gatekeeperURL AS gatekeeper FROM events AS e $where ORDER BY e.dateUTC LIMIT $limit", ['now' => time()] + ($q === '' ? [] : ['q' => $like]));

                return array_map(fn($row) => self::place($row, self::when($row), $row['description']), $rows);
            case 'classifieds':
                $where = 'WHERE expirationdate > :now' . ($q === '' ? '' : ' AND (name LIKE :q OR description LIKE :q OR parcelname LIKE :q OR simname LIKE :q)');
                $rows = self::rows($SearchDB ?? null, "SELECT name, description, simname AS region, parcelname FROM classifieds $where ORDER BY creationdate DESC LIMIT $limit", ['now' => time()] + ($q === '' ? [] : ['q' => $like]));

                return array_map(fn($row) => ['title' => (string) $row['name'], 'detail' => trim($row['parcelname'] . ($row['region'] === '' ? '' : ', ' . $row['region']), ', '), 'text' => trim((string) $row['description']), 'url' => null], $rows);
            case 'people':
                try {
                    $people = \OpenSim\Models\Avatar::all(OpenSim_Helpers_GridConfig::engine_config(), ['search' => $q, 'filters' => ['active' => 'yes', 'technical' => 'no'], 'perPage' => (int) $limit])->array();
                } catch (\OpenSim\Exceptions\ChannelUnavailable $e) {
                    $people = [];
                }
                $profile = OpenSim_Helpers_View::page_path('profile');

                return array_map(fn($person) => ['title' => $person['name'], 'detail' => '', 'text' => '', 'url' => $profile === null ? null : rtrim($profile, '/') . '/' . rawurlencode($person['firstName'] . '.' . $person['lastName'])], $people);
        }

        return [];
    }

    /**
     * Rows of the database, nothing when it does not answer.
     *
     * @param  object|null $db
     * @return list<array<string,mixed>>
     */
    private static function rows($db, $sql, $params)
    {
        $statement = $db ? $db->prepareAndExecute($sql, $params) : false;

        return $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /** A result that is a place: its name, what to say of it, and the link a viewer follows. */
    private static function place($row, $detail, $text = '')
    {
        $region = (string) ($row['region'] ?? '');

        return [
            'title' => (string) $row['name'],
            'detail' => trim($region !== '' && $detail !== '' ? "$region · $detail" : $region . $detail),
            'text' => trim((string) $text),
            'url' => self::hop((string) ($row['gatekeeper'] ?? ''), $region, (string) ($row['landingpoint'] ?? '')),
        ];
    }

    /** When an event is: its date and its duration. */
    private static function when($row)
    {
        return date('Y-m-d H:i', (int) $row['dateUTC']) . ' UTC, ' . sprintf(_('%d min'), (int) $row['duration']);
    }

    /**
     * The link of a place: the hop link of its region of its grid, at its landing point.
     *
     * @return string|null null when there is nothing to say where it is
     */
    public static function hop($gatekeeper, $region, $landing)
    {
        $host = preg_replace('#^https?://#i', '', rtrim($gatekeeper !== '' ? $gatekeeper : (defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : ''), '/'));
        if ($region === '' || $host === '') {
            return null;
        }
        $at = preg_match('/([\d.]+)\s*[\/,]\s*([\d.]+)\s*[\/,]\s*([\d.]+)/', $landing, $m) ? sprintf('/%d/%d/%d', (float) $m[1], (float) $m[2], (float) $m[3]) : '';

        return opensim_format_tp("$host:$region$at", TPLINK_HOP);
    }
}
