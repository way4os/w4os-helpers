<?php

declare(strict_types=1);

use OpenSim\Config\GridConfig;
use OpenSim\Flux\Posts;
use OpenSim\Helpers\View\View;

/**
 * The stream of a profile (w4os-flux): its posts in a tab of the page, the Feed tab of the viewers (which opens the page of the
 * profile, `web_profile_url`), and its feeds.
 */

/** The grid of the profiles, Ada with two posts. */
function feed_grid(): OpenSim\Models\Grid
{
    $grid = profile_grid();
    $posts = Posts::for(GridConfig::fromFolder($grid->directory()));
    $posts->add('11111111-1111-4111-8111-111111111111', 'Ada Love', 'An older one', 1_700_000_000);
    $posts->add('11111111-1111-4111-8111-111111111111', 'Ada Love', 'See https://example.org/garden & a < b', 1_700_000_100);

    return $grid;
}

/** The page of a profile asked of the helpers of a grid (BaseTest), its databases the ones of feed_grid(). */
function feed_get(string $path, string $agent = 'Mozilla/5.0'): Psr\Http\Message\ResponseInterface
{
    feed_grid();

    return base_get(base_app('/helpers', ['web_profile_url' => 'https://example.com/helpers/profile/[AGENT_NAME]']), $path, $agent);
}

describe('The stream of a profile', function () {
    test('is the tab after the in-world one, newest first, its links made links, its text escaped', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', feed_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);
        preg_match_all('/<a class="tab__link" href="#profile-([a-z-]+)"><span>([^<]+)</', $html, $tabs);

        expect(array_slice($tabs[1], 0, 2))
            ->toBe(['inworld', 'feed'])
            ->and($tabs[2][1])
            ->toBe('Feed')
            ->and($html)
            ->toContain('<a href="https://example.org/garden" rel="nofollow ugc">https://example.org/garden</a> &amp; a &lt; b')
            ->and(strpos($html, 'See https') < strpos($html, 'An older one'))
            ->toBeTrue();
    });

    test('is all a viewer gets, as its Feed tab opens the page of the profile; or who asks it with feed_only', function () {
        $viewer = (string) feed_get('/helpers/profile/Ada.Love?name=Ada.Love', 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm-Releasex64)')->getBody();
        $asked = (string) feed_get('/helpers/profile/Ada.Love?feed_only=true')->getBody();
        $browser = (string) feed_get('/helpers/profile/Ada.Love')->getBody();

        expect($viewer)
            ->toContain('id="profile-feed"', 'An older one')
            ->not->toContain('id="profile-inworld"', 'profile-tabs')
            ->and($asked)
            ->toContain('id="profile-feed"')
            ->not->toContain('id="profile-picks"')
            ->and($browser)
            ->toContain('id="profile-inworld"', 'id="profile-feed"');
    });

    test('is a JSON Feed and an RSS feed too', function () {
        $json = json_decode((string) feed_get('/helpers/profile/Ada.Love?format=feed')->getBody(), true);
        $rss = feed_get('/helpers/profile/Ada.Love?format=rss');

        expect([$json['version'] ?? null, $json['title'] ?? null, array_column($json['items'] ?? [], 'content_text')])
            ->toBe(['https://jsonfeed.org/version/1.1', 'Ada Love', ['See https://example.org/garden & a < b', 'An older one']])
            ->and($rss->getHeaderLine('Content-Type'))
            ->toStartWith('application/rss+xml')
            ->and((string) $rss->getBody())
            ->toContain('<rss version="2.0">', '<title>Ada Love</title>', '<description>See https://example.org/garden &amp; a &lt; b</description>');
    });
});
