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
        $people = array_column($data['groups'][0]['items'] ?? [], 'title');

        expect($people)->toBe(['Ada Lovelace']);
    });

    test('links each one to their profile, at the address the grid gives', function () {
        $data = Blocks::data('search', people_grid(), ['q' => 'la', 'topic' => 'people', 'level' => 'h1']);

        expect($data['groups'][0]['items'][0]['url'])->toBe('https://demo.example.org/profile/Ada.Lovelace?tab=web');
    });
});

describe('The links of a search a site shows', function () {
    $site = ['search_url' => 'https://site.example.org/fr/search', 'web_profile_url' => 'https://site.example.org/fr/profile/[AGENT_NAME]'];

    test('are at the addresses the site gives (its language), the form, the topics and the profiles', function () use ($site) {
        $data = Blocks::data('search', people_grid(), ['q' => 'la', 'topic' => 'people', 'level' => 'h1'] + $site);

        expect($data['action'])->toBe('https://site.example.org/fr/search')
            ->and($data['all_url'])->toBe('https://site.example.org/fr/search?q=la')
            ->and(array_column($data['topics'], 'url'))->toContain('https://site.example.org/fr/search/people/?q=la')
            ->and($data['groups'][0]['url'])->toBe('https://site.example.org/fr/search/people/?q=la')
            ->and($data['groups'][0]['items'][0]['url'])->toBe('https://site.example.org/fr/profile/Ada.Lovelace');
    });

    test('are the grid\'s without an address of the site, or with one that is not a page', function () {
        $data = Blocks::data('search', people_grid(), ['q' => 'la', 'topic' => 'people', 'level' => 'h1', 'search_url' => 'javascript:alert(1)', 'web_profile_url' => 'javascript:[AGENT_NAME]']);

        expect($data['action'])->toBe('/search')
            ->and($data['groups'][0]['items'][0]['url'])->toBe('https://demo.example.org/profile/Ada.Lovelace?tab=web');
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
