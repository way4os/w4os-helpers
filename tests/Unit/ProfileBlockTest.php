<?php

use OpenSim\Data\Connections;
use OpenSim\Data\OSPDO;
use OpenSim\Helpers\View\View;
use OpenSim\Models\Grid;
use OpenSim\Testing\Machine;

/** A grid with two published profiles: one with several sections of the viewer's profile, one with its about text alone. */
function profile_grid(): Grid
{
    $machine = new Machine();
    $robust = (string) file_get_contents($machine->grid . '/Robust.HG.ini');
    $machine->write(
        $machine->grid . '/Robust.HG.ini',
        $robust . "\n[DatabaseService]\n    ConnectionString = \"Data Source=localhost;Database=robust;User ID=os;Password=pw;\"\n",
    );
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE UserAccounts (PrincipalID CHAR(36) PRIMARY KEY, ScopeID CHAR(36), FirstName VARCHAR(64), LastName VARCHAR(64), Email VARCHAR(64), Created INT, UserLevel INT DEFAULT 0, UserFlags INT DEFAULT 0, UserTitle VARCHAR(64) DEFAULT \'\', active INT DEFAULT 1)');
    $pdo->exec("CREATE TABLE GridUser (UserID VARCHAR(255) PRIMARY KEY, HomeRegionID CHAR(36), LastRegionID CHAR(36), Online CHAR(5) DEFAULT 'false', Login CHAR(16) DEFAULT '0', Logout CHAR(16) DEFAULT '0')");
    $pdo->exec('CREATE TABLE userprofile (useruuid CHAR(36) PRIMARY KEY, profileImage CHAR(36), profileAboutText TEXT, profileURL VARCHAR(255), profilePartner CHAR(36), profileWantToText TEXT, profileSkillsText TEXT, profileLanguages TEXT, profileFirstImage CHAR(36), profileFirstText TEXT, profileWantToMask INT DEFAULT 0, profileSkillsMask INT DEFAULT 0)');
    $pdo->exec('CREATE TABLE userpicks (pickuuid CHAR(36), creatoruuid CHAR(36), name TEXT, description TEXT, snapshotuuid CHAR(36), simname TEXT, posglobal TEXT, gatekeeper TEXT, enabled TEXT, sortorder INT)');
    $pdo->exec("INSERT INTO userpicks VALUES ('99999999-9999-4999-8999-999999999999', '11111111-1111-4111-8111-111111111111', 'Welcome', '', '00000000-0000-0000-0000-000000000000', 'Welcome', '<256128.5, 256128.2, 26>', '', 'true', 0)");
    $pdo->exec('CREATE TABLE classifieds (creatoruuid CHAR(36), name TEXT, description TEXT, simname TEXT, parcelname TEXT, creationdate INT, expirationdate INT)');
    $pdo->exec("INSERT INTO UserAccounts (PrincipalID, FirstName, LastName, Email, Created, UserFlags) VALUES
        ('11111111-1111-4111-8111-111111111111', 'Ada', 'Love', 'ada@example.org', 1700000000, 1),
        ('22222222-2222-4222-8222-222222222222', 'Bob', 'Plain', 'bob@example.org', 1700000000, 1)");
    $pdo->exec("INSERT INTO userprofile (useruuid, profileAboutText, profileWantToText, profileWantToMask, profileLanguages, profileFirstText) VALUES
        ('11111111-1111-4111-8111-111111111111', 'Hello', 'Gardens', 5, 'Français, English', 'A painter'),
        ('22222222-2222-4222-8222-222222222222', 'Just me', '', 0, '', '')");
    Connections::using(static fn() => $pdo);

    return Grid::find($machine->environment, 'demo');
}

afterEach(fn() => Connections::using(null));

describe('The block of a profile', function () {
    test('shows every section of the viewer, in its order, as tabs; an empty one says so', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);
        preg_match_all('/<a href="#profile-([a-z-]+)">([^<]+)</', $html, $tabs);

        expect($tabs[1])->toBe(['second-life', 'web', 'interests', 'picks', 'classifieds', 'first-life'])
            ->and($tabs[2])->toBe(['2nd Life', 'Web', 'Interests', 'Picks', 'Classifieds', '1st Life'])
            ->and($html)->toContain('Build, Meet<br>Gardens', 'Français, English', 'A painter', 'role\', \'tablist\'', '<p class="profile-none">None.</p>');
    });

    test('shows the picks as the viewer: the list, then each one with its picture, description, place and teleport link', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);

        expect($html)->toContain(
            '<li><a href="#profile-pick-1">Welcome</a></li>',
            '<article id="profile-pick-1" class="profile-item">',
            '201ce950-aa38-46d8-a8f1-4396e9d6be00.jpg',
            'No description given.',
            'Welcome (128, 128, 26)',
            'class="profile-item-teleport" href="hop://',
        );
    });

    test('names its tabs as the catalog of the engine does, in the language of the page', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'fr']);
        preg_match_all('/<a href="#profile-([a-z-]+)">([^<]+)</', $html, $tabs);
        OpenSim\Helpers\I18n::use('en');

        expect($tabs[2])->toBe(['Vie SL', 'Web', 'Centres d’intérêt', 'Favoris', 'Petites annonces', 'Vie RL'])
            ->and($html)->toContain('aria-label="Sections du profil"');
    })->skip(fn() => setlocale(LC_MESSAGES, ['fr_FR.UTF-8', 'fr_FR.utf8']) === false, 'the system has no French locale');

    test('a profile with little has every tab all the same', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Bob.Plain', 'lang' => 'en']);

        expect($html)->toContain('id="profile-second-life"', 'Just me', 'profile-tabs', 'id="profile-picks"')->not->toContain('class="profile-items');
    });
});
