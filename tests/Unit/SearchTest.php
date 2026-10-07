<?php
/**
 * Block search: the search of the web, its results by kind, the links a viewer follows, the words of the visitor taken as
 * words and not as a query.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';
require_once dirname(__DIR__, 2) . '/classes/class-router.php';

/** A database that answers the queries of the search with the rows of its fixtures, and keeps what it was asked. */
function search_db(array &$asked): object
{
    return new class($asked) {
        public function __construct(private array &$asked) {}

        public function prepareAndExecute($sql, $params = [])
        {
            $this->asked[] = [$sql, $params];
            $rows = match (true) {
                str_contains($sql, 'FROM parcelsales') => [['name' => 'Sale Meadow', 'area' => 2048, 'saleprice' => 500, 'landingpoint' => '10.5/20.2/30', 'gatekeeper' => 'http://play.example.org:8002', 'region' => 'Alpha']],
                str_contains($sql, 'FROM parcels') => [['name' => 'Garden of Alpha', 'description' => 'A garden', 'landingpoint' => '128/128/25', 'gatekeeper' => 'http://play.example.org:8002', 'dwell' => 12, 'region' => 'Alpha']],
                str_contains($sql, 'FROM events') => [['name' => 'Concert', 'description' => 'Music', 'dateUTC' => 1900000000, 'duration' => 90, 'region' => 'Beta', 'landingpoint' => '1/2/3', 'gatekeeper' => '']],
                str_contains($sql, 'FROM classifieds') => [['name' => 'Land to rent', 'description' => 'Cheap', 'region' => 'Alpha', 'parcelname' => 'Meadow']],
                str_contains($sql, 'FROM UserAccounts') => [['FirstName' => 'Jane', 'LastName' => 'Doe']],
                default => [],
            };

            return new class($rows) {
                public function __construct(private array $rows) {}

                public function fetchAll($mode = null)
                {
                    return $this->rows;
                }
            };
        }
    };
}

describe('The search of the web', function () {
    beforeEach(function () {
        unset($_SERVER['PATH_INFO']);
        $GLOBALS['SearchDB'] = null;
    });

    test('takes what is looked for as words: what means something to LIKE is not taken as it', function () {
        expect(OpenSim_Helpers_Block_Search::like('50% off_now'))->toBe('%50\\% off\\_now%');
    });

    test('is for a kind of results when the path says it, or the query', function () {
        $_SERVER['PATH_INFO'] = '/land/';

        expect(OpenSim_Helpers_Block_Search::topic([]))
            ->toBe('land')
            ->and(OpenSim_Helpers_Block_Search::topic(['topic' => 'events']))
            ->toBe('events')
            // What is not a kind is all of them
            ->and(OpenSim_Helpers_Block_Search::topic(['topic' => 'nothing']))
            ->toBe('');
        unset($_SERVER['PATH_INFO']);
        expect(OpenSim_Helpers_Block_Search::topic([]))->toBe('');
    });

    test('gives the link a viewer follows to a place: the region of its grid, at its landing point', function () {
        expect(OpenSim_Helpers_Block_Search::hop('http://play.example.org:8002', 'Alpha', '10.5/20.2/30.9'))
            ->toBe('hop://play.example.org:8002/Alpha/10/20/30')
            // Without a grid it is the one of the site, without a landing point the region
            ->and(OpenSim_Helpers_Block_Search::hop('http://play.example.org:8002/', 'Alpha Two', ''))
            ->toBe('hop://play.example.org:8002/Alpha+Two')
            ->and(OpenSim_Helpers_Block_Search::hop('http://play.example.org:8002', '', '1/2/3'))
            ->toBeNull();
    });

    test('finds the places, the land, the events, the classifieds and the people, each with what to say and where to go', function () {
        $asked = [];
        $GLOBALS['SearchDB'] = search_db($asked);

        $places = OpenSim_Helpers_Block_Search::find('places', 'garden');
        $land = OpenSim_Helpers_Block_Search::find('land', '');

        expect($places[0])
            ->toBe(['title' => 'Garden of Alpha', 'detail' => 'Alpha', 'text' => 'A garden', 'url' => 'hop://play.example.org:8002/Alpha/128/128/25'])
            ->and($land[0]['detail'])
            ->toBe('Alpha · 2,048 m², 500')
            ->and(OpenSim_Helpers_Block_Search::find('events', '')[0])
            ->toMatchArray(['title' => 'Concert', 'text' => 'Music'])
            ->and(OpenSim_Helpers_Block_Search::find('classifieds', 'rent')[0])
            ->toMatchArray(['title' => 'Land to rent', 'detail' => 'Meadow, Alpha', 'url' => null]);
        // What the visitor wrote is a value, never part of the query
        [$sql, $params] = $asked[0];

        expect($sql)->toContain('LIKE :q')->not->toContain('garden')->and($params)->toBe(['q' => '%garden%']);
    });

    test('lists nothing for a kind it cannot find, and does not fail without a database', function () {
        expect(OpenSim_Helpers_Block_Search::find('places', 'x'))->toBe([])->and(OpenSim_Helpers_Block_Search::find('unknown', 'x'))->toBe([]);
    });

    test('shows the results grouped by kind for a word, none for the people when nothing is asked, and the form alone when embedded', function () {
        $asked = [];
        $GLOBALS['SearchDB'] = search_db($asked);

        $page = OpenSim_Helpers_Block_Search::data(['q' => 'garden', 'level' => 'h1']);
        $browse = OpenSim_Helpers_Block_Search::data(['level' => 'h1']);
        $embedded = OpenSim_Helpers_Block_Search::data([]);

        expect(array_column($page['groups'], 'topic'))
            ->toBe(['places', 'land', 'events', 'classifieds'])
            ->and($page['q'])
            ->toBe('garden')
            // Nobody lists the people of a grid
            ->and(array_column($browse['groups'], 'topic'))
            ->not->toContain('people')
            ->and($embedded)
            ->toMatchArray(['embedded' => true, 'groups' => [], 'form' => true]);
    });

    test('is a page of its own that shows, and a form that goes to it', function () {
        $asked = [];
        $GLOBALS['SearchDB'] = search_db($asked);
        $_GET = ['q' => 'garden'];

        $html = OpenSim_Helpers_Blocks::render('search', ['q' => 'garden', 'level' => 'h1']);

        expect($html)
            ->toContain('<form class="search-form" action="/search"')
            ->toContain('value="garden"')
            ->toContain('<a href="hop://play.example.org:8002/Alpha/128/128/25">Garden of Alpha</a>')
            ->toContain('search-topics');
        $_GET = [];
    });
});
