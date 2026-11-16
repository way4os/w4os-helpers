<?php

use OpenSim\Data\Connections;
use OpenSim\Data\OSPDO;
use OpenSim\Helpers\Block\Blocks;
use OpenSim\Models\Grid;
use OpenSim\Testing\Machine;

/** A grid whose accounts are in one database: Ada published her profile ("show in search"), Mona did not. */
function people_grid(): Grid
{
    $machine = new Machine();
    $machine->write(
        $machine->grid . '/Robust.HG.ini',
        (string) file_get_contents($machine->grid . '/Robust.HG.ini') .
            "\n[DatabaseService]\n    ConnectionString = \"Data Source=localhost;Database=robust;User ID=os;Password=pw;\"\n",
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
});
