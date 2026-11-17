<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Models\Avatar;
use OpenSim\Models\Grid;

/**
 * The people who joined the grid last, as w4os lists its recent users: the newest active avatars, never an avatar model, the
 * account of the services or an account without an email. Their name links to their profile; their email is not shown, the block
 * may be public.
 */
final class NewUsersBlock implements Block
{
    public const COUNT = 5;

    public function data(Grid $grid, array $args = []): array
    {
        $config = (new SearchData($grid))->config;
        $count = max(1, min(50, (int) ($args['count'] ?? self::COUNT)));
        try {
            $avatars = $config === null
                ? []
                : Avatar::all($config, [
                    'filters' => ['status' => 'active', 'model' => 'no', 'technical' => 'no', 'email' => 'yes'],
                    'sort' => 'created desc',
                    'perPage' => $count,
                ])->items();
        } catch (\OpenSim\Exceptions\ChannelUnavailable) {
            $avatars = [];
        }
        $profiles = Blocks::siteAddress($args, 'web_profile_url') ?? (string) $grid->address('web-profile');
        $rows = [];
        foreach ($avatars as $avatar) {
            $created = (int) ($avatar->detail()['created'] ?? 0);
            $rows[] = [
                'label' => $avatar->name(),
                'value' => $created > 0 ? date('Y-m-d', $created) : '',
                'url' => Avatar::profileAt($profiles, $avatar->name()),
            ];
        }

        return ['title' => _('Recent users'), 'rows' => $rows];
    }
}
