<?php

use OpenSim\Data\Connections;
use OpenSim\Data\OSPDO;
use OpenSim\Helpers\Http\Action\Engine;
use OpenSim\Models\Grid;
use OpenSim\Testing\Machine;

/** A grid of a test machine whose databases, the search's among them, are one: what its search tasks need to be added. */
function search_tasks_grid(): Grid
{
    $machine = new Machine();
    $machine->write(
        $machine->grid . '/Robust.HG.ini',
        (string) file_get_contents($machine->grid . '/Robust.HG.ini') .
            "\n[DatabaseService]\n    ConnectionString = \"Data Source=localhost;Database=robust;User ID=os;Password=pw;\"\n",
    );
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    // The registered simulators: none is due
    $pdo->exec('CREATE TABLE hostsregister (host VARCHAR(255), port INT, register INT, nextcheck INT, checked INT, failcounter INT, gatekeeperURL VARCHAR(255), PRIMARY KEY (host, port))');
    Connections::using(static fn() => $pdo);

    return Grid::find($machine->environment, 'demo');
}

/** @return array<string,?int> the last run of each task of the scheduler of a grid */
function search_tasks_runs(Grid $grid): array
{
    return array_column(Engine::scheduler($grid)->status(), 'lastRun', 'name');
}

afterEach(fn() => Connections::using(null));

describe('What waits for the answer', function () {
    test('the API of a request has the search tasks of its grid, which run once the answer is sent', function () {
        $grid = search_tasks_grid();
        $before = search_tasks_runs($grid);

        Engine::api($grid);
        Engine::finish();

        expect($before)
            ->toBe(['search-parse' => null, 'events-parse' => null, 'guide-pictures' => null])
            ->and(search_tasks_runs($grid)['search-parse'])
            ->not->toBeNull();
    });

    test('a visit that asked no API runs the tasks that are due all the same: the pseudo cron of the grid served', function () {
        $grid = search_tasks_grid();

        Engine::serving($grid);
        Engine::finish();

        expect(search_tasks_runs($grid)['search-parse'])->not->toBeNull();
    });
});
