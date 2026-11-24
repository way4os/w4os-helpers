<?php

declare(strict_types=1);

/**
 * The web search and the search of the viewers (query.php) find the same: for each kind of search and each criteria a viewer can
 * give (words, a category, the ratings, the price and the area of land, the day of events), the same items, on the same search.
 */

use W4OS\Engine\Data\Connections;
use W4OS\Engine\Data\OSPDO;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Services\SearchFilters;
use W4OS\Engine\Services\SearchQuery;
use W4OS\Engine\Testing\ClockFake;
use W4OS\Engine\Testing\Machine;
use W4OS\Helpers\Block\Blocks;

/** A search with places, land for sale, events and classifieds of every rating and of several categories. */
function parity_search(): array
{
    $machine = new Machine();
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $now = time();
    $pdo->exec('CREATE TABLE regionsregister (regionUUID CHAR(36), regionname VARCHAR(255))');
    $pdo->exec("INSERT INTO regionsregister VALUES ('r1', 'Welcome'), ('r2', 'Plaza')");
    $pdo->exec('CREATE TABLE allparcels (regionUUID CHAR(36), parcelname VARCHAR(255), ownerUUID CHAR(36), groupUUID CHAR(36), landingpoint VARCHAR(255), parcelUUID CHAR(36), infoUUID CHAR(36), parcelarea INT, gatekeeperURL VARCHAR(255))');
    $pdo->exec('CREATE TABLE parcels (parcelUUID CHAR(36), regionUUID CHAR(36), parcelname VARCHAR(255), description TEXT, landingpoint VARCHAR(255), searchcategory INT, mature VARCHAR(10), dwell FLOAT, imageUUID CHAR(36), infouuid CHAR(36), gatekeeperURL VARCHAR(255))');
    $pdo->exec("INSERT INTO parcels VALUES
        ('p1', 'r1', 'Beach house', 'Quiet sand', '10/20/30', 10, 'PG', 12, NULL, 'i1', 'http://grid.example.org:8002'),
        ('p2', 'r1', 'Beach club', 'Night and sand', '1/2/3', 7, 'Mature', 50, NULL, 'i2', 'http://grid.example.org:8002'),
        ('p3', 'r2', 'Club Noir', 'Adults only', '5/5/5', 7, 'Adult', 80, NULL, 'i3', 'http://grid.example.org:8002'),
        ('p4', 'r2', 'Plaza shop', 'Beach wear', '6/6/6', 11, 'PG', 3, NULL, 'i4', 'http://grid.example.org:8002')");
    $pdo->exec('CREATE TABLE parcelsales (regionUUID CHAR(36), parcelname VARCHAR(255), parcelUUID CHAR(36), area INT, saleprice INT, landingpoint VARCHAR(255), infoUUID CHAR(36), dwell INT, parentestate INT, mature VARCHAR(10), gatekeeperURL VARCHAR(255))');
    $pdo->exec("INSERT INTO parcelsales VALUES
        ('r1', 'Lot mainland', 's1', 512, 1000, '5/5/25', 'j1', 0, 1, 'PG', 'http://grid.example.org:8002'),
        ('r1', 'Lot estate', 's2', 1024, 400, '6/6/25', 'j2', 0, 2, 'Mature', 'http://grid.example.org:8002'),
        ('r2', 'Lot adult', 's3', 256, 0, '7/7/25', 'j3', 0, 3, 'Adult', 'http://grid.example.org:8002')");
    $pdo->exec('CREATE TABLE events (owneruuid CHAR(36), name VARCHAR(255), eventid INTEGER PRIMARY KEY, creatoruuid CHAR(36), category INT, description TEXT, dateUTC INT, duration INT, covercharge INT, coveramount INT, simname VARCHAR(255), parcelUUID CHAR(36), globalPos VARCHAR(255), eventflags INT, gatekeeperURL VARCHAR(255), landingpoint VARCHAR(35))');
    $event = static fn(string $name, int $category, string $text, int $at, int $flags): string => "('o', '$name', NULL, 'c', $category, '$text', $at, 60, 0, 0, 'Welcome', 'p', '128,128,25', $flags, 'http://grid.example.org:8002', '128/128/25')";
    $pdo->exec('INSERT INTO events VALUES ' . implode(', ', [
        $event('Jazz night', 20, 'Live jazz', $now + 3600, 0),
        $event('Jazz club', 20, 'Late jazz', $now + 7200, 1),
        $event('Adult party', 23, 'Night', $now + 3600, 2),
        $event('Tomorrow talk', 18, 'Discussion', $now + 90000, 0),
        $event('Gone', 19, 'Over', $now - 90000, 0),
    ]));
    $pdo->exec('CREATE TABLE classifieds (classifieduuid CHAR(36), creatoruuid CHAR(36), creationdate INT, expirationdate INT, category INT, name VARCHAR(255), description TEXT, parceluuid CHAR(36), parentestate INT, snapshotuuid CHAR(36), simname VARCHAR(255), posglobal VARCHAR(255), parcelname VARCHAR(255), classifiedflags INT, priceforlisting INT)');
    $pdo->exec("INSERT INTO classifieds VALUES
        ('c1', 'u', 1, " . ($now + 86400) . ", 1, 'Shoes', 'New shoes', 'p', 1, NULL, 'Welcome', '256010,256020,25', 'Shop', 4, 10),
        ('c2', 'u', 2, " . ($now + 86400) . ", 2, 'Land to rent', 'Big lot', 'p', 1, NULL, 'Plaza', '256010,256020,25', 'Lot', 8, 50),
        ('c3', 'u', 3, " . ($now + 86400) . ", 9, 'Adult shoes', 'Shoes', 'p', 1, NULL, 'Plaza', '256010,256020,25', 'Club', 64, 30),
        ('c4', 'u', 4, " . ($now - 10) . ", 1, 'Old shoes', 'Expired', 'p', 1, NULL, 'Welcome', '256010,256020,25', 'Shop', 4, 99)");
    Connections::using(static fn() => $pdo);
    $clock = new ClockFake();
    $clock->now = (float) $now;

    return [Grid::find($machine->environment, 'demo'), new SearchQuery($pdo, 'regionsregister', profiles: $pdo, clock: $clock)];
}

/** The names a list of results has, in order of name. */
function parity_names(array $items, string $key): array
{
    $names = array_map('strval', array_column($items, $key));
    sort($names);

    return $names;
}

/** @return list<list<string>> every combination of the ratings, none too */
function parity_ratings(): array
{
    return [[], ['general'], ['moderate'], ['adult'], ['general', 'moderate'], ['moderate', 'adult'], ['general', 'moderate', 'adult']];
}

afterEach(fn() => Connections::using(null));

describe('The web search and the search of the viewers', function () {
    test('find the same places, by words, category and ratings', function () {
        [$grid, $viewer] = parity_search();
        $compared = 0;
        foreach (['beach', 'club', 'sand', 'plaza'] as $q) {
            foreach ([0, 7, 10, 11] as $category) {
                foreach (parity_ratings() as $ratings) {
                    $web = Blocks::data('search', $grid, ['topic' => 'places', 'q' => $q, 'category' => $category, 'rating' => $ratings, 'go' => 1, 'level' => 'h1'])['results'];
                    $bits = array_sum(array_intersect_key(SearchFilters::RATING_BITS, array_flip($ratings)));
                    $asked = $viewer->places(['text' => $q, 'category' => $category, 'flags' => $bits]);

                    expect(parity_names($web, 'name'))->toBe(parity_names($asked['data'], 'name'), "places $q $category " . implode(',', $ratings));
                    $compared += count($web);
                }
            }
        }

        expect($compared)->toBeGreaterThan(20);
    });

    test('find the same land for sale, by type, price, area and ratings', function () {
        [$grid, $viewer] = parity_search();
        $types = ['any' => 4294967295, 'mainland' => 8, 'estate' => 16];
        $compared = 0;
        foreach ($types as $type => $typeBits) {
            foreach ([null, 0, 500] as $price) {
                foreach ([null, 600] as $area) {
                    foreach (parity_ratings() as $ratings) {
                        $web = Blocks::data('search', $grid, ['topic' => 'land', 'type' => $type, 'price' => $price, 'area' => $area, 'rating' => $ratings, 'go' => 1, 'level' => 'h1'])['results'];
                        $bits = array_sum(array_intersect_key(SearchFilters::RATING_BITS, array_flip($ratings)))
                            | ($price === null ? 0 : 1 << 20) | ($area === null ? 0 : 1 << 21);
                        $asked = $viewer->land(['type' => $typeBits, 'flags' => $bits, 'price' => $price ?? 0, 'area' => $area ?? 0]);

                        expect(parity_names($web, 'name'))->toBe(parity_names($asked['data'], 'name'), "land $type $price $area " . implode(',', $ratings));
                        $compared += count($web);
                    }
                }
            }
        }

        expect($compared)->toBeGreaterThan(10);
    });

    test('find the same events, to come or of a day, by category, ratings and words', function () {
        [$grid, $viewer] = parity_search();
        $compared = 0;
        // The days a visitor can ask: from the one of the first event to come (a day before it is that one)
        $first = (int) round((strtotime(date('Y-m-d', time() + 3600) . ' 12:00') - strtotime('today 12:00')) / 86400);
        foreach (['u', $first, $first + 1] as $day) {
            foreach ([0, 20, 23] as $category) {
                foreach (['', 'jazz', 'late'] as $words) {
                    foreach (parity_ratings() as $ratings) {
                        $web = Blocks::data('search', $grid, ['topic' => 'events', 'q' => $words, 'when' => $day === 'u' ? 'u' : 'day', 'day' => $day === 'u' ? 0 : $day, 'category' => $category, 'rating' => $ratings, 'go' => 1, 'level' => 'h1'])['results'];
                        $bits = array_sum(array_intersect_key(SearchFilters::RATING_BITS, array_flip($ratings)));
                        $asked = $viewer->events(['text' => "$day|$category|$words", 'flags' => $bits]);

                        expect(parity_names($web, 'name'))->toBe(parity_names($asked['data'], 'name'), "events $day $category $words " . implode(',', $ratings));
                        $compared += count($web);
                    }
                }
            }
        }

        expect($compared)->toBeGreaterThan(20);
    });

    test('give the same events over their pages, a page as long as the one of the viewers', function () {
        [$grid, $viewer] = parity_search();
        $pdo = Connections::pdo($grid->config(), \W4OS\Engine\Data\Database::Search);
        $now = time();
        foreach (range(1, 150) as $n) {
            $pdo->exec(sprintf("INSERT INTO events (owneruuid, name, creatoruuid, category, description, dateUTC, duration, covercharge, coveramount, simname, parcelUUID, globalPos, eventflags, gatekeeperURL, landingpoint) VALUES ('o', 'Party %03d', 'c', 23, 'Dance', %d, 60, 0, 0, 'Welcome', 'p', '128,128,25', 0, 'http://grid.example.org:8002', '128/128/25')", $n, $now + $n * 60));
        }
        $args = ['topic' => 'events', 'q' => 'party', 'when' => 'u', 'rating' => ['general'], 'go' => 1, 'level' => 'h1'];
        $first = Blocks::data('search', $grid, $args);
        $second = Blocks::data('search', $grid, $args + ['page' => 2]);
        $asked = [...$viewer->events(['text' => 'u|0|party', 'flags' => 1 << 24, 'query_start' => 0])['data'], ...$viewer->events(['text' => 'u|0|party', 'flags' => 1 << 24, 'query_start' => 100])['data']];

        expect($first['results'])->toHaveCount(100)
            ->and($first['list']->list->pages)->toBe(2)
            ->and($second['results'])->toHaveCount(50)
            ->and($second['list']->list->page)->toBe(2)
            ->and(parity_names([...$first['results'], ...$second['results']], 'name'))->toBe(array_values(array_unique(parity_names($asked, 'name'))));
    });

    test('find the same classifieds, by words, category and ratings, none expired', function () {
        [$grid, $viewer] = parity_search();
        $compared = 0;
        foreach (['', 'shoes', 'lot'] as $q) {
            foreach ([0, 1, 2, 9] as $category) {
                foreach (parity_ratings() as $ratings) {
                    $web = Blocks::data('search', $grid, ['topic' => 'classifieds', 'q' => $q, 'category' => $category, 'rating' => $ratings, 'go' => 1, 'level' => 'h1'])['results'];
                    $bits = array_sum(array_intersect_key(SearchFilters::CLASSIFIED_BITS, array_flip($ratings)));
                    $asked = $viewer->classifieds(['text' => $q, 'category' => $category, 'flags' => $bits]);

                    expect(parity_names($web, 'name'))->toBe(parity_names($asked['data'], 'name'), "classifieds $q $category " . implode(',', $ratings))
                        ->and(parity_names($web, 'name'))->not->toContain('Old shoes');
                    $compared += count($web);
                }
            }
        }

        expect($compared)->toBeGreaterThan(10);
    });
});

describe('The text of the search', function () {
    test('is UTF-8 from the table to the web page and the answer of the viewers: accents, emojis, full-width signs', function () {
        [$grid, $viewer] = parity_search();
        $pdo = Connections::pdo($grid->config(), \W4OS\Engine\Data\Database::Search);
        $name = 'Soirée 🎉 DJ LaRissa＠The Night Owls';
        $pdo->prepare("INSERT INTO events (owneruuid, name, creatoruuid, category, description, dateUTC, duration, covercharge, coveramount, simname, parcelUUID, globalPos, eventflags, gatekeeperURL, landingpoint) VALUES ('o', ?, 'c', 20, ?, ?, 60, 0, 0, 'Welcome', 'p', '128,128,25', 0, 'http://grid.example.org:8002', '128/128/25')")
            ->execute([$name, 'Café 🌙 & dance', time() + 1800]);
        $args = ['topic' => 'events', 'q' => 'soirée', 'when' => 'u', 'rating' => ['general'], 'go' => 1, 'level' => 'h1', 'lang' => 'en'];
        $web = Blocks::data('search', $grid, $args)['results'];
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('search', $grid, $args);
        $asked = $viewer->events(['text' => 'u|0|soirée', 'flags' => 1 << 24])['data'];

        expect(array_column($web, 'name'))->toBe([$name])
            ->and($html)->toContain($name)->toContain('Café 🌙 &amp; dance')
            ->and(array_column($asked, 'name'))->toBe([$name]);
    });
});

describe('The results of the web search', function () {
    test('each have their picture: their own, the one of the parcel of their place, none without one (never the image of a title)', function () {
        [$grid] = parity_search();
        foreach (['places' => ['q' => 'beach'], 'land' => [], 'events' => ['when' => 'u'], 'classifieds' => ['q' => 'shoes']] as $topic => $asked) {
            $data = Blocks::data('search', $grid, ['topic' => $topic, 'go' => 1, 'level' => 'h1'] + $asked);

            // No parcel of the fixture has a picture
            expect($data['results'])->not->toBe([], $topic)
                ->and(array_filter(array_column($data['results'], 'picture')))->toBe([], $topic)
                ->and($data['selected']['picture'] ?? null)->toBeNull($topic);
        }
    });
});

describe('The day of the events', function () {
    test('is chosen with a date, never before the first event to come: a date before it is that one, never an error', function () {
        [$grid] = parity_search();
        $first = date('Y-m-d', time() + 3600);
        $when = static fn(array $args): array => array_values(array_filter(Blocks::data('search', $grid, ['topic' => 'events', 'go' => 1, 'level' => 'h1'] + $args)['fields'], static fn(array $f): bool => $f['type'] === 'when'))[0];
        // The day of the talk to come (25 hours from now)
        $tomorrow = date('Y-m-d', time() + 90000);

        $asked = $when(['when' => 'day', 'date' => $tomorrow]);
        $past = $when(['when' => 'day', 'date' => '2001-01-01']);
        $data = Blocks::data('search', $grid, ['topic' => 'events', 'go' => 1, 'level' => 'h1', 'when' => 'day', 'date' => $tomorrow]);

        expect([$asked['iso'], $asked['min'], $asked['value']])->toBe([$tomorrow, $first, 'day'])
            ->and($past['iso'])->toBe($first)
            // The same events as the day of its arrows
            ->and(array_column($data['results'], 'name'))->toBe(array_column(Blocks::data('search', $grid, ['topic' => 'events', 'go' => 1, 'level' => 'h1', 'when' => 'day', 'day' => $asked['day']])['results'], 'name'))
            ->and(array_column($data['results'], 'name'))->toContain('Tomorrow talk');
    });
});

describe('The list of the results', function () {
    test('shows no picture in its rows, in any kind: the name and the time; the picture is in the detail', function () {
        [$grid] = parity_search();
        $view = new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates');
        foreach (['places' => ['q' => 'beach'], 'land' => [], 'events' => ['when' => 'u'], 'classifieds' => ['q' => 'shoes']] as $topic => $asked) {
            $html = $view->block('search', $grid, ['topic' => $topic, 'go' => 1, 'level' => 'h1', 'lang' => 'en'] + $asked);
            $list = substr($html, (int) strpos($html, 'w4os-ld-list'), (int) strpos($html, 'id="search-detail"') - (int) strpos($html, 'w4os-ld-list'));

            // No picture in the rows; none in the detail of a result without one
            expect($list)->not->toContain('<img', $topic)
                ->and($html)->not->toContain('w4os-detail-picture', $topic);
        }
    });
});

