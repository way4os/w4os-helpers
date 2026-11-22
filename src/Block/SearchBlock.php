<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Engine\Hypergrid\Reachable;
use W4OS\Engine\I18n\Catalog;
use W4OS\Engine\Models\Classified;
use W4OS\Engine\Models\Event;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Models\Group;
use W4OS\Engine\Models\LandSale;
use W4OS\Engine\Models\SearchPerson;
use W4OS\Engine\Models\SearchPlace;
use W4OS\Engine\Services\SearchFilters;
use W4OS\Helpers\I18n;

/**
 * The web search, as the search of the viewers: a kind of search at a time (people, groups, places, land for sale, events,
 * classifieds), always offered in the order of their tabs, each with its own form and its own results. Each kind is a model of the
 * engine (MODELS): its query (the one of the search of the viewers), its list (its columns, its sorts) and its page (what its
 * detail shows) are the model's; its links are asked of each result.
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

    /** The model of each kind. */
    public const MODELS = [
        'people' => SearchPerson::class,
        'groups' => Group::class,
        'places' => SearchPlace::class,
        'land' => LandSale::class,
        'events' => Event::class,
        'classifieds' => Classified::class,
    ];

    /** What a search asks, in the address of its page: a site gives each of them to the block (rating is a list). */
    public const PARAMETERS = ['q', 'category', 'rating', 'type', 'price', 'area', 'when', 'day', 'go', 'sort', 'page', 'id'];

    /** The ratings, and the ones asked when a form has not been sent yet (as the viewers: general and moderate). */
    public const RATINGS = ['general', 'moderate', 'adult'];
    public const DEFAULT_RATINGS = ['general', 'moderate'];

    /** The results a page shows, as the viewers (each asks the next page on its own). */
    public const LIMIT = 100;

    /** The fields of a page shown as its text, under its other fields. */
    private const TEXTS = ['description', 'about', 'charter'];

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
        $config = $grid->config();
        [$ready, $note] = self::ready($topic, $asked, $texts);
        $items = [];
        $more = false;
        $selected = null;
        $page = max(1, (int) ($args['page'] ?? 1));
        if ($ready && !$embedded && $config !== null) {
            try {
                [$sort, $descending] = self::sort($topic, $args);
                $found = self::MODELS[$topic]::all($config, [
                    'search' => $topic === 'land' ? '' : $asked['q'],
                    'filters' => self::filters($topic, $asked),
                    'sort' => $sort . ($descending ? ' desc' : ' asc'),
                    'page' => $page,
                    'perPage' => self::LIMIT,
                ]);
                $more = $found->total() > $page * self::LIMIT;
                $items = array_map(fn($model): array => $this->item($topic, $model, $args, $texts), $found->items());
                // The result asked, shown beside the list: read again by its id, with all its page shows (the members of a group)
                $id = isset($args['id']) ? (string) $args['id'] : '';
                $model = $id === '' ? null : self::MODELS[$topic]::find($config, $id);
                $selected = $model === null ? null : $this->item($topic, $model, $args, $texts, true);
            } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
                Blocks::unreachable($e);
                $this->unavailable = true;
            }
        }
        // Each result selects itself (its detail beside the list), the search kept
        $kept = self::query($asked) + array_intersect_key($args, ['sort' => 1]) + ($page > 1 ? ['page' => $page] : []);
        foreach ($items as $i => $item) {
            $items[$i]['select'] = '?' . http_build_query($kept + ['id' => $item['id']]);
            $items[$i]['current'] = $selected !== null && $selected['id'] === $item['id'];
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
            // A search the visitor asked (their form, their words): one made on its own, at the opening of a page, says nothing of none found
            'sent' => $asked['sent'],
            'results' => $items,
            'selected' => $selected,
            // The head of the list: the columns of the kind, each a sort when the kind sorts on it
            'head' => self::head($topic, $asked, $args, $texts),
            'pages' => self::pages($asked, $args, $page, $more, $texts),
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
     * The head of the list of a kind, as its model describes it (its Listing): each column its label and, when it sorts the list,
     * the link that sorts it so (the other way when it is the sort already), what else it asks kept.
     *
     * @return list<array{key:string,label:string,url:?string,current:bool,dir:string}>
     */
    private static function head(string $topic, array $asked, array $args, array $texts): array
    {
        $listing = self::MODELS[$topic]::listing();
        [$sort, $descending] = self::sort($topic, $args);
        $head = [];
        foreach ($listing->document()['columns'] as $column) {
            $key = $column['sort'] ?? null;
            $current = $key !== null && $key === $sort;
            // The way it goes first: its default one when it is the list's, else up
            $first = $key === ltrim($listing->sort, '-') && str_starts_with($listing->sort, '-') ? "-$key" : $key;
            $head[] = [
                'key' => (string) ($key ?? $column['name']),
                'label' => (string) ($texts[$column['label']] ?? $column['label']),
                'url' => $key === null ? null : '?' . http_build_query(self::query($asked) + ['sort' => $current ? ($descending ? $key : "-$key") : $first]),
                'current' => $current,
                'dir' => $current ? ($descending ? 'desc' : 'asc') : '',
            ];
        }

        return $head;
    }

    /**
     * The links to the previous and the next page of results, the sort and what else it asks kept; null where there is none.
     *
     * @return array{previous:?string,next:?string,previous_label:string,next_label:string}
     */
    private static function pages(array $asked, array $args, int $page, bool $more, array $texts): array
    {
        $kept = self::query($asked) + array_intersect_key($args, ['sort' => 1]);
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

    /**
     * The sort asked of a kind (`traffic`, `-traffic` for the other way), one its list takes, else its own.
     *
     * @return array{0:string,1:bool} the sort, whether it goes down
     */
    private static function sort(string $topic, array $args): array
    {
        $listing = self::MODELS[$topic]::listing();
        $asked = trim((string) ($args['sort'] ?? ''));
        $sort = in_array(ltrim($asked, '-'), $listing->sorts(), true) ? $asked : $listing->sort;

        return [ltrim($sort, '-'), str_starts_with($sort, '-')];
    }

    /**
     * The criteria of a search as the filters of the list of its kind (its model's Listing).
     *
     * @return array<string,string>
     */
    private static function filters(string $topic, array $asked): array
    {
        $rating = implode(',', $asked['ratings']);

        return array_filter(match ($topic) {
            'places', 'classifieds' => ['category' => (string) $asked['category'], 'rating' => $rating],
            'land' => ['type' => $asked['type'] === 'any' ? '' : $asked['type'], 'price' => (string) ($asked['price'] ?? ''), 'area' => (string) ($asked['area'] ?? ''), 'rating' => $rating],
            'events' => ['day' => $asked['when'] === 'day' ? (string) $asked['day'] : 'u', 'category' => (string) $asked['category'], 'rating' => $rating],
            default => [],
        }, static fn(string $value): bool => $value !== '');
    }

    /**
     * A result as the block shows it: its name, its columns (the ones of its list, after its name), its links asked of it; with its
     * page (`details`, its text, its members, its actions) when it is the one shown.
     *
     * @return array<string,mixed>
     */
    private function item(string $topic, object $model, array $args, array $texts, bool $page = false): array
    {
        $d = self::sited($model->detail(), $args);
        $columns = array_slice(self::MODELS[$topic]::listing()->document()['columns'], 1);
        $item = [
            'id' => (string) $d['id'],
            'title' => (string) $d['name'],
            'columns' => array_map(fn(array $column): array => [
                'label' => (string) ($texts[$column['label']] ?? $column['label']),
                'value' => $this->value($column, $d, $texts),
                'number' => $column['type'] === 'number',
            ], $columns),
            'links' => $model instanceof Reachable ? Links::all($model) : [],
            'url' => $model instanceof Reachable ? Links::to($model, $args) : ($d['profilePage'] ?? null),
        ];
        if (!$page) {
            return $item;
        }
        $item += ['picture' => null, 'details' => [], 'text' => '', 'actions' => [], 'members' => null];
        foreach (self::MODELS[$topic]::itemForm()->fields as $field) {
            $name = (string) $field['name'];
            $label = (string) ($texts[$field['label']] ?? $field['label']);
            match (true) {
                $field['type'] === 'picture' => $item['picture'] = $d[$name] ?? null,
                $name === 'name' => null,
                in_array($name, self::TEXTS, true) => $item['text'] = trim((string) ($d[$name] ?? '')),
                !empty($field['action']) => ($d[$name] ?? null) === null || $item['actions'][] = ['label' => $label, 'url' => (string) $d[$name]],
                $field['type'] === 'records' => $item['members'] = [
                    'labels' => array_map(static fn(array $part): string => (string) ($texts[$part[1]] ?? $part[1]), $field['parts']),
                    'rows' => array_map(fn(array $record): array => array_map(
                        fn(array $part): array => [
                            'value' => $this->value(['name' => $part[0], 'type' => $part[2]] + ($part[3] ?? []), $record, $texts),
                            'url' => isset($part[3]['link']) ? ($record[$part[3]['link']] ?? null) : null,
                        ],
                        $field['parts'],
                    ), (array) ($d[$name] ?? [])),
                ],
                default => $item['details'][] = [
                    'label' => $label,
                    'value' => $this->value($field, $d, $texts),
                    'url' => $field['type'] === 'link' && isset($field['link']) ? ($d[$field['link']] ?? null) : null,
                ],
            };
        }

        return $item;
    }

    /**
     * The web profiles a result links to at the addresses the site gives (its language), when it gives one: the person, their partner,
     * the founder of a group, its members.
     *
     * @param array<string,mixed> $detail
     * @return array<string,mixed>
     */
    private static function sited(array $detail, array $args): array
    {
        $site = Blocks::siteAddress($args, 'web_profile_url');
        if ($site === null) {
            return $detail;
        }
        $at = static fn(?string $name): ?string => $name === null || $name === '' ? null : \W4OS\Engine\Models\Avatar::profileAt($site, $name);
        foreach (['profilePage' => 'name', 'partnerPage' => 'partnerName', 'founderPage' => 'founderName'] as $page => $name) {
            array_key_exists($page, $detail) && ($detail[$page] = $at($detail[$name] ?? null));
        }
        isset($detail['roster']) && ($detail['roster'] = array_map(static fn(array $m): array => ['page' => $at($m['name'])] + $m, $detail['roster']));

        return $detail;
    }

    /**
     * A value of a result as its column or its field says it shows (its type): a number with its decimals and its unit, a date, a
     * word of the catalog, yes or no; what an empty one says (`empty`).
     *
     * @param array<string,mixed> $field a column of its list or a field of its page
     */
    private function value(array $field, array $item, array $texts): string
    {
        $value = $item[$field['name']] ?? null;
        if (isset($field['online']) && !empty($item[$field['online']])) {
            return (string) ($texts[$field['onlineLabel']] ?? '');
        }
        if ($value === null || $value === '' || $value === []) {
            return isset($field['empty']) ? (string) ($texts[$field['empty']] ?? '') : '';
        }
        $days = static fn(int $time): string => ProfileBlock::age(intdiv(max(0, time() - $time), 86400));

        return match ($field['type']) {
            'number' => number_format((float) $value, (int) ($field['decimals'] ?? 0)) . (isset($field['unit']) ? ' ' . $field['unit'] : ''),
            'date' => I18n::date((int) $value, 'date') . (empty($field['age']) ? '' : ' (' . $days((int) $value) . ')'),
            'datetime' => I18n::date((int) $value, 'long', 'UTC') . ' UTC',
            'boolean' => $value ? $texts['web_search_yes'] : $texts['web_search_no'],
            'select' => (string) ($texts[$field['options'][(string) $value] ?? ''] ?? ''),
            'list' => implode(', ', array_map(static fn($one): string => (string) ($texts[array_column((array) ($field['values'] ?? []), 'label', 'value')[$one] ?? ''] ?? $one), (array) $value)),
            'role' => (string) ($texts[Catalog::roleKey((string) $value)] ?? $value),
            default => (string) $value,
        };
    }
}
