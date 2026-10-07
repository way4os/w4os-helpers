<?php
/**
 * The "show in search" box of a profile: who may tell it (the holder of the secure session of the avatar), and what is written.
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

describe('The caller that may tell it', function () {
    $session = fn($avatar) => $avatar === 'aaaaaaaa-0000-4000-8000-000000000001' ? ['secureID' => 'B0B0B0B0-0000-4000-8000-000000000002'] : [];

    test('holds the secure session of an avatar who is in the grid', function () use ($session) {
        expect(helpers_has_session('aaaaaaaa-0000-4000-8000-000000000001', 'b0b0b0b0-0000-4000-8000-000000000002', $session))->toBeTrue();
    });

    test('is nobody else: another session, none, an avatar who is not in the grid, a null session', function () use ($session) {
        $null = '00000000-0000-0000-0000-000000000000';

        expect(helpers_has_session('aaaaaaaa-0000-4000-8000-000000000001', 'b0b0b0b0-0000-4000-8000-0000000000ff', $session))
            ->toBeFalse()
            ->and(helpers_has_session('aaaaaaaa-0000-4000-8000-000000000001', '', $session))
            ->toBeFalse()
            ->and(helpers_has_session('cccccccc-0000-4000-8000-0000000000cc', 'b0b0b0b0-0000-4000-8000-000000000002', $session))
            ->toBeFalse()
            ->and(helpers_has_session('dddddddd-0000-4000-8000-0000000000dd', $null, fn() => ['secureID' => $null]))
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
