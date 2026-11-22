<?php

use W4OS\Engine\Data\Connections;
use W4OS\Engine\Data\OSPDO;
use W4OS\Helpers\Block\Blocks;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Testing\Machine;

/** A grid whose accounts are in one database: Ada published her profile ("show in search"), Mona did not. */
function people_grid(): Grid
{
    $machine = new Machine();
    $machine->write(
        $machine->grid . '/Robust.HG.ini',
        (string) file_get_contents($machine->grid . '/Robust.HG.ini') .
            "\n[DatabaseService]\n    ConnectionString = \"Data Source=localhost;Database=robust;User ID=os;Password=pw;\"\n" .
            "[GridInfoService]\n    web_profile_url = \"https://demo.example.org/profile/[AGENT_NAME]?tab=web\"\n",
    );
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE UserAccounts (PrincipalID CHAR(36) PRIMARY KEY, ScopeID CHAR(36), FirstName VARCHAR(64), LastName VARCHAR(64), Email VARCHAR(64), Created INT, UserLevel INT DEFAULT 0, UserFlags INT DEFAULT 0, UserTitle VARCHAR(64) DEFAULT \'\', active INT DEFAULT 1)');
    $pdo->exec("CREATE TABLE GridUser (UserID VARCHAR(255) PRIMARY KEY, HomeRegionID CHAR(36), LastRegionID CHAR(36), Online CHAR(5) DEFAULT 'false', Login CHAR(16) DEFAULT '0', Logout CHAR(16) DEFAULT '0')");
    $pdo->exec('CREATE TABLE userprofile (useruuid CHAR(36) PRIMARY KEY, profileImage CHAR(36), profileAboutText TEXT, profileURL VARCHAR(255), profilePartner CHAR(36), profileWantToText TEXT, profileSkillsText TEXT, profileLanguages TEXT, profileFirstImage CHAR(36), profileFirstText TEXT, profileWantToMask INT DEFAULT 0, profileSkillsMask INT DEFAULT 0)');
    $pdo->exec("INSERT INTO UserAccounts (PrincipalID, FirstName, LastName, Email, Created, UserFlags, active) VALUES
        ('11111111-1111-4111-8111-111111111111', 'Ada', 'Lovelace', 'ada@example.org', 1700000000, 1, 1),
        ('22222222-2222-4222-8222-222222222222', 'Mona', 'Lavender', 'mona@example.org', 1700000001, 0, 1)");
    Connections::using(static fn() => $pdo);

    return Grid::find($machine->environment, 'demo');
}

afterEach(fn() => Connections::using(null));

describe('The search of people', function () {
    test('finds only the avatars who published their profile ("show in search")', function () {
        $data = Blocks::data('search', people_grid(), ['q' => 'la', 'topic' => 'people', 'level' => 'h1']);
        $people = array_column($data['results'] ?? [], 'title');

        expect($people)->toBe(['Ada Lovelace']);
    });

    test('links each one to their profile, at the address the grid gives', function () {
        $data = Blocks::data('search', people_grid(), ['q' => 'la', 'topic' => 'people', 'level' => 'h1']);

        expect($data['results'][0]['url'])->toBe('https://demo.example.org/profile/Ada.Lovelace?tab=web');
    });
});

describe('The links of a search a site shows', function () {
    $site = ['search_url' => 'https://site.example.org/fr/search', 'web_profile_url' => 'https://site.example.org/fr/profile/[AGENT_NAME]'];

    test('are at the addresses the site gives (its language), the form, the topics and the profiles', function () use ($site) {
        $data = Blocks::data('search', people_grid(), ['q' => 'la', 'topic' => 'people', 'level' => 'h1'] + $site);

        expect($data['action'])->toBe('https://site.example.org/fr/search/people/')
            ->and(array_column($data['topics'], 'url'))->toContain('https://site.example.org/fr/search/people/?q=la', 'https://site.example.org/fr/search/places/?q=la')
            ->and($data['results'][0]['url'])->toBe('https://site.example.org/fr/profile/Ada.Lovelace');
    });

    test('are the grid\'s without an address of the site, or with one that is not a page', function () {
        $data = Blocks::data('search', people_grid(), ['q' => 'la', 'topic' => 'people', 'level' => 'h1', 'search_url' => 'javascript:alert(1)', 'web_profile_url' => 'javascript:[AGENT_NAME]']);

        expect($data['action'])->toBe('/search/people/')
            ->and($data['results'][0]['url'])->toBe('https://demo.example.org/profile/Ada.Lovelace?tab=web');
    });
});

describe('A place found', function () {
    test('is reached by the link of its grid, its region and its landing point, for where it is shown', function () {
        $search = new W4OS\Helpers\Block\SearchData(people_grid());

        // The landing point as the search keeps it (x/y/z), or as a script writes it (<x, y, z>); a height of zero lands at 21 m
        expect($search->teleport('http://grid.example.org:8002', 'My Region', '<10.4, 20, 30.6>'))
            ->toBe((new W4OS\Engine\Hypergrid\Place('grid.example.org:8002/My Region/10.4/20/30.6'))->tpLink(W4OS\Engine\Hypergrid\Destination::HOP))
            ->and($search->teleport('http://grid.example.org:8002', 'My Region', '128/128/0'))
            ->toBe((new W4OS\Engine\Hypergrid\Place('grid.example.org:8002/My Region/128/128/21'))->tpLink(W4OS\Engine\Hypergrid\Destination::HOP))
            // In a viewer, the form of the viewer: secondlife://host:port/secondlife/Region
            ->and($search->teleport('http://grid.example.org:8002', 'My Region', '128/128/25', ['agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm)']))
            ->toBe('secondlife://grid.example.org:8002/secondlife/My%20Region/128/128/25')
            ->and($search->teleport('http://grid.example.org:8002', ''))
            ->toBeNull();
    });
});

describe('The events found', function () {
    test('are shown from a table of an older version, without a landing point, as from one of now', function () {
        $grid = people_grid();
        $pdo = Connections::pdo($grid->config(), W4OS\Engine\Data\Database::Search);
        $pdo->exec('CREATE TABLE events (owneruuid CHAR(36), name VARCHAR(255), eventid INTEGER PRIMARY KEY, creatoruuid CHAR(36), category INT, description TEXT, dateUTC INT, duration INT, covercharge INT, coveramount INT, simname VARCHAR(255), parcelUUID CHAR(36), globalPos VARCHAR(255), eventflags INT, gatekeeperURL VARCHAR(255))');
        $pdo->exec("INSERT INTO events (name, description, dateUTC, duration, simname, globalPos, eventflags, gatekeeperURL) VALUES ('Jazz night', 'Live jazz', " . (time() + 3600) . ", 60, 'Welcome', '128,128,25', 0, 'http://grid.example.org:8002')");

        $data = Blocks::data('search', $grid, ['q' => 'jazz', 'topic' => 'events', 'level' => 'h1']);
        $item = $data['results'][0] ?? [];

        expect($item['title'] ?? null)->toBe('Jazz night')
            ->and($item['links']['browser'] ?? [])->not->toBe([]);
    });

    test('of another grid are linked to their region, the name of its grid kept apart, exactly as the place gives its links', function () {
        $grid = people_grid();
        $pdo = Connections::pdo($grid->config(), W4OS\Engine\Data\Database::Search);
        $pdo->exec('CREATE TABLE events (owneruuid CHAR(36), name VARCHAR(255), eventid INTEGER PRIMARY KEY, creatoruuid CHAR(36), category INT, description TEXT, dateUTC INT, duration INT, covercharge INT, coveramount INT, simname VARCHAR(255), parcelUUID CHAR(36), globalPos VARCHAR(255), eventflags INT, gatekeeperURL VARCHAR(255), landingpoint VARCHAR(35))');
        $pdo->exec("INSERT INTO events (name, description, dateUTC, duration, simname, globalPos, eventflags, gatekeeperURL, landingpoint) VALUES ('Far night', 'Far away', " . (time() + 3600) . ", 60, 'far.example.org:8002 Far Away', '2583726,2579592,24', 0, 'http://far.example.org:8002', '46/232/24')");

        $item = Blocks::data('search', $grid, ['q' => 'far', 'topic' => 'events', 'level' => 'h1'])['results'][0] ?? [];
        $place = W4OS\Helpers\Block\Links::all($grid->placeAt('Far Away', '46/232/24', 'http://far.example.org:8002'));

        expect($item['links'] ?? null)->toBe($place)
            ->and(array_column($item['links']['browser'], 'format'))->toBe(array_keys($grid->placeAt('Far Away', '46/232/24', 'http://far.example.org:8002')->tpLinks('browser')))
            // Shown as kept: its grid, then its region
            ->and($item['detail'])->toStartWith('far.example.org:8002 Far Away · ')
            ->and(W4OS\Helpers\Block\SearchData::regionName('far.example.org:8002:Far Away', 'far.example.org:8002'))->toBe('Far Away')
            ->and(W4OS\Helpers\Block\SearchData::regionName('Welcome', 'http://grid.example.org:8002'))->toBe('Welcome')
            ->and(W4OS\Helpers\Block\SearchData::regionName('far.example.org:80020 X', 'http://far.example.org:8002'))->toBe('far.example.org:80020 X');
    });
});

describe('The kinds of search', function () {
    /** The search of a grid with two places (one rated adult), a land for sale, an event to come and a classified. */
    function kinds_grid(): Grid
    {
        $grid = people_grid();
        $pdo = Connections::pdo($grid->config(), W4OS\Engine\Data\Database::Search);
        $pdo->exec('CREATE TABLE regionsregister (regionUUID CHAR(36), regionname VARCHAR(255))');
        $pdo->exec("INSERT INTO regionsregister VALUES ('r1', 'Welcome')");
        $pdo->exec('CREATE TABLE parcels (parcelUUID CHAR(36), regionUUID CHAR(36), parcelname VARCHAR(255), description TEXT, landingpoint VARCHAR(255), searchcategory INT, mature VARCHAR(10), dwell FLOAT, imageUUID CHAR(36), gatekeeperURL VARCHAR(255))');
        $pdo->exec("INSERT INTO parcels VALUES ('p1', 'r1', 'Beach house', 'Sand', '10/20/30', 7, 'PG', 12, NULL, 'http://grid.example.org:8002'), ('p2', 'r1', 'Beach club', 'Night', '1/2/3', 7, 'Adult', 50, NULL, 'http://grid.example.org:8002')");
        $pdo->exec('CREATE TABLE allparcels (regionUUID CHAR(36), parcelname VARCHAR(255), ownerUUID CHAR(36), groupUUID CHAR(36), landingpoint VARCHAR(255), parcelUUID CHAR(36), infoUUID CHAR(36), parcelarea INT, gatekeeperURL VARCHAR(255))');
        $pdo->exec("INSERT INTO allparcels (regionUUID, parcelname, parcelUUID, parcelarea) VALUES ('r1', 'Beach house', 'p1', 1024)");
        $pdo->exec('CREATE TABLE parcelsales (parcelUUID CHAR(36), regionUUID CHAR(36), parcelname VARCHAR(255), area INT, saleprice INT, landingpoint VARCHAR(255), parentestate INT, mature VARCHAR(10), gatekeeperURL VARCHAR(255))');
        $pdo->exec("INSERT INTO parcelsales VALUES ('s1', 'r1', 'Lot 1', 512, 1000, '5/5/25', 2, 'PG', 'http://grid.example.org:8002')");
        $pdo->exec('CREATE TABLE events (owneruuid CHAR(36), name VARCHAR(255), eventid INTEGER PRIMARY KEY, creatoruuid CHAR(36), category INT, description TEXT, dateUTC INT, duration INT, covercharge INT, coveramount INT, simname VARCHAR(255), parcelUUID CHAR(36), globalPos VARCHAR(255), eventflags INT, gatekeeperURL VARCHAR(255), landingpoint VARCHAR(35))');
        $pdo->exec("INSERT INTO events (name, description, dateUTC, duration, category, simname, globalPos, eventflags, gatekeeperURL) VALUES ('Jazz night', 'Live', " . (time() + 3600) . ", 60, 20, 'Welcome', '128,128,25', 0, 'http://grid.example.org:8002')");
        $pdo->exec('CREATE TABLE classifieds (classifieduuid CHAR(36), name VARCHAR(255), description TEXT, category INT, classifiedflags INT, expirationdate INT, creationdate INT, priceforlisting INT, parcelname VARCHAR(255), simname VARCHAR(255), snapshotuuid CHAR(36))');
        $pdo->exec("INSERT INTO classifieds VALUES ('c1', 'Shoes', 'New', 1, 4, " . (time() + 86400) . ", 1, 10, 'Shop', 'Welcome', NULL)");

        return $grid;
    }

    test('are always offered as tabs, each with its own form: words required for places, none for land', function () {
        $grid = kinds_grid();
        $places = Blocks::data('search', $grid, ['topic' => 'places', 'level' => 'h1']);
        $land = Blocks::data('search', $grid, ['topic' => 'land', 'level' => 'h1']);
        $short = Blocks::data('search', $grid, ['topic' => 'places', 'q' => 'be', 'level' => 'h1']);

        expect(array_column($places['topics'], 'topic'))->toBe(['people', 'groups', 'places', 'land', 'events', 'classifieds'])
            ->and(array_column($places['fields'], 'name'))->toBe(['q', 'category', 'rating'])
            ->and($places['asked'])->toBeFalse()
            ->and($short['asked'])->toBeFalse()
            ->and($short['note'])->toBe('At least 3 letters.')
            ->and(array_column($land['fields'], 'name'))->toBe(['type', 'price', 'area', 'rating'])
            ->and(array_column($land['results'], 'title'))->toBe(['Lot 1']);
    });

    test('take the ratings asked, general and moderate before the form is sent, as the viewers', function () {
        $grid = kinds_grid();
        $usual = Blocks::data('search', $grid, ['topic' => 'places', 'q' => 'beach', 'level' => 'h1']);
        $adult = Blocks::data('search', $grid, ['topic' => 'places', 'q' => 'beach', 'rating' => ['adult'], 'go' => 1, 'level' => 'h1']);

        expect(array_column($usual['results'], 'title'))->toBe(['Beach house'])
            ->and(array_column($adult['results'], 'title'))->toBe(['Beach club'])
            ->and(array_column(array_filter(end($usual['fields'])['options'], static fn($o) => $o['selected']), 'value'))->toBe(['general', 'moderate']);
    });

    test('list the events to come without words, by category, and the classifieds without words', function () {
        $grid = kinds_grid();
        $events = Blocks::data('search', $grid, ['topic' => 'events', 'level' => 'h1']);
        $sports = Blocks::data('search', $grid, ['topic' => 'events', 'category' => 19, 'go' => 1, 'rating' => ['general'], 'level' => 'h1']);
        $classifieds = Blocks::data('search', $grid, ['topic' => 'classifieds', 'level' => 'h1']);

        expect(array_column($events['results'], 'title'))->toBe(['Jazz night'])
            ->and($events['results'][0]['category'])->toBe('Live Music')
            ->and($sports['results'])->toBe([])
            ->and(array_column($classifieds['results'], 'category'))->toBe(['Shopping']);
    });
});

/** The grid of people_grid() with groups: one shown in the search with two members, one of them in a role that hides its members. */
function groups_grid(): Grid
{
    $grid = people_grid();
    $pdo = Connections::pdo($grid->config(), W4OS\Engine\Data\Database::Robust);
    $pdo->exec('CREATE TABLE os_groups_groups (GroupID CHAR(36), Location VARCHAR(255), Name VARCHAR(255), Charter TEXT, InsigniaID CHAR(36), FounderID CHAR(36), MembershipFee INT, OpenEnrollment VARCHAR(255), ShowInList INT, AllowPublish INT, MaturePublish INT, OwnerRoleID CHAR(36))');
    $pdo->exec('CREATE TABLE os_groups_membership (GroupID CHAR(36), PrincipalID VARCHAR(255), SelectedRoleID CHAR(36), Contribution INT, ListInProfile INT, AcceptNotices INT, AccessToken CHAR(36))');
    $pdo->exec('CREATE TABLE os_groups_roles (GroupID CHAR(36), RoleID CHAR(36), Name VARCHAR(255), Description VARCHAR(255), Title VARCHAR(255), Powers BIGINT)');
    $pdo->exec("INSERT INTO os_groups_groups VALUES ('g1', '', 'Lovers of lace', 'We knit', '', '11111111-1111-4111-8111-111111111111', 5, '1', 1, 1, 0, 'r1'), ('g2', '', 'Hidden lace', '', '', '', 0, '', 0, 0, 0, '')");
    $pdo->exec("INSERT INTO os_groups_roles VALUES ('g1', 'r1', 'Owners', '', 'Chief', " . W4OS\Engine\Models\Group::MEMBER_VISIBLE . "), ('g1', 'r2', 'Shy', '', 'Shy', 0)");
    $pdo->exec("INSERT INTO os_groups_membership VALUES ('g1', '11111111-1111-4111-8111-111111111111', 'r1', 0, 1, 1, ''), ('g1', '22222222-2222-4222-8222-222222222222', 'r2', 0, 1, 1, ''), ('g2', '11111111-1111-4111-8111-111111111111', 'r1', 0, 1, 1, '')");

    return $grid;
}

describe('The search of groups', function () {
    test('finds the groups shown in the search, with their members, as the viewers', function () {
        $data = Blocks::data('search', groups_grid(), ['q' => 'lace', 'topic' => 'groups', 'level' => 'h1', 'id' => 'g1']);
        $group = $data['results'][0];

        expect(array_column($data['results'], 'title'))->toBe(['Lovers of lace'])
            ->and(array_column($group['columns'], 'value'))->toBe(['2'])
            ->and(array_column($group['details'], 'value'))->toBe(['g1', 'Ada Lovelace', 'Yes', '5', 'General Content'])
            ->and($group['text'])->toBe('We knit')
            ->and(array_column($group['members']['rows'], 'name'))->toBe(['Ada Lovelace'])
            ->and(array_column($data['sorts'], 'key'))->toBe(['name', 'members'])
            ->and(array_column($data['head'], 'label'))->toBe(['Name', 'Members'])
            ->and($data['selected']['id'])->toBe('g1');
    });

    test('lists the results with their columns, the one chosen (?id=) showing its detail beside them, its search kept', function () {
        $grid = groups_grid();
        $list = Blocks::data('search', $grid, ['q' => 'lace', 'topic' => 'groups', 'level' => 'h1', 'sort' => 'members', 'dir' => 'desc']);
        $html = (new W4OS\Helpers\View\View(dirname(__DIR__, 2) . '/templates'))->block('search', $grid, ['q' => 'lace', 'topic' => 'groups', 'level' => 'h1', 'id' => 'g1', 'lang' => 'en']);

        expect($list['selected'])->toBeNull()
            ->and($list['results'][0]['select'])->toBe('?q=lace&rating%5B0%5D=general&rating%5B1%5D=moderate&go=1&sort=members&dir=desc&id=g1')
            ->and($list['results'][0]['members']['rows'])->toBe([])
            ->and($html)->toContain('<table class="search-results">', 'aria-controls="search-detail" aria-current="true">Lovers of lace</a>', '<section id="search-detail"', 'We knit')
            ->and($html)->not->toContain('<details');
    });

    test('says nothing was found only of a search the visitor asked', function () {
        $grid = groups_grid();
        $opened = Blocks::data('search', $grid, ['topic' => 'classifieds', 'level' => 'h1']);
        $asked = Blocks::data('search', $grid, ['topic' => 'classifieds', 'level' => 'h1', 'go' => 1]);

        expect($opened['results'])->toBe([])->and($opened['sent'])->toBeFalse()
            ->and($asked['sent'])->toBeTrue()->and($asked['nothing'])->toBe('No results');
    });

    test('asks words, as the search of people', function () {
        $data = Blocks::data('search', groups_grid(), ['topic' => 'groups', 'level' => 'h1']);

        expect($data['asked'])->toBeFalse()->and($data['note'])->toBe('')->and(array_column($data['fields'], 'name'))->toBe(['q']);
    });

    test('shows a person as the viewers: key, birth, account, partner, groups, a link to the profile', function () {
        $data = Blocks::data('search', groups_grid(), ['q' => 'ada', 'topic' => 'people', 'level' => 'h1']);
        $person = $data['results'][0];

        expect(array_column($person['details'], 'label'))->toBe(['Key', 'Birthdate', 'Account', 'Partner', 'Groups'])
            ->and($person['details'][0]['value'])->toBe('11111111-1111-4111-8111-111111111111')
            ->and($person['details'][4]['value'])->toBe('Hidden lace, Lovers of lace')
            ->and($person['actions'][0]['url'])->toBe($person['url']);
    });
})->skip(fn() => !extension_loaded('pdo_sqlite'), 'pdo_sqlite is missing');
