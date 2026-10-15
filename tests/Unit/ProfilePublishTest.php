<?php
/**
 * The "show in search" box of a profile: who may tell it (the holder of the secure session of the avatar), and what is written.
 */

require_once dirname(__DIR__, 2) . '/includes/profile-publish.php';

/** A database for the user accounts: it has the accounts of its fixtures (UserFlags by UUID), and keeps what was written. */
function accounts_db(array $accounts, array &$written): object
{
    return new class($accounts, $written) {
        public function __construct(private array $accounts, private array &$written) {}

        public function prepareAndExecute($sql, $params = [])
        {
            if (str_starts_with($sql, 'UPDATE')) {
                $this->written[] = $params;
            }
            $rows = str_starts_with($sql, 'SELECT') && isset($this->accounts[$params['id']]) ? [['UserFlags' => $this->accounts[$params['id']]]] : [];

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

describe('caller that may tell it', function () {
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

describe('box of a profile', function () {
    $avatar = 'aaaaaaaa-0000-4000-8000-000000000001';

    test('is written in the first flag of the account, as 1, every other flag is kept (age verified is 32)', function () use ($avatar) {
        $written = [];

        // 0x2e = mature (2), identified (4), transacted (8), age verified (32): none is ours but the first
        expect(helpers_profile_publish(accounts_db([$avatar => 0x2e], $written), $avatar, true))->toBe('ok');
        expect(helpers_profile_publish(accounts_db([$avatar => 0x2f], $written), $avatar, 'true'))->toBe('ok');
        expect(helpers_profile_publish(accounts_db([$avatar => 0x2f], $written), $avatar, false))->toBe('ok');
        expect($written)->toBe([
            ['flags' => 0x2f, 'id' => $avatar],
            ['flags' => 0x2f, 'id' => $avatar],
            ['flags' => 0x2e, 'id' => $avatar],
        ]);
    });

    test('is not written for what is not an account of the grid, nor for what is not an avatar', function () use ($avatar) {
        $written = [];

        expect(helpers_profile_publish(accounts_db([], $written), $avatar, true))
            ->toBe('missing')
            ->and(helpers_profile_publish(accounts_db([$avatar => 0], $written), "x' OR '1'='1", true))
            ->toBe('invalid')
            ->and($written)
            ->toBe([]);
    });
});
