<?php

use W4OS\Engine\Data\Connections;
use W4OS\Engine\Data\OSPDO;
use W4OS\Helpers\App;
use W4OS\Helpers\Block\Blocks;
use W4OS\Helpers\View\View;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Testing\Machine;
use Slim\Psr7\Factory\ServerRequestFactory;

/** A grid whose accounts are in a database (its Robust's): reached through the connections the test gives. */
function database_grid(): Grid
{
    $machine = new Machine();
    $robust = (string) file_get_contents($machine->grid . '/Robust.HG.ini');
    $machine->write(
        $machine->grid . '/Robust.HG.ini',
        $robust . "\n[DatabaseService]\n    ConnectionString = \"Data Source=db.example.org;Database=robust;User ID=os;Password=pw;\"\n",
    );

    return Grid::find($machine->environment, 'demo');
}

afterEach(fn() => Connections::using(null));

describe('A block whose database cannot be reached', function () {
    test('says the profile is unavailable for now, never that it does not exist, and tells why', function () {
        $grid = database_grid();
        Connections::using(static fn() => null);
        View::notices();
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $html = $view->block('profile', $grid, ['name' => 'Ada.Love']);

        expect($view->unavailable())->toBeTrue()
            ->and($html)->toContain('temporarily unavailable')
            ->and($html)->not->toContain('This profile does not exist')
            ->and(array_column(View::notices(), 'level'))->toContain('error');
    });

    test('an unknown profile still does not exist', function () {
        $grid = database_grid();
        $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
        $pdo->exec('CREATE TABLE UserAccounts (PrincipalID CHAR(36) PRIMARY KEY, ScopeID CHAR(36), FirstName VARCHAR(64), LastName VARCHAR(64), Email VARCHAR(64), Created INT, UserLevel INT DEFAULT 0, UserFlags INT DEFAULT 0, UserTitle VARCHAR(64) DEFAULT \'\', active INT DEFAULT 1)');
        $pdo->exec("CREATE TABLE GridUser (UserID VARCHAR(255) PRIMARY KEY, HomeRegionID CHAR(36), LastRegionID CHAR(36), Online CHAR(5) DEFAULT 'false', Login CHAR(16) DEFAULT '0', Logout CHAR(16) DEFAULT '0')");
        $pdo->exec('CREATE TABLE userprofile (useruuid CHAR(36) PRIMARY KEY, profileImage CHAR(36), profileAboutText TEXT, profileURL VARCHAR(255), profilePartner CHAR(36), profileWantToText TEXT, profileSkillsText TEXT, profileLanguages TEXT, profileFirstImage CHAR(36), profileFirstText TEXT, profileWantToMask INT DEFAULT 0, profileSkillsMask INT DEFAULT 0)');
        Connections::using(static fn() => $pdo);
        View::notices();
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $html = $view->block('profile', $grid, ['name' => 'Nobody.Here']);

        expect($view->unavailable())->toBeFalse()
            ->and($view->found())->toBeFalse()
            ->and($html)->toContain('This profile does not exist')
            ->and(View::notices())->toBe([]);
    });

    test('says the search of people and the new users are unavailable for now, not empty, and tells why', function () {
        $grid = database_grid();
        Connections::using(static fn() => null);
        View::notices();

        expect(Blocks::data('search', $grid, ['q' => 'ada', 'topic' => 'people'])['unavailable'] ?? false)->toBeTrue()
            ->and(Blocks::data('new_users', $grid)['unavailable'] ?? false)->toBeTrue()
            ->and(count(View::notices()))->toBeGreaterThanOrEqual(2);
    });

    test('says the popular places and the guide of the search are unavailable for now, not an error, and tells why', function () {
        $grid = database_grid();
        Connections::using(static fn() => null);
        View::notices();
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $places = $view->block('popular_places', $grid);
        $placesUnavailable = $view->unavailable();
        $guide = $view->block('destination_guide', $grid);

        expect($places)->toContain('temporarily unavailable')
            ->and($placesUnavailable)->toBeTrue()
            ->and($guide)->toContain('temporarily unavailable')
            ->and($view->unavailable())->toBeTrue()
            ->and(count(View::notices()))->toBeGreaterThanOrEqual(2);
    });

    test('says the directory and the search of places are unavailable for now, not empty, and tells why', function () {
        $grid = database_grid();
        Connections::using(static fn() => null);
        View::notices();
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $directory = $view->block('directory_info', $grid);

        expect($view->unavailable())->toBeTrue()
            ->and($directory)->toContain('temporarily unavailable')
            ->and($directory)->not->toContain('0 grids')
            ->and(Blocks::data('search', $grid, ['q' => 'ada', 'topic' => 'places'])['unavailable'] ?? false)->toBeTrue()
            ->and(count(View::notices()))->toBeGreaterThanOrEqual(2);
    });

    test('searches the people when only the search cannot be reached', function () {
        $grid = database_grid();
        $accounts = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
        $accounts->exec('CREATE TABLE UserAccounts (PrincipalID CHAR(36) PRIMARY KEY, ScopeID CHAR(36), FirstName VARCHAR(64), LastName VARCHAR(64), Email VARCHAR(64), Created INT, UserLevel INT DEFAULT 0, UserFlags INT DEFAULT 0, UserTitle VARCHAR(64) DEFAULT \'\', active INT DEFAULT 1)');
        $accounts->exec("CREATE TABLE GridUser (UserID VARCHAR(255) PRIMARY KEY, HomeRegionID CHAR(36), LastRegionID CHAR(36), Online CHAR(5) DEFAULT 'false', Login CHAR(16) DEFAULT '0', Logout CHAR(16) DEFAULT '0')");
        $accounts->exec('CREATE TABLE userprofile (useruuid CHAR(36) PRIMARY KEY, profileImage CHAR(36), profileAboutText TEXT, profileURL VARCHAR(255), profilePartner CHAR(36), profileWantToText TEXT, profileSkillsText TEXT, profileLanguages TEXT, profileFirstImage CHAR(36), profileFirstText TEXT, profileWantToMask INT DEFAULT 0, profileSkillsMask INT DEFAULT 0)');
        Connections::using(static fn($what) => $what === \W4OS\Engine\Data\Database::Search ? null : $accounts);
        View::notices();

        expect(Blocks::data('search', $grid, ['q' => 'ada', 'topic' => 'people'])['unavailable'])->toBeFalse()
            ->and(View::notices())->toBe([]);
    });

    test('says a search it cannot read is unavailable for now, not empty', function () {
        $grid = database_grid();
        $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
        Connections::using(static fn() => $pdo);
        View::notices();

        expect(Blocks::data('directory_info', $grid)['unavailable'] ?? false)->toBeTrue()
            ->and(array_column(View::notices(), 'level'))->toContain('error');
    });

    test('a grid without a search has an empty directory, not an unavailable one', function () {
        $grid = Grid::find((new Machine())->environment, 'demo');
        View::notices();

        expect(Blocks::data('directory_info', $grid)['unavailable'] ?? false)->toBeFalse()
            ->and(View::notices())->toBe([]);
    });

    test('is answered with an error the site can tell from a page that does not exist', function () {
        $grid = database_grid();
        Connections::using(static fn() => null);
        $app = App::create(static fn(string $nick) => $grid);
        $get = fn(string $path) => $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path))->getStatusCode();

        expect($get('/blocks/profile?name=Ada.Love'))->toBe(503)
            ->and($get('/blocks/profile?name=Ada.Love&format=json'))->toBe(503);
    });
});
