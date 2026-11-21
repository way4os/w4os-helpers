<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\I18n\Catalog;
use W4OS\Engine\Models\Avatar;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Services\SearchFilters;
use W4OS\Helpers\I18n;

/**
 * The web search, as the search of the viewers: a kind of search at a time (places, land for sale, events, classifieds, people),
 * always offered, each with its own form and its own results. What each asks is written once with the search of the viewers
 * (SearchFilters): the same terms, the same results.
 *
 *  - places: words (3 letters at least), a category, the ratings;
 *  - land for sale: no words; its type, its price at most, its area at least, the ratings;
 *  - events: words if any; the ones to come or a day (the previous one, the next one), a category, the ratings;
 *  - classifieds: words if any; a category, the ratings;
 *  - people: a name, the ones who published their profile.
 */
final class SearchBlock implements Block
{
    public const TOPICS = ['places', 'land', 'events', 'classifieds', 'people'];

    /** What a search asks, in the address of its page: a site gives each of them to the block (rating is a list). */
    public const PARAMETERS = ['q', 'category', 'rating', 'type', 'price', 'area', 'when', 'day', 'go'];

    /** The ratings, and the ones asked when a form has not been sent yet (as the viewers: general and moderate). */
    public const RATINGS = ['general', 'moderate', 'adult'];
    public const DEFAULT_RATINGS = ['general', 'moderate'];

    public const LIMIT = 30;

    /** Whether a part of the search could not be done (a database of the grid that cannot be reached) */
    private bool $unavailable = false;

    public function data(Grid $grid, array $args = []): array
    {
        $this->unavailable = false;
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        $topic = in_array($args['topic'] ?? '', self::TOPICS, true) ? (string) $args['topic'] : 'places';
        $asked = self::criteria($topic, $args);
        // On a page of its own (a level, or a kind in its address) it shows results; embedded in another page it is the form alone,
        // until it is used
        $embedded = !isset($args['level']) && !isset($args['topic']) && !$asked['sent'];
        $search = new SearchData($grid);
        [$ready, $note] = self::ready($topic, $asked, $texts);
        $items = [];
        if ($ready && !$embedded) {
            try {
                $items = $this->find($search, $topic, $asked, $args);
            } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
                Blocks::unreachable($e);
                $this->unavailable = true;
            }
        }

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
            'results' => $items,
            // Each kind lists its own results: what a column says
            'columns' => [
                'name' => $texts['web_search_name'], 'region' => $texts['web_search_region'], 'traffic' => $texts['web_search_traffic'], 'price' => $texts['web_search_price'],
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
        $short = $asked['q'] === '' ? $texts['web_search_ask'] : sprintf($texts['web_search_min_letters'], SearchFilters::MIN_TEXT);

        return match ($topic) {
            'places' => mb_strlen($asked['q']) >= SearchFilters::MIN_TEXT ? [true, ''] : [false, $short],
            'people' => $asked['q'] !== '' ? [true, ''] : [false, $texts['web_search_ask']],
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
            'people' => $words + ['label' => $texts['web_search_name'], 'required' => true],
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
        if ($topic !== 'people') {
            $fields[] = ['name' => 'rating', 'type' => 'ratings', 'label' => $texts['web_search_ratings'], 'options' => array_map(
                static fn(string $rating): array => ['value' => $rating, 'label' => $texts["web_search_rating_$rating"], 'selected' => in_array($rating, $asked['ratings'], true)],
                self::RATINGS,
            )];
        }

        return array_values(array_filter($fields));
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

    /** @return list<array<string,mixed>> */
    private function find(SearchData $search, string $kind, array $asked, array $args = []): array
    {
        $limit = self::LIMIT;
        // The table of the regions, for the kinds that join it
        $regions = in_array($kind, ['places', 'land'], true) ? $search->regions() : '';
        $where = static fn(array $terms): string => $terms === [] ? '' : 'WHERE ' . implode(' AND ', $terms);
        switch ($kind) {
            case 'places':
                [$terms, $values] = SearchFilters::places($asked['q'], $asked['category'], $asked['ratings'], 'p');
                $rows = $search->rows(
                    "SELECT p.*, r.regionname AS region FROM parcels AS p LEFT JOIN $regions AS r ON r.regionUUID = p.regionUUID {$where($terms)} ORDER BY p.dwell DESC, p.parcelname LIMIT $limit",
                    $values,
                );

                return array_map(fn(array $row): array => $this->place($search, [
                    'name' => $row['parcelname'], 'region' => (string) ($row['region'] ?? ''), 'landingpoint' => $row['landingpoint'] ?? '',
                    'gatekeeper' => $row['gatekeeperURL'] ?? '',
                ], '', $row['description'] ?? '', $args) + ['picture' => self::picture($row['imageUUID'] ?? null), 'traffic' => (int) ($row['dwell'] ?? 0)], $rows);
            case 'land':
                [$terms, $values] = SearchFilters::land($asked['type'], $asked['price'], $asked['area'], $asked['ratings'], 's');
                $rows = $search->rows(
                    "SELECT s.*, r.regionname AS region FROM parcelsales AS s LEFT JOIN $regions AS r ON r.regionUUID = s.regionUUID {$where($terms)} ORDER BY s.saleprice, s.parcelname LIMIT $limit",
                    $values,
                );

                return array_map(fn(array $row): array => $this->place($search, [
                    'name' => $row['parcelname'], 'region' => (string) ($row['region'] ?? ''), 'landingpoint' => $row['landingpoint'] ?? '',
                    'gatekeeper' => $row['gatekeeperURL'] ?? '',
                ], sprintf('%s m², %s', number_format((int) $row['area']), number_format((int) $row['saleprice'])), '', $args)
                    + ['area' => (int) $row['area'], 'price' => (int) $row['saleprice']], $rows);
            case 'events':
                [$terms, $values] = SearchFilters::events($asked['when'] === 'day' ? $asked['day'] : 'u', $asked['category'], $asked['ratings'], $asked['q'], time(), 'e');
                // Every column the table has: one of an older version has no landing point until the engine adds it
                $rows = $search->rows("SELECT e.* FROM events AS e {$where($terms)} ORDER BY e.dateUTC LIMIT $limit", $values);
                $categories = self::categories('events');
                $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;

                return array_map(
                    fn(array $row): array => $this->place(
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
                        I18n::date((int) $row['dateUTC'], 'long', 'UTC') . ' UTC, ' . sprintf(I18n::text('%d min'), (int) $row['duration']),
                        $row['description'],
                        $args,
                    ) + [
                        'date' => I18n::date((int) $row['dateUTC'], 'long', 'UTC') . ' UTC',
                        'duration' => sprintf(I18n::text('%d min'), (int) $row['duration']),
                        'category' => isset($categories[(int) ($row['category'] ?? 0)]) ? $texts[$categories[(int) $row['category']]] : '',
                    ],
                    $rows,
                );
            case 'classifieds':
                [$terms, $values] = SearchFilters::classifieds($asked['q'], $asked['category'], $asked['ratings']);
                $terms[] = 'expirationdate > ' . time();
                $rows = $search->rows("SELECT * FROM classifieds {$where($terms)} ORDER BY priceforlisting DESC, creationdate DESC LIMIT $limit", $values);
                $categories = self::categories('classifieds');
                $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;

                return array_map(
                    fn(array $row): array => [
                        'title' => (string) $row['name'],
                        'detail' => trim(($row['parcelname'] ?? '') . (($row['simname'] ?? '') === '' ? '' : ', ' . $row['simname']), ', '),
                        'text' => trim((string) $row['description']),
                        'url' => null,
                        'picture' => self::picture($row['snapshotuuid'] ?? null),
                        'category' => isset($categories[(int) ($row['category'] ?? 0)]) ? $texts[$categories[(int) $row['category']]] : '',
                    ],
                    $rows,
                );
            case 'people':
                if ($search->config === null) {
                    return [];
                }
                try {
                    // Only who published their profile ("show in search" in the viewer), as the search of the viewers does
                    $people = Avatar::all($search->config, ['search' => $asked['q'], 'filters' => ['active' => 'yes', 'technical' => 'no', 'published' => 'yes'], 'perPage' => $limit])->array();
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
