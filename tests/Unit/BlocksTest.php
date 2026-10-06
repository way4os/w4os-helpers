<?php
/**
 * The blocks of the pages (classes/class-blocks.php): what they give from what the grid says and the database tells, with a
 * database that answers what the test says.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';

defined('OPENSIM_GRID_NAME') || define('OPENSIM_GRID_NAME', 'Alpha <b>World');
defined('OPENSIM_LOGIN_URI') || define('OPENSIM_LOGIN_URI', 'http://127.0.0.1:9');
defined('OPENSIM_MOTD') || define('OPENSIM_MOTD', 'Hello <USERNAME>\\nSecond line');

/** A database that answers a number for each query, by what the query is about. */
function counting_db(array $answers): object
{
    return new class($answers) {
        public $connected = true;
        public array $asked = [];

        public function __construct(private array $answers) {}

        public function prepareAndExecute($sql, $params = [])
        {
            $this->asked[] = [$sql, $params];
            foreach ($this->answers as $about => $value) {
                if (stripos($sql, $about) !== false) {
                    return new class($value) {
                        public function __construct(private $value) {}

                        public function fetchColumn()
                        {
                            return is_array($this->value) ? ($this->value[0] ?? false) : $this->value;
                        }

                        public function fetchAll($mode = null)
                        {
                            return (array) $this->value;
                        }
                    };
                }
            }

            return false;
        }
    };
}

describe('Block grid-info', function () {
    test('gives the name of the grid, and the address to log in with as the link a viewer follows', function () {
        $data = OpenSim_Helpers_Blocks::data('grid-info');

        expect($data['rows'][0])
            ->toMatchArray(['label' => 'Grid Name', 'value' => 'Alpha <b>World'])
            ->and($data['rows'][1])
            ->toMatchArray(['label' => 'Login URI', 'value' => '127.0.0.1:9', 'url' => 'hop://127.0.0.1:9']);
    });

    test('has the title of the call, or none when it is null, and a level that is a heading', function () {
        expect(OpenSim_Helpers_Blocks::data('grid-info')['title'])
            ->toBe('Grid Information')
            ->and(OpenSim_Helpers_Blocks::data('grid-info', ['title' => 'Mine'])['title'])
            ->toBe('Mine')
            ->and(OpenSim_Helpers_Blocks::data('grid-info', ['title' => null])['title'])
            ->toBeNull()
            ->and(OpenSim_Helpers_Blocks::data('grid-info', ['level' => 'h2'])['level'])
            ->toBe('h2')
            ->and(OpenSim_Helpers_Blocks::data('grid-info', ['level' => 'script'])['level'])
            ->toBe('h3');
    });

    test('is shown as a fragment, with the markup of the grid name as text', function () {
        $html = OpenSim_Helpers_Blocks::render('grid-info', ['title' => 'Info <i>']);

        expect($html)
            ->toContain('<section class="block block-grid-info">')
            ->toContain('<h3 class="block-title">Info &lt;i&gt;</h3>')
            ->toContain('Alpha &lt;b&gt;World')
            ->toContain('href="hop://127.0.0.1:9"');
    });
});

describe('Block grid-status', function () {
    $counts = [
        'members' => 1234,
        'active_members' => 56,
        'members_in_world' => 7,
        'active_users' => 89,
        'total_users' => 10,
        'regions' => 42,
        'area' => 2.75,
    ];

    test('shows the status and the numbers, formatted', function () use ($counts) {
        $rows = OpenSim_Helpers_Block_GridStatus::rows(['online' => true, 'counts' => $counts]);
        $by = array_column($rows, 'value', 'label');

        expect($by['Status'])
            ->toBe('Online')
            ->and($by['Members'])
            ->toBe('1,234')
            ->and($by['Regions'])
            ->toBe('42')
            ->and($by['Total area'])
            ->toBe("2.75\u{a0}km²")
            ->and(count($rows))
            ->toBe(8);
    });

    test('leaves out the visitors of other grids when asked', function () use ($counts) {
        $labels = array_column(OpenSim_Helpers_Block_GridStatus::rows(['online' => true, 'counts' => $counts], false), 'label');

        expect($labels)
            ->not->toContain('Active users (30 days)')
            ->not->toContain('Total users in world')
            ->toContain('Members in world');
    });

    test('says Offline, and has only the status without a database', function () {
        expect(OpenSim_Helpers_Block_GridStatus::rows(['online' => false, 'counts' => null]))->toBe([
            ['label' => 'Status', 'value' => 'Offline'],
        ])
            ->and(OpenSim_Helpers_Block_GridStatus::rows(['online' => null, 'counts' => null]))
            ->toBe([]);
    });

    test('counts in the database of Robust, the 30 days from now', function () {
        $db = counting_db([
            'FROM UserAccounts WHERE' => 12,
            'GridUser g' => 5,
            'Presence p' => 3,
            'FROM GridUser WHERE' => 8,
            'FROM Presence WHERE' => 4,
            'ROUND(SUM' => '1.5',
            'FROM regions' => 9,
        ]);
        $counts = OpenSim_Helpers_Block_GridStatus::counts(fn($service) => $db, 1_800_000_000);

        expect($counts)
            ->toMatchArray([
                'members' => 12,
                'active_members' => 5,
                'members_in_world' => 3,
                'active_users' => 8,
                'total_users' => 4,
                'regions' => 9,
                'area' => '1.5',
            ])
            ->and($db->asked[1][1])
            ->toBe(['month' => 1_800_000_000 - 30 * 86400]);
    });

    test('asks each service its own database, and joins nothing across two', function () {
        $answers = ['FROM UserAccounts WHERE active' => 100, 'PrincipalID IN' => 4, 'FROM GridUser WHERE' => 3, 'FROM Presence WHERE' => 2, 'FROM regions' => 5];
        $dbs = [];
        foreach (['UserAccountService', 'GridUserService', 'PresenceService', 'GridService'] as $service) {
            $dbs[$service] = counting_db(['SELECT UserID FROM' => ['u1', 'u2', 'u3']] + $answers);
        }
        $counts = OpenSim_Helpers_Block_GridStatus::counts(fn($service) => $dbs[$service], 1_800_000_000);

        // The accounts come from their own database, the logins and the presences from theirs
        expect($counts['members'])
            ->toBe(100)
            ->and($counts['regions'])
            ->toBe(5)
            ->and(count($dbs['UserAccountService']->asked))
            ->toBeGreaterThanOrEqual(1)
            ->and(array_filter(array_column($dbs['UserAccountService']->asked, 0), fn($sql) => str_contains($sql, 'GridUser')))
            ->toBe([])
            ->and(array_filter(array_column($dbs['GridUserService']->asked, 0), fn($sql) => str_contains($sql, 'UserAccounts WHERE active')))
            ->toBe([]);
    });

    test('counts nothing without a database that answers', function () {
        expect(OpenSim_Helpers_Block_GridStatus::counts(fn($service) => null, 0))->toBeNull();

        $down = counting_db([]);
        $down->connected = false;
        expect(OpenSim_Helpers_Block_GridStatus::counts(fn($service) => $down, 0))->toBeNull();
    });

    test('is online when something answers at the login address', function () {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($server, false), ':'), 1);

        expect(OpenSim_Helpers_Block_GridStatus::online("http://127.0.0.1:$port"))->toBeTrue();

        fclose($server);
        expect(OpenSim_Helpers_Block_GridStatus::online("http://127.0.0.1:$port"))
            ->toBeFalse()
            ->and(OpenSim_Helpers_Block_GridStatus::online(''))
            ->toBeNull();
    });
});

describe('Block motd', function () {
    test('gives the message of the grid, with a name for the avatar a page does not know, and the new lines', function () {
        $data = OpenSim_Helpers_Blocks::data('motd');

        expect($data['text'])->toBe("Hello traveler\nSecond line")->and($data['title'])->toBe('Message of the day');
    });

    test('is shown as a paragraph with its new lines', function () {
        expect(OpenSim_Helpers_Blocks::render('motd'))->toContain('Hello traveler<br />')->toContain('Second line');
    });
});

describe('The blocks', function () {
    test('give nothing for a block that does not exist', function () {
        expect(OpenSim_Helpers_Blocks::data('nothing'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Blocks::render('nothing'))
            ->toBe('');
    });
});

describe('The cache', function () {
    test('keeps a value for a while, then makes a new one', function () {
        $dir = sys_get_temp_dir() . '/cache-' . bin2hex(random_bytes(4));
        $made = 0;
        $make = function () use (&$made) {
            return ['n' => ++$made];
        };

        expect(OpenSim_Helpers_Cache::remember('key', 60, $make, $dir))
            ->toBe(['n' => 1])
            ->and(OpenSim_Helpers_Cache::remember('key', 60, $make, $dir))
            ->toBe(['n' => 1])
            ->and(OpenSim_Helpers_Cache::remember('other', 60, $make, $dir))
            ->toBe(['n' => 2]);

        // Out of date
        touch($dir . '/' . md5('key') . '.json', time() - 120);
        expect(OpenSim_Helpers_Cache::remember('key', 60, $make, $dir))->toBe(['n' => 3]);
    });

    test('keeps null and false as values', function () {
        $dir = sys_get_temp_dir() . '/cache-' . bin2hex(random_bytes(4));
        $made = 0;
        $make = function () use (&$made) {
            ++$made;

            return null;
        };
        OpenSim_Helpers_Cache::remember('nothing', 60, $make, $dir);
        OpenSim_Helpers_Cache::remember('nothing', 60, $make, $dir);

        expect($made)->toBe(1);
    });

    test('can keep a value for a time that depends on it', function () {
        $dir = sys_get_temp_dir() . '/cache-' . bin2hex(random_bytes(4));
        $made = 0;
        $make = function () use (&$made) {
            return ['online' => false, 'n' => ++$made];
        };
        $ttl = fn($value) => $value['online'] ? 3600 : 60;

        OpenSim_Helpers_Cache::remember('grid', $ttl, $make, $dir);
        expect(OpenSim_Helpers_Cache::remember('grid', $ttl, $make, $dir)['n'])->toBe(1);

        // What did not answer is asked again after a minute
        touch($dir . '/' . md5('grid') . '.json', time() - 120);
        expect(OpenSim_Helpers_Cache::remember('grid', $ttl, $make, $dir)['n'])->toBe(2);
    });
});
