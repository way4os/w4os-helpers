<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
use OpenSim\Helpers\I18n;
use OpenSim\I18n\Catalog;
use OpenSim\Models\Avatar;
use OpenSim\Models\Grid;

/** The page of a profile; what the avatar did not publish ("Show in search") is not shown. */
final class ProfileBlock implements Block
{
    public function data(Grid $grid, array $args = []): array
    {
        $search = new SearchData($grid);
        $config = $search->config;
        $name = (string) ($args['name'] ?? '');
        [$first, $last] = array_pad(explode('.', explode('@', $name)[0], 2), 2, '');
        try {
            $avatar = $config === null ? null : Avatar::find($config, "$first.$last");
        } catch (\OpenSim\Exceptions\ChannelUnavailable) {
            $avatar = null;
        }
        // The account of the services of the grid, or one that is not active, has no page
        if ($avatar === null || $avatar->isTechnical() || !$avatar->isEnabled()) {
            return ['title' => _('Profile not found'), 'found' => false, 'name' => $name];
        }
        $d = $avatar->detail();
        $published = ((int) ($d['flags'] ?? 0) & 1) === 1;
        $text = static fn(string $key): string => $published ? trim((string) ($d[$key] ?? '')) : '';
        $partner = null;
        if ($published && $d['partner'] !== null) {
            $other = Avatar::find($config, $d['partner']);
            $partner = $other ? ['name' => $other->name(), 'url' => $this->url($grid, $other->detail())] : null;
        }
        $data = [
            'title' => trim($d['firstName'] . ' ' . $d['lastName']),
            'found' => true,
            'member_since' => (int) ($d['created'] ?? 0) > 0 ? date('Y-m-d', (int) $d['created']) : '',
            'account_title' => trim((string) ($d['title'] ?? '')),
            // The picture of the profile, seen by anyone in the world: the placeholder of the engine when there is none
            'picture' => trim((string) ($d['profileImage'] ?? ''), '0-') !== '' ? (string) $d['profileImage'] : WebAssets::PLACEHOLDER,
            'published' => $published,
            'about' => $text('about'),
            'first_life' => $text('firstLife'),
            'wants' => $text('wants'),
            'skills' => $text('skills'),
            'languages' => $text('languages'),
            'web' => preg_match('#^https?://#i', $text('profileUrl')) ? $text('profileUrl') : '',
            'partner' => $partner,
            // The boxes checked in the viewer, by their texts in the language of the page
            'wants_list' => $published ? self::flags($d['wantsList'] ?? [], 'want_') : [],
            'skills_list' => $published ? self::flags($d['skillsList'] ?? [], 'skill_') : [],
            'picks' => [],
            'classifieds' => [],
        ];
        if ($published) {
            // What the viewer shows of each (the engine's Avatar::place()): picture, place, teleport link
            foreach ($avatar->picks() as $pick) {
                $data['picks'][] = self::item($pick);
            }
            foreach ($avatar->classifieds() as $ad) {
                $data['classifieds'][] = self::item($ad) + ['until' => date('Y-m-d', (int) $ad['expirationdate'])];
            }
        }

        // Every section of the viewer's profile, in its order (its tabs), the empty ones too
        $data['sections'] = ['second-life', 'web', 'interests', 'picks', 'classifieds', 'first-life'];

        return $data;
    }

    /**
     * The texts of the boxes of a profile (the engine's names of its flags), in the language in use.
     *
     * @param list<string> $names
     * @return list<string>
     */
    private static function flags(array $names, string $prefix): array
    {
        $texts = Catalog::messages(I18n::current());

        return array_map(static fn(string $name): string => $texts[$prefix . str_replace('-', '_', $name)] ?? $name, $names);
    }

    /** @param array<string,mixed> $detail */
    private function url(Grid $grid, array $detail): ?string
    {
        $path = rtrim((string) preg_replace('#/?\[AGENT_NAME\].*$#', '', (string) parse_url((string) $grid->address('web-profile'), PHP_URL_PATH)), '/');

        return $path === '' ? null : "$path/" . rawurlencode($detail['firstName'] . '.' . $detail['lastName']);
    }

    /**
     * A pick or a classified as the viewer shows it, from what the engine gives of it: the place is "parcel, region (x, y, z)".
     *
     * @param array<string,mixed> $row
     * @return array{name:string, description:string, picture:string, place:string, url:?string}
     */
    private static function item(array $row): array
    {
        $position = is_array($row['position'] ?? null) ? ' (' . implode(', ', $row['position']) . ')' : '';

        return [
            'name' => (string) ($row['name'] ?? ''),
            'description' => trim((string) ($row['description'] ?? '')),
            'picture' => (string) ($row['picture'] ?? '') ?: WebAssets::PLACEHOLDER,
            'place' => implode(', ', array_filter([$row['parcel'] ?? null, $row['region'] ?? null])) . (($row['region'] ?? null) === null ? '' : $position),
            'url' => $row['teleport'] ?? null,
        ];
    }
}
