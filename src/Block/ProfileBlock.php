<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
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
            'picks' => [],
            'classifieds' => [],
        ];
        if ($published) {
            foreach ($avatar->picks() as $pick) {
                $region = (string) ($pick['simname'] ?? '');
                $data['picks'][] = [
                    'name' => (string) $pick['name'],
                    'description' => trim((string) $pick['description']),
                    'place' => $region,
                    'url' => $this->hop($search, (string) ($pick['gatekeeper'] ?? ''), $region, (string) ($pick['posglobal'] ?? '')),
                ];
            }
            foreach ($avatar->classifieds() as $ad) {
                $data['classifieds'][] = [
                    'name' => (string) $ad['name'],
                    'description' => trim((string) $ad['description']),
                    'place' => trim(($ad['parcelname'] ?? '') . (empty($ad['simname']) ? '' : ', ' . $ad['simname']), ', '),
                    'until' => date('Y-m-d', (int) $ad['expirationdate']),
                ];
            }
        }

        return $data;
    }

    /** @param array<string,mixed> $detail */
    private function url(Grid $grid, array $detail): ?string
    {
        $path = rtrim((string) preg_replace('#/?\[AGENT_NAME\].*$#', '', (string) parse_url((string) $grid->address('web-profile'), PHP_URL_PATH)), '/');

        return $path === '' ? null : "$path/" . rawurlencode($detail['firstName'] . '.' . $detail['lastName']);
    }

    /** The place of a pick: its position is global, the position in the region is the rest of the division by 256. */
    private function hop(SearchData $search, string $gatekeeper, string $region, string $posglobal): ?string
    {
        if ($region === '' || preg_match('/<?\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/', $posglobal, $m) !== 1) {
            return null;
        }

        return $search->hop($gatekeeper, $region, sprintf('%d/%d/%d', fmod((float) $m[1], 256), fmod((float) $m[2], 256), (float) $m[3]));
    }
}
