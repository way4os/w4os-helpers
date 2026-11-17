<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Assets\WebAssets;
use OpenSim\Helpers\I18n;
use OpenSim\I18n\Catalog;
use OpenSim\Flux\Posts;
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
        $published = $avatar->isPublished();
        $text = static fn(string $key): string => $published ? trim((string) ($d[$key] ?? '')) : '';
        $partner = null;
        if ($published && $d['partner'] !== null) {
            $other = Avatar::find($config, $d['partner']);
            $partner = $other ? ['name' => $other->name(), 'url' => Avatar::profileAt(Blocks::siteAddress($args, 'web_profile_url') ?? (string) $grid->address('web-profile'), $other->name())] : null;
        }
        $data = [
            'title' => trim($d['firstName'] . ' ' . $d['lastName']),
            'found' => true,
            'member_since' => (int) ($d['created'] ?? 0) > 0 ? I18n::date((int) $d['created']) : '',
            // What the in-world tab shows, as the viewer's: the key, the age of the account in days, the account (its role)
            'key' => (string) ($d['uuid'] ?? ''),
            'age' => (int) ($d['created'] ?? 0) > 0 ? self::age(intdiv(max(0, time() - (int) $d['created']), 86400)) : null,
            // The groups it shows in its profile (ListInProfile), by name
            'groups' => $published ? array_column($avatar->groups(), 'name') : [],
            'role' => (string) ((Catalog::messages(I18n::current()) + Catalog::MESSAGES)[Catalog::roleKey((string) ($d['role'] ?? ''))] ?? ''),
            // The picture of the real life, the silhouette of a person of the engine when there is none
            'first_picture' => trim((string) ($d['firstImage'] ?? ''), '0-') !== '' ? (string) $d['firstImage'] : WebAssets::PERSON,
            'account_title' => trim((string) ($d['title'] ?? '')),
            // The picture of the profile, seen by anyone in the world: the silhouette of a person of the engine when there is none
            'picture' => trim((string) ($d['profileImage'] ?? ''), '0-') !== '' ? (string) $d['profileImage'] : WebAssets::PERSON,
            'published' => $published,
            'about' => $text('about'),
            'real_life' => $text('firstLife'),
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
            'feed' => [],
        ];
        if ($published) {
            // What the viewer shows of each (the engine's Avatar::place()): picture, place, teleport link
            foreach ($avatar->picks() as $pick) {
                $data['picks'][] = self::item($pick);
            }
            foreach ($avatar->classifieds() as $ad) {
                $data['classifieds'][] = self::item($ad);
            }
            // Its stream (w4os-flux), newest first: none when the grid keeps no posts
            try {
                $data['feed'] = array_map(self::post(...), Posts::for($config)->of((string) $d['uuid']));
            } catch (\OpenSim\Exceptions\ChannelUnavailable) {
                $data['feed'] = [];
            }
        }

        // Every section of the viewer's profile, in its order (its tabs), the empty ones too; the stream where the viewer has its
        // Feed tab, and alone in it (which opens this page), or for whoever asks it alone (feed_only, as the viewers of SL do)
        $data['sections'] = \OpenSim\Result::isTrue($args['feed_only'] ?? false)
            ? ['feed']
            : ['inworld', 'feed', 'web', 'interests', 'picks', 'classifieds', 'real-life'];

        return $data;
    }

    /**
     * A post of the stream as the page shows it: its text escaped, its addresses links, its date in the language of the page.
     *
     * @param array{id:string,content:string,created:int} $post
     * @return array{id:string,text:string,html:string,created:int,date:string}
     */
    public static function post(array $post): array
    {
        $html = preg_replace_callback(
            '#\bhttps?://[^\s<>"]+#i',
            static fn(array $m): string => '<a href="' . $m[0] . '" rel="nofollow ugc">' . $m[0] . '</a>',
            htmlspecialchars($post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );

        return [
            'id' => $post['id'],
            'text' => $post['content'],
            'html' => nl2br((string) $html),
            'created' => $post['created'],
            'date' => I18n::date($post['created']),
        ];
    }

    /** The age of an account as the viewer says it: joined today, else its days, in the form of their number. */
    private static function age(int $days): string
    {
        return $days === 0
            ? (string) ((Catalog::messages(I18n::current()) + Catalog::MESSAGES)['profile_age_today'] ?? 'Joined today')
            : sprintf(Catalog::plural(I18n::current(), 'profile_age_days', $days), $days);
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

    /**
     * A pick or a classified as the viewer shows it, from what the engine gives of it (Avatar::picks(), classifieds(), or their API):
     * the place is "parcel, region (x, y, z)", a classified ends on a date. For the sites that show them the same way (View::items()).
     *
     * @param array<string,mixed> $row
     * @return array{name:string, description:string, picture:string, place:string, url:?string, until:?string}
     */
    public static function item(array $row): array
    {
        $position = is_array($row['position'] ?? null) ? ' (' . implode(', ', $row['position']) . ')' : '';
        $until = (int) ($row['expirationdate'] ?? $row['expires'] ?? 0);

        return [
            'name' => (string) ($row['name'] ?? ''),
            'description' => trim((string) ($row['description'] ?? '')),
            'picture' => (string) ($row['picture'] ?? '') ?: WebAssets::PLACEHOLDER,
            'place' => implode(', ', array_filter([$row['parcel'] ?? null, $row['region'] ?? null])) . (($row['region'] ?? null) === null ? '' : $position),
            'url' => $row['teleport'] ?? null,
            'until' => $until > 0 ? I18n::date($until) : null,
        ];
    }
}
