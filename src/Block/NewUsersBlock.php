<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Helpers\I18n;
use W4OS\Engine\Models\Avatar;
use W4OS\Engine\Models\Grid;

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
        } catch (\W4OS\Engine\Exceptions\ChannelUnavailable $e) {
            Blocks::unreachable($e);
            $avatars = [];
            $unavailable = true;
        }
        $profiles = Blocks::siteAddress($args, 'web_profile_url') ?? (string) $grid->address('web-profile');
        $rows = [];
        foreach ($avatars as $avatar) {
            $created = (int) ($avatar->detail()['created'] ?? 0);
            $rows[] = [
                'label' => $avatar->name(),
                'value' => $created > 0 ? I18n::date($created, 'list') : '',
                'url' => Avatar::profileAt($profiles, $avatar->name()),
            ];
        }

        return ['title' => I18n::text('Recent users'), 'rows' => $rows] + (isset($unavailable) ? ['unavailable' => true] : []);
    }
}
