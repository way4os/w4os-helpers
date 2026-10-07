<?php
/**
 * The "show in search" box of a profile: who may tell it, and what is written.
 */

require_once dirname(__DIR__, 2) . '/includes/profile-publish.php';

/** A database for the profiles: it has the profiles of its fixtures, and keeps what was written. */
function profiles_db(array $profiles, array &$written): object
{
    return new class($profiles, $written) {
        public function __construct(private array $profiles, private array &$written) {}

        public function prepareAndExecute($sql, $params = [])
        {
            if (str_starts_with($sql, 'UPDATE')) {
                $this->written[] = $params;
            }
            $rows = str_starts_with($sql, 'SELECT') && in_array($params['id'], $this->profiles, true) ? [['useruuid' => $params['id']]] : [];

            return new class($rows) {
                public function __construct(private array $rows) {}

                public function fetchAll()
                {
                    return $this->rows;
                }
            };
        }
    };
}

describe('The simulators that may tell it', function () {
    $resolve = fn($name) => $name === 'sim.example.org' ? ['203.0.113.7'] : false;

    test('are the ones registered to the search, by address or by name, and this machine', function () use ($resolve) {
        $hosts = ['192.168.8.105', 'sim.example.org'];

        expect(helpers_is_simulator('192.168.8.105', $hosts, '', $resolve))
            ->toBeTrue()
            ->and(helpers_is_simulator('203.0.113.7', $hosts, '', $resolve))
            ->toBeTrue()
            ->and(helpers_is_simulator('127.0.0.1', [], '', $resolve))
            ->toBeTrue()
            ->and(helpers_is_simulator('198.51.100.9', [], '198.51.100.9', $resolve))
            ->toBeTrue();
    });

    test('are not anybody else', function () use ($resolve) {
        expect(helpers_is_simulator('198.51.100.1', ['192.168.8.105', 'sim.example.org'], '198.51.100.9', $resolve))
            ->toBeFalse()
            ->and(helpers_is_simulator('', ['192.168.8.105'], '', $resolve))
            ->toBeFalse();
    });
});

describe('The box of a profile', function () {
    $avatar = 'aaaaaaaa-0000-4000-8000-000000000001';

    test('is written for a profile that exists, as 0 and 1', function () use ($avatar) {
        $written = [];

        expect(helpers_profile_publish(profiles_db([$avatar], $written), $avatar, true, 'false'))->toBe('ok');
        expect($written)->toBe([['allow' => 1, 'mature' => 0, 'id' => $avatar]]);
    });

    test('is not written for an avatar that made no profile, nor for what is not an avatar', function () use ($avatar) {
        $written = [];

        expect(helpers_profile_publish(profiles_db([], $written), $avatar, true, true))
            ->toBe('missing')
            ->and(helpers_profile_publish(profiles_db([$avatar], $written), "x' OR '1'='1", true, true))
            ->toBe('invalid')
            ->and($written)
            ->toBe([]);
    });
});
