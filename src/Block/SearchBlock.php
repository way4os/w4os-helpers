<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\I18n\Catalog;
use W4OS\Engine\Models\Avatar;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Models\Group;
use W4OS\Engine\Services\SearchFilters;
use W4OS\Helpers\I18n;

/**
 * The web search, as the search of the viewers: a kind of search at a time (people, groups, places, land for sale, events,
 * classifieds), always offered in the order of their tabs, each with its own form and its own results. What each asks is written once with the search of the viewers
 * (SearchFilters): the same terms, the same results.
 *
 *  - places: words (3 letters at least), a category, the ratings;
 *  - land for sale: no words; its type, its price at most, its area at least, the ratings;
 *  - events: words if any; the ones to come or a day (the previous one, the next one), a category, the ratings;
 *  - classifieds: words if any; a category, the ratings;
 *  - people: words of a name, the ones who published their profile;
 *  - groups: words of a name, the ones shown in the search, of this grid, with members (the ratings asked change nothing there).
 */
final class SearchBlock implements Block
{
    public const TOPICS = ['people', 'groups', 'places', 'land', 'events', 'classifieds'];

    /** What a search asks, in the address of its page: a site gives each of them to the block (rating is a list). */
    public const PARAMETERS = ['q', 'category', 'rating', 'type', 'price', 'area', 'when', 'day', 'go', 'sort', 'dir', 'page', 'id'];

    /** The ratings, and the ones asked when a form has not been sent yet (as the viewers: general and moderate). */
    public const RATINGS = ['general', 'moderate', 'adult'];
    public const DEFAULT_RATINGS = ['general', 'moderate'];

    /** The columns of the list of each kind, after the name, as the viewers have them: the sort each is, its text. */
    public const COLUMNS = [
        'people' => [],
        'groups' => ['members' => 'web_search_members'],
        'places' => ['traffic' => 'web_search_traffic'],
        'land' => ['type' => 'web_search_land_type', 'price' => 'web_search_price', 'area' => 'web_search_area', 'price_m2' => 'web_search_price_m2'],
        'events' => ['time' => 'web_search_time'],
        'classifieds' => ['price' => 'web_search_price'],
    ];

    /** The results a page shows, as the viewers (each asks the next page on its own). */
    public const LIMIT = 100;

    /** Whether a part of the search could not be done (a database of the grid that cannot be reached) */
    private bool $unavailable = false;

    /** Whether there are more results than the page shows, for a kind of search that counts them (people) */
    private bool $more = false;

    public function data(Grid $grid, array $args = []): array
    {
        $this->unavailable = false;
        $this->more = false;
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        $topic = in_array($args['topic'] ?? '', self::TOPICS, true) ? (string) $args['topic'] : 'places';
        $asked = self::criteria($topic, $args);
        // On a page of its own (a level, or a kind in its address) it shows results; embedded in another page it is the form alone,
        // until it is used
        $embedded = !isset($args['level']) && !isset($args['topic']) && !$asked['sent'];
        $search = new SearchData($grid);
        [$ready, $note] = self::ready($topic, $asked, $texts);
        $items = [];
        $page = max(1, (int) ($args['page'] ?? 1));
        if ($ready && !$embedded) {
            try {
                $items = $this->find($search, $topic, $asked, $args + ['page' => $page]);
            } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
                Blocks::unreachable($e);
                $this->unavailable = true;
            }
        }

        $more = $this->more || count($items) > self::LIMIT;
        $items = array_slice($items, 0, self::LIMIT);
        // Each result selects itself (its detail beside the list), the search kept: the one asked is shown
        $kept = self::query($asked) + array_intersect_key($args, ['sort' => 1, 'dir' => 1]) + ($page > 1 ? ['page' => $page] : []);
        $selected = null;
        foreach ($items as $i => $item) {
            $items[$i]['select'] = '?' . http_build_query($kept + ['id' => $item['id'] ?? (string) $i]);
            $items[$i]['current'] = isset($args['id']) && (string) $args['id'] === (string) ($item['id'] ?? $i);
            $items[$i]['current'] && ($selected = $items[$i]);
        }
        $sorts = self::sorts($topic, $asked, $args, $texts);
        $sortBy = array_column($sorts, null, 'key');

        return [
            'title' => I18n::text('Search'),
            'form' => true,
            'embedded' => $embedded,
            'action' => self::page($grid, $args) . "/$topic/",
            'topic' => $topic,
            'q' => $asked['q'],
            'topics' => array_map(
                // The words go with the kind chosen: they are the same search, asked another way
                fn(string $kind): array => ['topic' => $kind, 'title' => $texts["web_search_$kind"], 'url' => self::page($grid, $args) . "/$kind/" . ($asked['q'] === '' ? '' : '?q=' . rawurlencode($asked['q'])), 'current' => $kind === $topic],
                self::TOPICS,
            ),
            'fields' => self::fields($topic, $asked, $texts),
            'submit' => $texts['web_search_submit'],
            'asked' => $ready,
            'note' => $note,
            'nothing' => $texts['web_search_nothing'],
            // A search the visitor asked (their form, their words): one made on its own, at the opening of a page, says nothing of none found
            'sent' => $asked['sent'],
            'results' => $items,
            'selected' => $selected,
            'sorts' => $sorts,
            // The head of the list: the name, then the columns of the kind, each a sort when the kind sorts on it
            'head' => array_map(
                static fn(string $key, string $label): array => ['label' => $texts[$label], 'url' => $sortBy[$key]['url'] ?? null, 'current' => $sortBy[$key]['current'] ?? false, 'dir' => $sortBy[$key]['dir'] ?? ''],
                ['name', ...array_keys(self::COLUMNS[$topic])],
                ['web_search_name', ...array_values(self::COLUMNS[$topic])],
            ),
            'pages' => self::pages($asked, $args, $page, $more, $texts),
            // Each kind lists its own results: what a column says
            'columns' => [
                'sort' => $texts['web_search_sort'], 'name' => $texts['web_search_name'], 'region' => $texts['web_search_region'], 'traffic' => $texts['web_search_traffic'], 'price' => $texts['web_search_price'],
                'area' => $texts['web_search_area'], 'date' => $texts['web_search_date'], 'duration' => $texts['web_search_duration'],
                'category' => $texts['web_search_category'],
            ],
            // What could not be searched (a database of the grid that cannot be reached): unavailable for now, not nothing found
            'unavailable' => $this->unavailable,
        ];
    }

    /**
     * What a search asks, from the arguments of the block (PARAMETERS); `sent` when its form was sent (`go`, or words given).
     *
     * @return array{q:string,category:int,ratings:list<string>,type:string,price:?int,area:?int,when:string,day:int,sent:bool}
     */
    public static function criteria(string $topic, array $args): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $sent = !empty($args['go']) || $q !== '';
        $ratings = is_array($args['rating'] ?? null) ? $args['rating'] : array_filter(explode(',', (string) ($args['rating'] ?? '')));
        $ratings = array_values(array_intersect(self::RATINGS, array_map('strval', $ratings)));
        $categories = self::categories($topic);
        $number = static fn(mixed $value): ?int => is_numeric($value) && (int) $value >= 0 ? (int) $value : null;

        return [
            'q' => $q,
            'category' => isset($categories[(int) ($args['category'] ?? 0)]) ? (int) $args['category'] : 0,
            // A form sent without a rating asks none; one not sent yet (a link with its words) asks the usual ones
            'ratings' => $ratings === [] && empty($args['go']) ? self::DEFAULT_RATINGS : $ratings,
            'type' => in_array($args['type'] ?? '', SearchFilters::LAND_TYPES, true) ? (string) $args['type'] : 'any',
            'price' => $number($args['price'] ?? null),
            'area' => $number($args['area'] ?? null),
            'when' => ($args['when'] ?? 'u') === 'day' ? 'day' : 'u',
            'day' => max(-30, min(30, (int) ($args['day'] ?? 0))),
            'sent' => $sent,
        ];
    }

    /**
     * Whether the search can be made, and what is said when it cannot (places and people need words, the others none).
     *
     * @return array{0:bool,1:string}
     */
    private static function ready(string $topic, array $asked, array $texts): array
    {
        $short = $asked['q'] === '' ? '' : sprintf($texts['web_search_min_letters'], SearchFilters::MIN_TEXT);

        return match ($topic) {
            'places' => mb_strlen($asked['q']) >= SearchFilters::MIN_TEXT ? [true, ''] : [false, $short],
            'people', 'groups' => [$asked['q'] !== '', ''],
            default => [true, ''],
        };
    }

    /** The categories of a kind of search, by number: their key in the catalog. */
    private static function categories(string $topic): array
    {
        return match ($topic) {
            'places' => array_map(static fn(string $key): string => "place_category_$key", SearchFilters::PLACE_CATEGORIES),
            'events' => array_map(static fn(string $key): string => "event_category_$key", SearchFilters::EVENT_CATEGORIES),
            'classifieds' => array_map(static fn(string $key): string => "classified_category_$key", SearchFilters::CLASSIFIED_CATEGORIES),
            default => [],
        };
    }

    /**
     * The fields of the form of a kind of search, as the template shows them: `name`, `type` (search, select, ratings, number,
     * when), `label`, `value`, `options` (value, label, selected), `help`, `min` (the fewest letters), `required`.
     *
     * @return list<array<string,mixed>>
     */
    private static function fields(string $topic, array $asked, array $texts): array
    {
        $fields = [];
        $words = ['name' => 'q', 'type' => 'search', 'value' => $asked['q']];
        $fields[] = match ($topic) {
            'places' => $words + ['label' => $texts['web_search_words'], 'required' => true, 'min' => SearchFilters::MIN_TEXT, 'help' => sprintf($texts['web_search_min_letters'], SearchFilters::MIN_TEXT)],
            'people', 'groups' => $words + ['label' => $texts['web_search_words'], 'required' => true],
            'land' => null,
            default => $words + ['label' => $texts['web_search_words']],
        };
        if ($topic === 'land') {
            $fields[] = ['name' => 'type', 'type' => 'select', 'label' => $texts['web_search_land_type'], 'value' => $asked['type'], 'options' => array_map(
                static fn(string $type): array => ['value' => $type, 'label' => $texts["web_search_land_$type"], 'selected' => $type === $asked['type']],
                SearchFilters::LAND_TYPES,
            )];
            $fields[] = ['name' => 'price', 'type' => 'number', 'label' => $texts['web_search_price_max'], 'value' => $asked['price']];
            $fields[] = ['name' => 'area', 'type' => 'number', 'label' => $texts['web_search_area_min'], 'value' => $asked['area'], 'unit' => 'm²'];
        }
        if ($topic === 'events') {
            $date = I18n::date(time() + $asked['day'] * 86400, 'date');
            $fields[] = ['name' => 'when', 'type' => 'when', 'label' => $texts['web_search_when'], 'value' => $asked['when'], 'day' => $asked['day'], 'date' => $date, 'options' => [
                ['value' => 'u', 'label' => $texts['web_search_upcoming'], 'selected' => $asked['when'] === 'u'],
                ['value' => 'day', 'label' => $texts['web_search_day'], 'selected' => $asked['when'] === 'day'],
            ], 'previous' => $texts['web_search_previous_day'], 'next' => $texts['web_search_next_day'],
                'previous_url' => self::dayUrl($asked, $asked['day'] - 1), 'next_url' => self::dayUrl($asked, $asked['day'] + 1)];
        }
        $categories = self::categories($topic);
        if ($categories !== []) {
            $fields[] = ['name' => 'category', 'type' => 'select', 'label' => $texts['web_search_category'], 'value' => $asked['category'], 'options' => [
                ['value' => 0, 'label' => $texts['web_search_any_category'], 'selected' => $asked['category'] === 0],
                ...array_map(
                    static fn(int $number, string $key): array => ['value' => $number, 'label' => $texts[$key], 'selected' => $number === $asked['category']],
                    array_keys($categories),
                    $categories,
                ),
            ]];
        }
        if (!in_array($topic, ['people', 'groups'], true)) {
            $fields[] = ['name' => 'rating', 'type' => 'ratings', 'label' => $texts['web_search_ratings'], 'options' => array_map(
                static fn(string $rating): array => ['value' => $rating, 'label' => $texts["web_search_rating_$rating"], 'selected' => in_array($rating, $asked['ratings'], true)],
                self::RATINGS,
            )];
        }

        return array_values(array_filter($fields));
    }

    /**
     * The columns a kind of search sorts on, each a link to its results sorted by it (the other way when it is the sort already),
     * what else it asks kept.
     *
     * @return list<array{key:string,label:string,url:string,current:bool,dir:string}>
     */
    private static function sorts(string $topic, array $asked, array $args, array $texts): array
    {
        [$sort, $dir] = self::sort($topic, $args);
        $labels = [
            'name' => 'web_search_name', 'traffic' => 'web_search_traffic', 'price' => 'web_search_price', 'area' => 'web_search_area',
            'price_m2' => 'web_search_price_m2', 'type' => 'web_search_land_type', 'time' => 'web_search_time', 'members' => 'web_search_members',
        ];
        $sorts = [];
        foreach (array_keys(self::SORTS[$topic]) as $key) {
            $next = $key === $sort ? ($dir === 'asc' ? 'desc' : 'asc') : self::sort($topic, ['sort' => $key])[1];
            $sorts[] = ['key' => $key, 'label' => $texts[$labels[$key]], 'url' => '?' . http_build_query(self::query($asked) + ['sort' => $key, 'dir' => $next]), 'current' => $key === $sort, 'dir' => $key === $sort ? $dir : ''];
        }

        return $sorts;
    }

    /**
     * The links to the previous and the next page of results, the sort and what else it asks kept; null where there is none.
     *
     * @return array{previous:?string,next:?string,previous_label:string,next_label:string}
     */
    private static function pages(array $asked, array $args, int $page, bool $more, array $texts): array
    {
        $kept = self::query($asked) + array_intersect_key($args, ['sort' => 1, 'dir' => 1]);
        $url = static fn(int $to): string => '?' . http_build_query($kept + ($to > 1 ? ['page' => $to] : []));

        return [
            'previous' => $page > 1 ? $url($page - 1) : null,
            'next' => $more ? $url($page + 1) : null,
            'previous_label' => $texts['web_search_previous'],
            'next_label' => $texts['web_search_next'],
        ];
    }

    /** What a search asks, as the query of its address (PARAMETERS): to keep it on another link. */
    private static function query(array $asked): array
    {
        return array_filter([
            'q' => $asked['q'],
            'category' => $asked['category'] ?: null,
            'rating' => $asked['ratings'],
            'type' => $asked['type'] === 'any' ? null : $asked['type'],
            'price' => $asked['price'],
            'area' => $asked['area'],
            'when' => $asked['when'] === 'u' ? null : $asked['when'],
            'day' => $asked['day'] ?: null,
            'go' => 1,
        ], static fn($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /** The query of the search of events on another day, what else it asks kept. */
    private static function dayUrl(array $asked, int $day): string
    {
        return '?' . http_build_query(array_filter([
            'q' => $asked['q'],
            'category' => $asked['category'] ?: null,
            'rating' => $asked['ratings'],
            'when' => 'day',
            'day' => $day,
            'go' => 1,
        ], static fn($value): bool => $value !== null && $value !== '' && $value !== []));
    }

    /** The page of the search: the one of the site that shows it (its language), else the one of the grid. */
    private static function page(Grid $grid, array $args = []): string
    {
        $site = Blocks::siteAddress($args, 'search_url');

        return $site !== null ? rtrim($site, '/') : (rtrim((string) parse_url((string) $grid->searchUrl(), PHP_URL_PATH), '/') ?: '/search');
    }

    /** The columns a kind of search sorts on, as the viewers do (its list): its column in the query; the first is the default. */
    public const SORTS = [
        'places' => ['traffic' => 'p.dwell', 'name' => 'p.parcelname'],
        'land' => ['price_m2' => 's.saleprice * 1.0 / s.area', 'name' => 's.parcelname', 'type' => 's.parentestate', 'price' => 's.saleprice', 'area' => 's.area'],
        'events' => ['time' => 'e.dateUTC', 'name' => 'e.name'],
        'classifieds' => ['price' => 'priceforlisting', 'name' => 'name'],
        'people' => ['name' => 'name'],
        'groups' => ['name' => 'name', 'members' => 'members'],
    ];

    /** The way each sort goes first: the most visited places, the most expensive classifieds first, the others up. */
    private const FIRST_DIRECTION = ['traffic' => 'desc', 'price' => 'asc'];

    /**
     * The sort asked of a kind of search, and its direction: one of its columns (SORTS), else its first.
     *
     * @return array{0:string,1:string}
     */
    private static function sort(string $topic, array $args): array
    {
        $columns = self::SORTS[$topic];
        $sort = isset($columns[$args['sort'] ?? '']) ? (string) $args['sort'] : (string) array_key_first($columns);
        $first = $topic === 'classifieds' && $sort === 'price' ? 'desc' : (self::FIRST_DIRECTION[$sort] ?? 'asc');
        $dir = in_array($args['dir'] ?? '', ['asc', 'desc'], true) ? (string) $args['dir'] : $first;

        return [$sort, $dir];
    }

    /** @return list<array<string,mixed>> */
    private function find(SearchData $search, string $kind, array $asked, array $args = []): array
    {
        // One more than a page: whether there is a next one
        $limit = (self::LIMIT + 1) . ' OFFSET ' . (max(1, (int) ($args['page'] ?? 1)) - 1) * self::LIMIT;
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        // The table of the regions, for the kinds that join it
        $regions = in_array($kind, ['places', 'land'], true) ? $search->regions() : '';
        $where = static fn(array $terms): string => $terms === [] ? '' : 'WHERE ' . implode(' AND ', $terms);
        [$sort, $dir] = self::sort($kind, $args);
        $order = $sort === '' ? '' : self::SORTS[$kind][$sort] . ' ' . strtoupper($dir);
        $rating = static fn(?string $mature): string => match (strtolower((string) $mature)) {
            'pg' => $texts['web_search_rating_general'], 'mature' => $texts['web_search_rating_moderate'], 'adult' => $texts['web_search_rating_adult'], default => '',
        };
        $column = static fn(string $label, mixed $value, bool $number = false): array => ['label' => $label, 'value' => (string) $value, 'number' => $number];
        switch ($kind) {
            case 'places':
                [$terms, $values] = SearchFilters::places($asked['q'], $asked['category'], $asked['ratings'], 'p');
                $rows = $search->rows(
                    "SELECT p.*, r.regionname AS region, a.parcelarea AS area FROM parcels AS p LEFT JOIN $regions AS r ON r.regionUUID = p.regionUUID " .
                        "LEFT JOIN allparcels AS a ON a.parcelUUID = p.parcelUUID {$where($terms)} ORDER BY $order, p.parcelname LIMIT $limit",
                    $values,
                );

                return array_map(fn(array $row): array => $this->place($search, [
                    'name' => $row['parcelname'], 'region' => (string) ($row['region'] ?? ''), 'landingpoint' => $row['landingpoint'] ?? '',
                    'gatekeeper' => $row['gatekeeperURL'] ?? '',
                ], '', $row['description'] ?? '', $args) + [
                    'id' => (string) $row['parcelUUID'],
                    'picture' => self::picture($row['imageUUID'] ?? null),
                    'traffic' => (int) ($row['dwell'] ?? 0),
                    'columns' => [$column($texts['web_search_traffic'], number_format((int) ($row['dwell'] ?? 0)), true)],
                    'details' => array_values(array_filter([
                        $column($texts['web_search_region'], $row['region'] ?? ''),
                        ($row['area'] ?? null) === null ? null : $column($texts['web_search_area'], number_format((int) $row['area']) . ' m²'),
                        $column($texts['web_search_traffic'], number_format((int) ($row['dwell'] ?? 0))),
                        $column($texts['web_search_rating'], $rating($row['mature'] ?? null)),
                    ], static fn(?array $d): bool => $d !== null && $d['value'] !== '')),
                ], $rows);
            case 'land':
                [$terms, $values] = SearchFilters::land($asked['type'], $asked['price'], $asked['area'], $asked['ratings'], 's');
                $rows = $search->rows(
                    "SELECT s.*, r.regionname AS region, p.imageUUID AS picture, p.description AS about FROM parcelsales AS s LEFT JOIN $regions AS r ON r.regionUUID = s.regionUUID " .
                        "LEFT JOIN parcels AS p ON p.parcelUUID = s.parcelUUID {$where($terms)} ORDER BY $order, s.parcelname LIMIT $limit",
                    $values,
                );

                return array_map(function (array $row) use ($search, $args, $texts, $column, $rating): array {
                    $area = (int) $row['area'];
                    $price = (int) $row['saleprice'];
                    $type = (int) ($row['parentestate'] ?? 0) === 1 ? $texts['web_search_land_mainland'] : $texts['web_search_land_estate'];

                    return $this->place($search, [
                        'name' => $row['parcelname'], 'region' => (string) ($row['region'] ?? ''), 'landingpoint' => $row['landingpoint'] ?? '',
                        'gatekeeper' => $row['gatekeeperURL'] ?? '',
                    ], sprintf('%s m², %s', number_format($area), number_format($price)), $row['about'] ?? '', $args) + [
                        'id' => (string) $row['parcelUUID'],
                        'area' => $area,
                        'price' => $price,
                        'picture' => self::picture($row['picture'] ?? null),
                        'columns' => [
                            $column($texts['web_search_land_type'], $type),
                            $column($texts['web_search_price'], number_format($price), true),
                            $column($texts['web_search_area'], number_format($area) . ' m²', true),
                            $column($texts['web_search_price_m2'], $area > 0 ? number_format($price / $area, 2) : '', true),
                        ],
                        'details' => array_values(array_filter([
                            $column($texts['web_search_region'], $row['region'] ?? ''),
                            $column($texts['web_search_area'], number_format($area) . ' m²'),
                            $column($texts['web_search_traffic'], number_format((int) ($row['dwell'] ?? 0))),
                            $column($texts['web_search_rating'], $rating($row['mature'] ?? null)),
                        ], static fn(array $d): bool => $d['value'] !== '')),
                    ];
                }, $rows);
            case 'events':
                [$terms, $values] = SearchFilters::events($asked['when'] === 'day' ? $asked['day'] : 'u', $asked['category'], $asked['ratings'], $asked['q'], time(), 'e');
                // Every column the table has: one of an older version has no landing point until the engine adds it
                $rows = $search->rows("SELECT e.* FROM events AS e {$where($terms)} ORDER BY $order LIMIT $limit", $values);
                $categories = self::categories('events');

                return array_map(function (array $row) use ($search, $args, $texts, $column, $categories): array {
                    $minutes = (int) $row['duration'];
                    $fee = (int) ($row['covercharge'] ?? 0) !== 0 ? number_format((int) ($row['coveramount'] ?? 0)) : $texts['web_search_none'];

                    return $this->place(
                        $search,
                        // The region of another grid's event is kept with its grid (host:port Region): its name, for its links;
                        // shown as kept
                        [
                            'name' => $row['name'],
                            'region' => SearchData::regionName((string) ($row['simname'] ?? ''), (string) ($row['gatekeeperURL'] ?? '')),
                            'shown' => (string) ($row['simname'] ?? ''),
                            'gatekeeper' => $row['gatekeeperURL'] ?? '',
                            'landingpoint' => $row['landingpoint'] ?? '',
                        ],
                        I18n::date((int) $row['dateUTC'], 'long', 'UTC') . ' UTC, ' . sprintf(I18n::text('%d min'), $minutes),
                        $row['description'],
                        $args,
                    ) + [
                        'id' => (string) $row['eventid'],
                        'date' => I18n::date((int) $row['dateUTC'], 'long', 'UTC') . ' UTC',
                        'duration' => sprintf(I18n::text('%d min'), $minutes),
                        'category' => isset($categories[(int) ($row['category'] ?? 0)]) ? $texts[$categories[(int) $row['category']]] : '',
                        'columns' => [$column($texts['web_search_time'], I18n::date((int) $row['dateUTC'], 'long', 'UTC') . ' UTC')],
                        'details' => [
                            $column($texts['web_search_land_type'], isset($categories[(int) ($row['category'] ?? 0)]) ? $texts[$categories[(int) $row['category']]] : ''),
                            $column($texts['web_search_adult_content'], (int) ($row['eventflags'] ?? 0) === 2 ? $texts['web_search_yes'] : $texts['web_search_no']),
                            $column($texts['web_search_date'], I18n::date((int) $row['dateUTC'], 'long', 'UTC') . ' UTC'),
                            $column($texts['web_search_duration'], sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60)),
                            $column($texts['web_search_organizer'], $this->organizer($search, (string) ($row['owneruuid'] ?? '')) ?: $texts['web_search_none']),
                            $column($texts['web_search_location'], (string) ($row['simname'] ?? '')),
                            $column($texts['web_search_entry_fee'], $fee),
                        ],
                    ];
                }, $rows);
            case 'classifieds':
                [$terms, $values] = SearchFilters::classifieds($asked['q'], $asked['category'], $asked['ratings'], now: time());
                $rows = $search->rows("SELECT * FROM classifieds {$where($terms)} ORDER BY $order, creationdate DESC LIMIT $limit", $values);
                $categories = self::categories('classifieds');

                return array_map(function (array $row) use ($search, $args, $texts, $column, $categories): array {
                    $flags = (int) ($row['classifiedflags'] ?? 0);
                    $rated = match (true) {
                        ($flags & 64) !== 0 => $texts['web_search_rating_adult'], ($flags & 8) !== 0 => $texts['web_search_rating_moderate'], default => $texts['web_search_rating_general'],
                    };
                    // Its place: the region, the point in it as the viewers show it (the global position within its region)
                    $point = array_map('floatval', explode(',', trim((string) ($row['posglobal'] ?? ''), '<> ')) + [0, 0, 0]);
                    $landing = sprintf('%d/%d/%d', fmod($point[0], 256), fmod($point[1], 256), $point[2] ?? 0);
                    $location = trim(($row['parcelname'] ?? '') . (($row['simname'] ?? '') === '' ? '' : ', ' . $row['simname']), ', ')
                        . (($row['posglobal'] ?? '') === '' ? '' : ' (' . str_replace('/', ', ', $landing) . ')');
                    $price = number_format((int) ($row['priceforlisting'] ?? 0));

                    return [
                        'id' => (string) $row['classifieduuid'],
                        'title' => (string) $row['name'],
                        'detail' => $location,
                        'text' => trim((string) $row['description']),
                        'url' => null,
                        'links' => ($row['simname'] ?? '') === '' ? [] : $search->links('', (string) $row['simname'], $landing),
                        'picture' => self::picture($row['snapshotuuid'] ?? null),
                        'category' => isset($categories[(int) ($row['category'] ?? 0)]) ? $texts[$categories[(int) $row['category']]] : '',
                        'columns' => [$column($texts['web_search_price'], $price, true)],
                        'details' => [
                            $column($texts['web_search_location'], $location),
                            $column($texts['web_search_content_type'], $rated),
                            $column($texts['web_search_category'], isset($categories[(int) ($row['category'] ?? 0)]) ? $texts[$categories[(int) $row['category']]] : ''),
                            $column($texts['web_search_creation_date'], I18n::date((int) ($row['creationdate'] ?? 0), 'date')),
                            $column($texts['web_search_listing_cost'], $price),
                        ],
                    ];
                }, $rows);
            case 'people':
            case 'groups':
                if ($search->config === null) {
                    return [];
                }
                $page = max(1, (int) ($args['page'] ?? 1));
                try {
                    // People: only who published their profile ("show in search" in the viewer), as the search of the viewers does
                    $found = $kind === 'people'
                        ? Avatar::all($search->config, ['search' => $asked['q'], 'filters' => ['active' => 'yes', 'technical' => 'no', 'published' => 'yes'], 'sort' => "name $dir", 'perPage' => self::LIMIT, 'page' => $page])
                        : Group::all($search->config, ['search' => $asked['q'], 'sort' => self::SORTS['groups'][$sort] . " $dir", 'perPage' => self::LIMIT, 'page' => $page]);
                    $this->more = $found->total() > $page * self::LIMIT;

                    return array_map(fn($found): array => $kind === 'people' ? $this->person($search, $found, $args, $texts) : $this->group($search, $found, $args, $texts), $found->items());
                } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
                    Blocks::unreachable($e);
                    $this->unavailable = true;

                    return [];
                }
        }

        return [];
    }

    /** A person found, as the viewers show them: their name, their key, their birth with their age, their account, their partner, about them, their groups. */
    private function person(SearchData $search, Avatar $avatar, array $args, array $texts): array
    {
        $d = $avatar->detail();
        $profile = Blocks::siteAddress($args, 'web_profile_url') ?? (string) $search->grid->address('web-profile');
        $url = Avatar::profileAt($profile, $avatar->name());
        $created = (int) ($d['created'] ?? 0);
        $partner = $d['partner'] === null ? '' : $this->organizer($search, (string) $d['partner']);
        $groups = array_column($avatar->groups(), 'name');
        $column = static fn(string $label, string $value, ?string $link = null): array => ['label' => $label, 'value' => $value, 'number' => false, 'url' => $link];

        return [
            'id' => (string) $d['uuid'],
            'title' => $avatar->name(),
            'detail' => '',
            'text' => trim((string) ($d['about'] ?? '')),
            'url' => $url,
            'picture' => self::picture($d['profileImage'] ?? null),
            'columns' => [],
            'details' => [
                $column($texts['web_search_key'], (string) $d['uuid']),
                $column($texts['web_search_born'], $created > 0 ? I18n::date($created, 'date') . ' (' . ProfileBlock::age(intdiv(max(0, time() - $created), 86400)) . ')' : ''),
                $column($texts['web_search_account'], (string) ($texts[Catalog::roleKey((string) ($d['role'] ?? ''))] ?? '')),
                $column($texts['web_search_partner'], $partner ?: $texts['web_search_none'], $partner === '' ? null : Avatar::profileAt($profile, $partner)),
                $column($texts['web_search_groups'], $groups === [] ? $texts['web_search_none'] : implode(', ', $groups)),
            ],
            'actions' => $url === null ? [] : [['label' => $texts['web_search_show_profile'], 'url' => $url]],
        ];
    }

    /** A group found, as the viewers show it to who is not a member: its key, its founder, its insignia, its charter, its members, who can join and at what fee, its content rating. */
    private function group(SearchData $search, Group $group, array $args, array $texts): array
    {
        $d = $group->detail();
        $profile = Blocks::siteAddress($args, 'web_profile_url') ?? (string) $search->grid->address('web-profile');
        $founder = $d['founder'] === null ? '' : $this->organizer($search, $d['founder']);
        $column = static fn(string $label, string $value, ?string $link = null, bool $number = false): array => ['label' => $label, 'value' => $value, 'number' => $number, 'url' => $link];

        return [
            'id' => $d['id'],
            'title' => $d['name'],
            'detail' => '',
            'text' => $d['charter'],
            'url' => null,
            'picture' => $d['picture'],
            'columns' => [$column($texts['web_search_members'], number_format($d['members']), number: true)],
            'details' => [
                $column($texts['web_search_group_key'], $d['id']),
                $column($texts['web_search_founder'], $founder ?: $texts['web_search_none'], $founder === '' ? null : Avatar::profileAt($profile, $founder)),
                $column($texts['web_search_open_enrollment'], $d['open'] ? $texts['web_search_yes'] : $texts['web_search_no']),
                $column($texts['web_search_join_fee'], number_format($d['fee'])),
                $column($texts['web_search_content_type'], $d['mature'] ? $texts['web_search_content_moderate'] : $texts['web_search_content_general']),
            ],
            // Who is in it, as the list of its members: their name, the title they wear, in the world or the day they last came; read
            // for the group shown only
            'members' => [
                'labels' => [$texts['web_search_member'], $texts['web_search_title'], $texts['web_search_status']],
                'rows' => array_map(static fn(array $m): array => [
                    'name' => $m['name'],
                    'url' => Avatar::profileAt($profile, $m['name']),
                    'title' => (string) $m['title'],
                    'status' => $m['online'] ? $texts['web_search_online'] : ($m['login'] ? I18n::date($m['login'], 'date') : ''),
                ], ($args['id'] ?? null) === $d['id'] ? $group->members() : []),
            ],
        ];
    }

    /** @var array<string,string> the names of the organizers asked during this request */
    private array $organizers = [];

    /** The name of the organizer of an event, '' for nobody (the events of a calendar) or one the grid does not know. */
    private function organizer(SearchData $search, string $uuid): string
    {
        if (trim($uuid, '0-1') === '' || $search->config === null) {
            return '';
        }
        try {
            return $this->organizers[$uuid] ??= (string) (Avatar::find($search->config, $uuid)?->name() ?? '');
        } catch (\Throwable) {
            return $this->organizers[$uuid] = '';
        }
    }

    /** A picture of a row, null when it has none (the null UUID). */
    private static function picture(mixed $uuid): ?string
    {
        $uuid = trim((string) $uuid);

        return trim($uuid, '0-') === '' ? null : $uuid;
    }

    /** @param array<string,mixed> $row */
    private function place(SearchData $search, array $row, string $detail, mixed $text = '', array $args = []): array
    {
        $region = (string) ($row['region'] ?? '');
        $shown = (string) ($row['shown'] ?? $region);

        return [
            'title' => (string) $row['name'],
            'region' => $shown,
            'detail' => trim($shown !== '' && $detail !== '' ? "$shown · $detail" : $shown . $detail),
            'text' => trim((string) $text),
            'url' => $search->teleport((string) ($row['gatekeeper'] ?? ''), $region, (string) ($row['landingpoint'] ?? ''), $args),
            'links' => $search->links((string) ($row['gatekeeper'] ?? ''), $region, (string) ($row['landingpoint'] ?? '')),
        ];
    }
}
