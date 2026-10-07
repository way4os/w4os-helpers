<?php
/**
 * Block profile: the page of an avatar, what its profile publishes and what it keeps to itself.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';
require_once dirname(__DIR__, 2) . '/classes/class-router.php';

describe('The profile of an avatar', function () {
    $account = ['FirstName' => 'Jane', 'LastName' => 'Doe', 'Created' => 1700000000, 'UserTitle' => 'Builder', 'UserFlags' => 1];
    $profile = [
        'profileAboutText' => "Builds things.\nLikes trains.",
        'profileFirstText' => 'Lives by the sea.',
        'profileWantToText' => 'Make friends',
        'profileSkillsText' => 'Scripting',
        'profileLanguages' => 'English, French',
        'profileURL' => 'https://jane.example.org/',
    ];
    $picks = [['name' => 'My shop', 'description' => 'Come and see', 'simname' => 'Plaza', 'posglobal' => '<256512, 256128, 30>', 'gatekeeper' => 'http://play.example.org:8002']];
    $ads = [['name' => 'Land to rent', 'description' => 'Cheap', 'simname' => 'Plaza', 'parcelname' => 'Meadow', 'creationdate' => 1, 'expirationdate' => 1900000000]];

    test('shows its name, since when it is a member, and what it published', function () use ($account, $profile, $picks, $ads) {
        $data = OpenSim_Helpers_Block_Profile::build($account, $profile, $picks, $ads, ['name' => 'John Roe', 'url' => '/profile/John.Roe']);

        expect($data)
            ->toMatchArray(['title' => 'Jane Doe', 'found' => true, 'member_since' => '2023-11-14', 'account_title' => 'Builder', 'published' => true, 'languages' => 'English, French', 'web' => 'https://jane.example.org/'])
            ->and($data['about'])
            ->toBe("Builds things.\nLikes trains.")
            ->and($data['partner'])
            ->toBe(['name' => 'John Roe', 'url' => '/profile/John.Roe'])
            ->and($data['classifieds'][0])
            ->toMatchArray(['name' => 'Land to rent', 'place' => 'Meadow, Plaza', 'until' => '2030-03-17'])
            // A pick is a place a viewer goes to: the position in the region is the rest of the global position
            ->and($data['picks'][0]['url'])
            ->toBe('hop://play.example.org:8002/Plaza/0/128/30');
    });

    test('keeps to itself what is not published: the name and the date of the account are all that is said', function () use ($account, $profile, $picks, $ads) {
        $data = OpenSim_Helpers_Block_Profile::build(['UserFlags' => 2] + $account, $profile, $picks, $ads, ['name' => 'John Roe', 'url' => null]);

        expect($data)
            ->toMatchArray(['published' => false, 'about' => '', 'languages' => '', 'web' => '', 'partner' => null, 'picks' => [], 'classifieds' => []])
            ->and($data['member_since'])
            ->toBe('2023-11-14')
            // An avatar that did not ask for it and never made a profile is the same
            ->and(OpenSim_Helpers_Block_Profile::build(['UserFlags' => 0] + $account, [], [], [])['published'])
            ->toBeFalse();
    });

    test('takes a flag as the service writes it, a byte or the digit', function () {
        expect(OpenSim_Helpers_Block_Profile::flag("\x01"))
            ->toBeTrue()
            ->and(OpenSim_Helpers_Block_Profile::flag('1'))
            ->toBeTrue()
            ->and(OpenSim_Helpers_Block_Profile::flag("\x00"))
            ->toBeFalse()
            ->and(OpenSim_Helpers_Block_Profile::flag('0'))
            ->toBeFalse()
            ->and(OpenSim_Helpers_Block_Profile::flag(null))
            ->toBeFalse();
    });

    test('does not link what is not a web address', function () use ($account, $profile) {
        expect(OpenSim_Helpers_Block_Profile::build($account, ['profileURL' => 'javascript:alert(1)'] + $profile, [], [])['web'])->toBe('');
    });

    test('is shown by its template, the text as text and the line breaks kept', function () use ($account, $profile) {
        $profile['profileAboutText'] = "<b>bold</b>\nsecond";
        $html = OpenSim_Helpers_View::render('components/profile-block.twig', OpenSim_Helpers_Block_Profile::build($account, $profile, [], []));

        expect($html)
            ->toContain('<h1 class="block-title">Jane Doe</h1>')
            ->toContain('&lt;b&gt;bold&lt;/b&gt;<br />')
            ->not->toContain('<b>bold</b>')
            ->toContain('rel="nofollow ugc"');
    });

    test('is a page that says so, with a 404, for an avatar that is not there', function () {
        OpenSim_Helpers_View::set_status(200);
        $data = (function () {
            // No database: nobody is found
            return OpenSim_Helpers_Block_Profile::data(['name' => 'Nobody.Here']);
        })();
        $html = OpenSim_Helpers_Blocks::render('profile', ['name' => 'Nobody.Here']);

        expect($data)
            ->toMatchArray(['found' => false])
            ->and(OpenSim_Helpers_View::$status)
            ->toBe(404)
            ->and($html)
            ->toContain('There is no such avatar here.');
        OpenSim_Helpers_View::set_status(200);
    });
});
