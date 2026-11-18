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
    test('is the first tab, before About, newest first, its links made links, its text escaped', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', feed_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);
        preg_match_all('/<a class="tab__link" href="#profile-([a-z-]+)"><span>([^<]+)</', $html, $tabs);

        expect(array_slice($tabs[1], 0, 2))
            ->toBe(['feed', 'about'])
            ->and($tabs[2][0])
            ->toBe('Feed')
            ->and($html)
            ->toContain('<a href="https://example.org/garden" rel="nofollow ugc">https://example.org/garden</a> &amp; a &lt; b')
            ->and(strpos($html, 'See https') < strpos($html, 'An older one'))
            ->toBeTrue();
    });

    test('comes with About and the interests in a viewer, whose own windows show the rest; alone for who asks it with feed_only', function () {
        $viewer = (string) feed_get('/helpers/profile/Ada.Love?name=Ada.Love', 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm-Releasex64)')->getBody();
        $asked = (string) feed_get('/helpers/profile/Ada.Love?feed_only=true')->getBody();
        $browser = (string) feed_get('/helpers/profile/Ada.Love')->getBody();

        expect($viewer)
            ->toContain('id="profile-feed"', 'An older one', 'id="profile-about"', 'id="profile-interests"', 'profile-tabs')
            ->not->toContain('id="profile-picks"', 'id="profile-web"')
            ->and($asked)
            ->toContain('id="profile-feed"')
            ->not->toContain('id="profile-picks"', 'id="profile-about"', 'profile-tabs')
            ->and($browser)
            ->toContain('id="profile-about"', 'id="profile-feed"', 'id="profile-picks"');
    });

    test('comes with About and the interests in a viewer, told by the engine (Destination::client), whoever shows the block: a site that gives its class or its agent', function () {
        $sections = fn (array $args) => OpenSim\Helpers\Block\Blocks::data('profile', feed_grid(), ['name' => 'Ada.Love'] + $args)['sections'];

        expect($sections(['client' => 'viewer']))->toBe(['feed', 'about', 'interests'])
            ->and($sections(['agent' => 'Mozilla/5.0 SecondLife/7.1.9 (Firestorm-Releasex64)']))->toBe(['feed', 'about', 'interests'])
            ->and($sections(['client' => 'browser']))->toBe(['feed', 'about', 'web', 'interests', 'picks', 'classifieds', 'real-life'])
            ->and($sections(['client' => 'browser', 'feed_only' => 'true']))->toBe(['feed']);
    });

    test('reads as a timeline: each post its date first, relative and small, its full date and time as its tip, then its message', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', feed_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);
        $post = OpenSim\Helpers\Block\ProfileBlock::post(['id' => 'p1', 'content' => 'Hi', 'created' => time() - 7200]);

        expect($post['ago'])->toBe('2 hours ago')
            ->and($post['full'])->toBe(OpenSim\Helpers\I18n::date(time() - 7200, 'long'))
            ->and($html)->toMatch('#<article id="profile-post-[^"]+" class="profile-post"><time class="profile-post-date" datetime="[^"]+" title="[^"]+">[^<]+ ago</time><p class="profile-post-text">See #')
            // Its files, for whoever shows the block: the styles of a profile
            ->and(View::blockFiles('profile')['css'])->toBe(['css/profile.css']);
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
