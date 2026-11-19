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
        } catch (\OpenSim\Exceptions\ChannelUnavailable $e) {
            // Unknown for now, not missing
            Blocks::unreachable($e);

            return ['title' => _('Profile'), 'found' => false, 'unavailable' => true, 'name' => $name];
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
        $data['banner'] = self::banner($d, $published);
        if ($published) {
            // What the viewer shows of each (the engine's Avatar::place()): picture, place, teleport link
            foreach ($avatar->picks() as $pick) {
                $data['picks'][] = self::item($pick, $grid, $args);
            }
            foreach ($avatar->classifieds() as $ad) {
                $data['classifieds'][] = self::item($ad, $grid, $args);
            }
            // Its stream (w4os-flux), newest first: none when the grid keeps no posts
            try {
                $data['feed'] = array_map(self::post(...), Posts::for($config)->of((string) $d['uuid']));
            } catch (\OpenSim\Exceptions\ChannelUnavailable $e) {
                // The rest of the profile is there: its stream said unavailable, not empty
                Blocks::unreachable($e);
                $data['feed'] = [];
            }
        }

        // The sections of the profile as tabs, in the order of the engine, its stream first, the empty ones too: every one in a browser; in a viewer, whose Feed tab
        // opens this page, the stream, About and the interests, its own windows showing the rest (the engine tells a viewer: the
        // class a site gives, else the user agent); the stream alone for whoever asks it so (feed_only, as the viewers of SL do)
        $client = isset($args['client']) ? (string) $args['client'] : \OpenSim\Hypergrid\Destination::client(isset($args['agent']) ? (string) $args['agent'] : null);
        // The sections are the engine's, as the grid has them (none the grid does not allow: the web address of a profile)
        $sections = array_column($avatar->form()->sections, 'name');
        $data['sections'] = array_values(array_intersect($sections, match (true) {
            \OpenSim\Result::isTrue($args['feed_only'] ?? false) => ['feed'],
            $client === 'viewer' => ['feed', 'about', 'interests'],
            default => $sections,
        }));
        $data['open'] = self::open($data['sections'], $data);

        return $data;
    }

    /**
     * The banner of a profile, over all its tabs: the fields the engine marks in the form of an avatar (Avatar::itemForm()->banner()),
     * its picture and the details under its name (its birth with its age, its role), each with its label; nothing but its name
     * (the title of the block) for a profile not published.
     *
     * @param array<string,mixed> $avatar Avatar::detail()
     * @return array{picture: ?string, details: list<array{label: ?string, value: string}>}
     */
    public static function banner(array $avatar, bool $published): array
    {
        $banner = ['picture' => null, 'details' => []];
        if (!$published) {
            return $banner;
        }
        $texts = Catalog::messages(I18n::current()) + Catalog::MESSAGES;
        $form = Avatar::itemForm();
        foreach ($form->fields as $field) {
            if (!in_array($field['name'], $form->banner(), true)) {
                continue;
            }
            $value = $avatar[$field['name']] ?? null;
            if ($field['type'] === 'picture') {
                // The silhouette of a person of the engine when there is none
                $banner['picture'] = trim((string) $value, '0-') !== '' ? (string) $value : WebAssets::PERSON;
                continue;
            }
            $text = match ($field['type']) {
                // The name is the title of the block
                'text' => null,
                'datetime' => (int) $value > 0 ? I18n::date((int) $value) . ' (' . self::age(intdiv(max(0, time() - (int) $value), 86400)) . ')' : null,
                'select' => isset($field['options'][(string) $value]) ? (string) ($texts[$field['options'][(string) $value]] ?? $value) : null,
                default => is_scalar($value) && (string) $value !== '' ? (string) $value : null,
            };
            // A value that reads alone (`bare`, the role) has no label
            $text === null || $banner['details'][] = ['label' => empty($field['bare']) ? (string) ($texts[$field['label']] ?? $field['label']) : null, 'value' => $text];
        }

        return $banner;
    }

    /**
     * The tab a profile opens on: the first of its sections that has something, the first when none has.
     *
     * @param list<string> $sections
     * @param array<string,mixed> $data the profile (data())
     */
    public static function open(array $sections, array $data): string
    {
        $has = static fn(string ...$keys): bool => array_filter($keys, static fn(string $key): bool => !empty($data[$key])) !== [];
        foreach ($sections as $section) {
            $full = match ($section) {
                'feed' => $has('feed'),
                'about' => $has('about', 'partner', 'groups'),
                'web' => $has('web'),
                'interests' => $has('wants_list', 'wants', 'skills_list', 'skills', 'languages'),
                'picks', 'classifieds' => $has($section),
                'real-life' => $has('real_life') || ($data['first_picture'] ?? WebAssets::PERSON) !== WebAssets::PERSON,
                default => false,
            };
            if ($full) {
                return $section;
            }
        }

        return $sections[0] ?? 'feed';
    }

    /**
     * A post of the stream as the page shows it: its text escaped, its addresses links, its date in the language of the page, told
     * relatively (`ago`) with its full date and time (`full`).
     *
     * @param array{id:string,content:string,created:int} $post
     * @return array{id:string,text:string,html:string,created:int,date:string,ago:string,full:string}
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
            'ago' => I18n::ago($post['created']),
            'full' => I18n::date($post['created'], 'long'),
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
    public static function item(array $row, ?Grid $grid = null, array $args = []): array
    {
        $position = is_array($row['position'] ?? null) ? ' (' . implode(', ', $row['position']) . ')' : '';
        $until = (int) ($row['expirationdate'] ?? $row['expires'] ?? 0);

        return [
            'name' => (string) ($row['name'] ?? ''),
            'description' => trim((string) ($row['description'] ?? '')),
            'picture' => (string) ($row['picture'] ?? '') ?: WebAssets::PLACEHOLDER,
            'place' => implode(', ', array_filter([$row['parcel'] ?? null, $row['region'] ?? null])) . (($row['region'] ?? null) === null ? '' : $position),
            // The teleport of the engine (hop://) as the link for where it is shown (Links::to())
            'url' => ($row['teleport'] ?? null) === null || $grid === null ? ($row['teleport'] ?? null) : Links::to($grid, (string) $row['teleport'], $args),
            'until' => $until > 0 ? I18n::date($until) : null,
        ];
    }
}
