<?php

use OpenSim\Data\Connections;
use OpenSim\Data\OSPDO;
use OpenSim\Helpers\Block\Blocks;
use OpenSim\Models\Grid;
use OpenSim\Testing\Machine;

/** A grid whose accounts are in one database: people, a model, the account of the services, one without an email, one disabled. */
function new_users_grid(): Grid
{
    $machine = new Machine();
    $robust = (string) file_get_contents($machine->grid . '/Robust.HG.ini');
    $machine->write(
        $machine->grid . '/Robust.HG.ini',
        str_replace("[Const]\n", "[Const]\n    WebURL = \"https://demo.example.org\"\n", $robust) .
            "\n[DatabaseService]\n    ConnectionString = \"Data Source=localhost;Database=robust;User ID=os;Password=pw;\"\n" .
            "[GridInfoService]\n    web_profile_url = \"https://demo.example.org/helpers/profile/[AGENT_NAME]\"\n",
    );
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE UserAccounts (PrincipalID CHAR(36) PRIMARY KEY, ScopeID CHAR(36), FirstName VARCHAR(64), LastName VARCHAR(64), Email VARCHAR(64), Created INT, UserLevel INT DEFAULT 0, UserFlags INT DEFAULT 0, UserTitle VARCHAR(64) DEFAULT \'\', active INT DEFAULT 1)');
    $pdo->exec("CREATE TABLE GridUser (UserID VARCHAR(255) PRIMARY KEY, HomeRegionID CHAR(36), LastRegionID CHAR(36), Online CHAR(5) DEFAULT 'false', Login CHAR(16) DEFAULT '0', Logout CHAR(16) DEFAULT '0')");
    $pdo->exec('CREATE TABLE userprofile (useruuid CHAR(36) PRIMARY KEY, profileImage CHAR(36), profileAboutText TEXT, profileURL VARCHAR(255), profilePartner CHAR(36), profileWantToText TEXT, profileSkillsText TEXT, profileLanguages TEXT, profileFirstImage CHAR(36), profileFirstText TEXT, profileWantToMask INT DEFAULT 0, profileSkillsMask INT DEFAULT 0)');
    $pdo->exec("INSERT INTO UserAccounts (PrincipalID, FirstName, LastName, Email, Created, UserLevel, active) VALUES
        ('11111111-1111-4111-8111-111111111111', 'Ada', 'Love', 'ada@example.org', 1700000000, 0, 1),
        ('22222222-2222-4222-8222-222222222222', 'Bob', 'Root', 'bob@example.org', 1600000000, 200, 1),
        ('33333333-3333-4333-8333-333333333333', 'Default', 'Ruth', '', 1800000000, 0, 1),
        ('44444444-4444-4444-8444-444444444444', 'GRID', 'SERVICES', 'grid@example.org', 1800000001, 0, 1),
        ('55555555-5555-4555-8555-555555555555', 'No', 'Mail', '', 1800000002, 0, 1),
        ('66666666-6666-4666-8666-666666666666', 'Cy', 'Gone', 'cy@example.org', 1800000003, -1, 0),
        ('77777777-7777-4777-8777-777777777777', 'Dee', 'Late', 'dee@example.org', 1750000000, 0, 1)");
    Connections::using(static fn() => $pdo);

    return Grid::find($machine->environment, 'demo');
}

afterEach(fn() => Connections::using(null));

describe('The block of the recent users', function () {
    test('lists the newest people, never a model, the account of the services, an account without an email or a disabled one', function () {
        $data = Blocks::data('new_users', new_users_grid());

        expect($data['title'])
            ->toBe('Recent users')
            ->and(array_column($data['rows'], 'label'))
            ->toBe(['Dee Late', 'Ada Love', 'Bob Root'])
            ->and($data['rows'][0])
            ->toMatchArray(['url' => '/helpers/profile/Dee.Late', 'value' => '2025-06-15'])
            ->and($data['rows'][0])
            ->not->toHaveKey('email');
    });

    test('takes how many it lists', function () {
        $data = Blocks::data('new_users', new_users_grid(), ['count' => 1]);

        expect(array_column($data['rows'], 'label'))->toBe(['Dee Late']);
    });
});
