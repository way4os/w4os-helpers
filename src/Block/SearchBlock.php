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
use W4OS\Views\Contract as ViewsContract;
use W4OS\Views\ListDetailView;
use W4OS\Views\ListView;

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
    public const PARAMETERS = ['q', 'category', 'rating', 'type', 'price', 'area', 'when', 'day', 'date', 'go', 'sort', 'page', 'id'];

    /** The ratings, and the ones asked when a form has not been sent yet (as the viewers: general and moderate). */
    public const RATINGS = ['general', 'moderate', 'adult'];
    public const DEFAULT_RATINGS = ['general', 'moderate'];

    /** The results a page shows, as the viewers (each asks the next page on its own). */
    public const LIMIT = 100;

    /** Whether a part of the search could not be done (a database of the grid that cannot be reached) */
    private bool $unavailable = false;

    /**
     * The kinds of search a grid can answer: all of them, but the people and the groups without the database of the grid (a host of
     * the search alone).
     *
     * @return list<string>
     */
    public static function topics(Grid $grid): array
    {
        return $grid->hasDatabase() ? self::TOPICS : array_values(array_diff(self::TOPICS, ['people', 'groups']));
    }

    public function data(Grid $grid, array $args = []): array
    {
        $this->unavailable = false;
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        $topics = self::topics($grid);
        $topic = in_array($args['topic'] ?? '', $topics, true) ? (string) $args['topic'] : 'places';
        $asked = self::criteria($topic, $args);
        // The events of a day: never before the first one to come (a date before it is that one)
        $asked['first'] = $topic === 'events' ? self::firstDay($grid) : 0;
        $asked['day'] = max($asked['day'], $asked['first']);
        // On a page of its own (a level, or a kind in its address) it shows results; embedded in another page it is the form alone,
        // until it is used
        $embedded = !isset($args['level']) && !isset($args['topic']) && !$asked['sent'];
        $config = $grid->config();
        [$ready, $note] = self::ready($topic, $asked, $texts);
        $page = max(1, (int) ($args['page'] ?? 1));
        // The search kept on every link of its list (its sorts, its pages, the results it selects)
        $query = self::query($asked) + array_filter(['sort' => (string) ($args['sort'] ?? ''), 'page' => $page > 1 ? $page : null, 'id' => (string) ($args['id'] ?? '')]);
        $type = self::MODELS[$topic]::type();
        $list = self::contract()->listOf($type) ?? [];
        $view = null;
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
                $rows = array_map(fn($model): array => self::row($model, $args, $texts, $grid), $found->items());
                $list = ListView::of($list, $rows, ['total' => $found->total(), 'page' => $page, 'perPage' => self::LIMIT], $query);
                // The result asked, shown beside the list: read again by its id, with all its page shows (the members of a group); the
                // first one when none is, as the search of the viewers shows it from the start
                $id = (string) ($args['id'] ?? '');
                $id === '' && $rows !== [] && ($id = (string) ($rows[0]['id'] ?? ''));
                $model = $id === '' ? null : self::MODELS[$topic]::find($config, $id);
                $view = ListDetailView::of($list, $model === null ? null : self::row($model, $args, $texts, $grid), self::groups($type), param: 'id', region: 'search-detail');
            } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
                Blocks::unreachable($e);
                $this->unavailable = true;
            }
        }

        return [
            // The page of the search, a noun, as a site takes it (View::blockTitle())
            'title' => \W4OS\Helpers\View\View::blockTitle('search', I18n::current()),
            'form' => true,
            'embedded' => $embedded,
            'action' => self::page($grid, $args) . "/$topic/",
            'topic' => $topic,
            'q' => $asked['q'],
            'topics' => array_map(
                // The words go with the kind chosen: they are the same search, asked another way
                fn(string $kind): array => ['topic' => $kind, 'title' => $texts["web_search_$kind"], 'url' => self::page($grid, $args) . "/$kind/" . ($asked['q'] === '' ? '' : '?q=' . rawurlencode($asked['q'])), 'current' => $kind === $topic],
                $topics,
            ),
            'fields' => self::fields($topic, $asked, $texts, (string) ($config?->find('LoginService', 'Currency') ?: 'L$')),
            'submit' => $texts['web_search_submit'],
            'asked' => $ready,
            'note' => $note,
            // The list and the detail of the result asked (w4os-views); what it says without a result, of a search the visitor
            // asked only (their form, their words): one made on its own, at the opening of a page, says nothing
            'list' => $view,
            // The same as plain values: each result as its model gives it, and the one asked
            'results' => $view === null ? [] : array_map(static fn($row): array => $row->item, $view->list->rows),
            'selected' => $view?->detail?->item,
            'empty' => $asked['sent'] ? 'web_search_nothing' : null,
            'sent' => $asked['sent'],
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
            // A day from today: a date (YYYY-MM-DD, the date picker), else a number of days (its arrows)
            'day' => self::dayOf($args),
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
    private static function fields(string $topic, array $asked, array $texts, string $currency = 'L$'): array
    {
        $fields = [];
        // Its words: the field alone, its label for the readers of the screen only (what to type is plain), its few letters said in it
        $words = ['name' => 'q', 'type' => 'text', 'value' => $asked['q']];
        $fields[] = match ($topic) {
            'places' => $words + ['label' => $texts['web_search_words'], 'required' => true, 'min' => SearchFilters::MIN_TEXT, 'placeholder' => sprintf($texts['web_search_min_letters'], SearchFilters::MIN_TEXT)],
            'people', 'groups' => $words + ['label' => $texts['web_search_words'], 'required' => true],
            'land' => null,
            default => $words + ['label' => $texts['web_search_words']],
        };
        if ($topic === 'land') {
            $fields[] = ['name' => 'type', 'type' => 'select', 'label' => $texts['web_search_land_type'], 'value' => $asked['type'], 'options' => array_map(
                static fn(string $type): array => ['value' => $type, 'label' => $texts["web_search_land_$type"], 'selected' => $type === $asked['type']],
                SearchFilters::LAND_TYPES,
            )];
            // The only ones a label shows, their unit in the field
            $fields[] = ['name' => 'price', 'type' => 'number', 'label' => $texts['web_search_price_max'], 'value' => $asked['price'], 'suffix' => $currency, 'shown' => true];
            $fields[] = ['name' => 'area', 'type' => 'number', 'label' => $texts['web_search_area_min'], 'value' => $asked['area'], 'suffix' => 'm²', 'shown' => true];
        }
        if ($topic === 'events') {
            $date = I18n::date(time() + $asked['day'] * 86400, 'date');
            $fields[] = ['name' => 'when', 'type' => 'when', 'label' => $texts['web_search_when'], 'value' => $asked['when'], 'day' => $asked['day'], 'date' => $date,
                // The date picker: its day, never before the first event to come (its min)
                'iso' => date('Y-m-d', time() + $asked['day'] * 86400), 'min' => date('Y-m-d', time() + $asked['first'] * 86400), 'at_min' => $asked['day'] <= $asked['first'], 'options' => [
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

    /** The day asked, in days from today: its date (YYYY-MM-DD) when it gives one, else its number of days; a year at most each way. */
    private static function dayOf(array $args): int
    {
        $date = (string) ($args['date'] ?? '');
        $at = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? strtotime("$date 12:00") : false;
        $day = $at !== false ? (int) round(($at - strtotime('today 12:00')) / 86400) : (int) ($args['day'] ?? 0);

        return max(-366, min(366, $day));
    }

    /** The day of the first event to come, in days from today (-1 when it began yesterday), 0 without one. */
    private static function firstDay(Grid $grid): int
    {
        $config = $grid->config();
        try {
            $first = $config === null ? null : (\W4OS\Engine\Models\Event::all($config, ['filters' => ['day' => 'u'], 'sort' => 'date asc', 'perPage' => 1])->items()[0] ?? null);
        } catch (\W4OS\Engine\Exceptions\ChannelUnavailable) {
            $first = null;
        }
        $at = $first === null ? null : (int) ($first->detail()['date'] ?? 0);

        return $at === null || $at <= 0 ? 0 : (int) round((strtotime(date('Y-m-d', $at) . ' 12:00') - strtotime('today 12:00')) / 86400);
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
     * A result as its list and its page show it: what its model gives, its links asked of it (`tpLinks`), the web profiles it links
     * to at the site's addresses, and the actions of its page (`searchActions`: its fields that are an action, a profile to open).
     *
     * @return array<string,mixed>
     */
    private static function row(object $model, array $args, array $texts, Grid $grid): array
    {
        $row = self::sited($model->detail(), $args);
        // Its picture: its own, else the one of the parcel of its place, when this host can show it; none without one (never the
        // image of its title in a detail, the cards' alone)
        $model instanceof Reachable && ($url = (string) $model->tpUri()) !== ''
            && ($row['picture'] = $grid->shownPicture($url, (string) ($row['name'] ?? ''), isset($row['picture']) && is_string($row['picture']) ? $row['picture'] : null, title: false));
        // An image of a title is no picture there (a result without a place)
        is_string($row['picture'] ?? null) && !\W4OS\Engine\Text\Uuid::isValid($row['picture']) && ($row['picture'] = null);
        // Its description without the links a calendar adds for the viewers: the page has its own teleport link
        is_string($row['description'] ?? null) && ($row['description'] = self::webText($row['description']));
        $model instanceof Reachable && ($row['tpLinks'] = Links::all($model));
        $row['searchActions'] = [];
        foreach ($model::itemForm()->fields as $field) {
            empty($field['action']) || empty($row[$field['name']]) || $row['searchActions'][] = ['label' => (string) ($texts[$field['label']] ?? $field['label']), 'url' => (string) $row[$field['name']]];
        }

        return $row;
    }

    /**
     * A text of a result as a web page shows it: without the hop:// and secondlife:///app/teleport/ links a calendar adds for the
     * viewers (the page has its own teleport link), never another address; the lines they leave empty go with them.
     *
     * Transitional: the engine stores the events without them and adds them to the answer of the viewers only
     * (SearchQuery::viewerDescription()); this is for a search the engine does not keep (an aggregator of before, MaintainSearch = false).
     */
    public static function webText(string $text): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $kept = trim((string) preg_replace(['#(?<!\S)(hop://|secondlife:///app/teleport/)\S+#iu', '/[ \t]{2,}/'], ['', ' '], $line));
            // A line of links only is gone; an empty line of the text stays
            ($kept === '' && trim($line) !== '') || $lines[] = $kept;
        }

        return implode("\n", $lines);
    }

    /**
     * The groups of fields of the page of a kind, as the contract describes it (x-form): the ones that are an action left to the
     * actions of the page.
     *
     * @return list<array{label:string,fields:list<array<string,mixed>>}>
     */
    private static function groups(string $type): array
    {
        $form = self::contract()->itemOf($type) ?? ['groups' => [], 'fields' => []];

        return array_map(static fn(array $group): array => [
            'label' => $group['label'],
            'fields' => array_values(array_filter($form['fields'], static fn(array $field): bool => ($field['group'] ?? '') === $group['name'] && empty($field['action']))),
        ], $form['groups']);
    }

    /** The contract of the engine as w4os-views reads it: what each list and each page show. */
    private static function contract(): ViewsContract
    {
        static $contract = null;

        return $contract ??= new ViewsContract(\W4OS\Engine\Api\Contract::document());
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
}
