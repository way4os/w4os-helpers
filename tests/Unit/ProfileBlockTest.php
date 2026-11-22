<?php

use W4OS\Engine\Data\Connections;
use W4OS\Engine\Data\OSPDO;
use W4OS\Helpers\View\View;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Testing\Machine;

/** A grid with two published profiles: one with several sections of the viewer's profile, one with its about text alone. */
function profile_grid(): Grid
{
    $machine = new Machine();
    $robust = (string) file_get_contents($machine->grid . '/Robust.HG.ini');
    $machine->write(
        $machine->grid . '/Robust.HG.ini',
        $robust . "\n[DatabaseService]\n    ConnectionString = \"Data Source=localhost;Database=robust;User ID=os;Password=pw;\"\n" .
            "[GridInfoService]\n    web_profile_url = \"https://demo.example.org/helpers/profile/[AGENT_NAME]\"\n",
    );
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE UserAccounts (PrincipalID CHAR(36) PRIMARY KEY, ScopeID CHAR(36), FirstName VARCHAR(64), LastName VARCHAR(64), Email VARCHAR(64), Created INT, UserLevel INT DEFAULT 0, UserFlags INT DEFAULT 0, UserTitle VARCHAR(64) DEFAULT \'\', active INT DEFAULT 1)');
    $pdo->exec("CREATE TABLE GridUser (UserID VARCHAR(255) PRIMARY KEY, HomeRegionID CHAR(36), LastRegionID CHAR(36), Online CHAR(5) DEFAULT 'false', Login CHAR(16) DEFAULT '0', Logout CHAR(16) DEFAULT '0')");
    $pdo->exec('CREATE TABLE userprofile (useruuid CHAR(36) PRIMARY KEY, profileImage CHAR(36), profileAboutText TEXT, profileURL VARCHAR(255), profilePartner CHAR(36), profileWantToText TEXT, profileSkillsText TEXT, profileLanguages TEXT, profileFirstImage CHAR(36), profileFirstText TEXT, profileWantToMask INT DEFAULT 0, profileSkillsMask INT DEFAULT 0)');
    $pdo->exec('CREATE TABLE userpicks (pickuuid CHAR(36), creatoruuid CHAR(36), name TEXT, description TEXT, snapshotuuid CHAR(36), simname TEXT, posglobal TEXT, gatekeeper TEXT, enabled TEXT, sortorder INT)');
    $pdo->exec("INSERT INTO userpicks VALUES ('99999999-9999-4999-8999-999999999999', '11111111-1111-4111-8111-111111111111', 'Welcome', '', '00000000-0000-0000-0000-000000000000', 'Welcome', '<256128.5, 256128.2, 26>', 'http://demo.example.org:8002', 'true', 0)");
    $pdo->exec('CREATE TABLE classifieds (creatoruuid CHAR(36), name TEXT, description TEXT, simname TEXT, parcelname TEXT, creationdate INT, expirationdate INT)');
    $pdo->exec("INSERT INTO UserAccounts (PrincipalID, FirstName, LastName, Email, Created, UserFlags) VALUES
        ('11111111-1111-4111-8111-111111111111', 'Ada', 'Love', 'ada@example.org', 1700000000, 1),
        ('22222222-2222-4222-8222-222222222222', 'Bob', 'Plain', 'bob@example.org', 1700000000, 1)");
    $pdo->exec("INSERT INTO userprofile (useruuid, profileAboutText, profileWantToText, profileWantToMask, profileLanguages, profileFirstText, profilePartner) VALUES
        ('11111111-1111-4111-8111-111111111111', 'Hello', 'Gardens', 5, 'Français, English', 'A painter', '22222222-2222-4222-8222-222222222222'),
        ('22222222-2222-4222-8222-222222222222', 'Just me', '', 0, '', '', NULL)");
    Connections::using(static fn() => $pdo);

    return Grid::find($machine->environment, 'demo');
}

afterEach(fn() => Connections::using(null));

describe('The block of a profile', function () {
    test('shows its stream first, then About and every other section of the viewer, as tabs; an empty one says so', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);
        preg_match_all('/<a class="tab__link" href="#profile-([a-z-]+)"><span>([^<]+)</', $html, $tabs);

        expect($tabs[1])->toBe(['feed', 'about', 'web', 'interests', 'picks', 'classifieds', 'real-life'])
            ->and($tabs[2])->toBe(['Feed', 'About', 'Web', 'Interests', 'Picks', 'Classifieds', 'Real life'])
            ->and($html)->toContain('Build, Meet<br>Gardens', 'Français, English', 'A painter', 'role\', \'tablist\'', '<p class="profile-none">None.</p>');
    });

    test('takes its sections from the engine: no Web tab when the grid does not allow the web address of a profile', function () {
        $grid = profile_grid();
        file_put_contents($grid->directory() . '/OpenSim.ini', "[UserProfiles]\n AllowUserProfileWebURLs = false\n");
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', $grid, ['name' => 'Ada.Love', 'lang' => 'en']);
        preg_match_all('/<a class="tab__link" href="#profile-([a-z-]+)"><span>([^<]+)</', $html, $tabs);
        $sections = fn (array $args) => W4OS\Helpers\Block\Blocks::data('profile', $grid, ['name' => 'Ada.Love'] + $args)['sections'];

        expect($tabs[1])->toBe(['feed', 'about', 'interests', 'picks', 'classifieds', 'real-life'])
            ->and($html)->not->toContain('id="profile-web"')
            ->and($sections(['client' => 'viewer']))->toBe(['feed', 'about', 'interests']);
    });

    test('has a banner over every tab, the fields the engine marks: the picture, the name, the birth with the age, the role alone', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);
        $banner = substr($html, (int) strpos($html, '<header class="profile-banner">'), (int) strpos($html, '</header>') - (int) strpos($html, '<header class="profile-banner">'));

        expect($banner)->toContain(
            '<span class="picture profile-photo" data-initials="AL"><img src="/pictures/c14a1985-c549-4485-8779-7103d74ef5e0.jpg" alt="" loading="lazy" onerror="this.hidden=true"></span>',
            '<h1 class="block-title">Ada Love</h1>',
            '<dt>Born</dt><dd>' . W4OS\Helpers\I18n::date(1700000000) . ' (',
            ' days)</dd>',
            '<p class="profile-banner-bare">Resident</p>',
        )->not->toContain('<dt>Role</dt>')->and(strpos($html, '<header class="profile-banner">'))->toBeLessThan(strpos($html, 'profile-tabs'));
    });

    test('shows the rest of the in-world tab in About: the partner, the about text, the groups; never the key, the viewer has it', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);
        $about = substr($html, (int) strpos($html, '<section id="profile-about"'));

        expect($html)->not->toContain('11111111-1111-4111-8111-111111111111', '<dt>Key</dt>')
            ->and($about)->toContain(
            'Hello',
            "asked() === '#' + id.replace(/^profile-/, '')",
            // The former names of the tab lead to it
            "'#second-life': '#about'",
            "'#inworld': '#about'",
            // The partner, linked to their profile at the address the grid gives
            '<a href="https://demo.example.org/helpers/profile/Bob.Plain">Bob Plain</a>',
        )->not->toContain('Member since');
    });

    test('shows the picks as the viewer: the list, then each one with its picture, description, place and teleport link', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);

        expect($html)->toContain(
            '<li><a href="#profile-pick-1">Welcome</a></li>',
            '<article id="profile-pick-1" class="profile-item">',
            // No snapshot: the image of its name, the engine's
            '/' . W4OS\Engine\Assets\TitleImage::id('Welcome') . '.jpg"',
            'No description given.',
            'Welcome (128, 128, 26)',
            'class="profile-item-teleport" href="hop://',
        );
    });

    test('names its tabs as the catalog of the engine does, in the language of the page', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'fr']);
        preg_match_all('/<a class="tab__link" href="#profile-([a-z-]+)"><span>([^<]+)</', $html, $tabs);
        W4OS\Helpers\I18n::use('en');

        expect($tabs[2])->toBe(['Flux', 'À propos', 'Web', 'Centres d’intérêt', 'Favoris', 'Petites annonces', 'Vie réelle'])
            ->and($html)->toContain('aria-label="Sections du profil"');
    })->skip(fn() => setlocale(LC_MESSAGES, ['fr_FR.UTF-8', 'fr_FR.utf8']) === false, 'the system has no French locale');

    test('a profile with little has every tab all the same', function () {
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Bob.Plain', 'lang' => 'en']);

        expect($html)->toContain('id="profile-about"', 'Just me', 'profile-tabs', 'id="profile-picks"')->not->toContain('class="profile-items');
    });

    test('shows the picks or classifieds a site gives the same way, from their API', function () {
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $html = $view->items('classifieds', [['name' => 'Shop', 'description' => '', 'picture' => W4OS\Engine\Assets\TitleImage::id('Shop'), 'parcel' => 'Mall', 'region' => 'Town', 'position' => [10, 20, 30], 'teleport' => 'hop://g:8002/Town/10/20/30', 'expires' => 1893456000]], '/assets/', 'en');

        expect($html)->toContain('<article id="profile-classified-1" class="profile-item">', '/assets/' . W4OS\Engine\Assets\TitleImage::id('Shop') . '.jpg', 'Mall, Town (10, 20, 30)', 'No description given.', W4OS\Helpers\I18n::date(1893456000), 'href="hop://g:8002/Town/10/20/30"')
            ->and($view->items('picks', [], '/assets'))->toContain('<p class="profile-none">None.</p>');
    });
});

describe('The tab a profile opens on', function () {
    test('is the first that has something: About for a stream with nothing, the stream once it has posts, the first when all are empty', function () {
        $open = fn (Grid $grid, string $name, array $args = []) => W4OS\Helpers\Block\Blocks::data('profile', $grid, ['name' => $name] + $args)['open'];
        $html = (new View(dirname(__DIR__, 2) . '/templates'))->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en']);

        expect($open(profile_grid(), 'Ada.Love'))->toBe('about')
            ->and($open(profile_grid(), 'Ada.Love', ['client' => 'viewer']))->toBe('about')
            ->and($open(feed_grid(), 'Ada.Love'))->toBe('feed')
            ->and(W4OS\Helpers\Block\ProfileBlock::open(['feed', 'about'], ['feed' => [], 'about' => '', 'partner' => null, 'groups' => []]))->toBe('feed')
            ->and($html)->toContain('data-open="profile-about"');
    });
});

describe('The title of a block', function () {
    test('is left to the page that shows it when it asks it so (an empty title): the profile keeps its banner, the search and the guide their content', function () {
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $profile = $view->block('profile', profile_grid(), ['name' => 'Ada.Love', 'lang' => 'en', 'title' => '']);
        $search = $view->block('search', profile_grid(), ['lang' => 'en', 'title' => '']);
        // A guide that has destinations: one with none shows nothing at all
        $dir = sys_get_temp_dir() . '/helpers-title-guide-' . bin2hex(random_bytes(4));
        mkdir($dir, 0o755, true);
        file_put_contents("$dir/Robust.ini", "[GridInfoService]\nlogin = \"http://grid.example.org:8002/\"\n");
        file_put_contents("$dir/Helpers.ini", "[Helpers]\nGuideSource = \"" . dirname(__DIR__) . "/fixtures/guide/one-category.txt\"\n");
        $guide = $view->block('destination_guide', Grid::at(helpers_environment(), $dir), ['lang' => 'en', 'title' => '']);

        expect($profile)->toContain('<header class="profile-banner">', '<dt>Born</dt>')->not->toContain('<h1', 'Ada Love</h')
            ->and($search)->toContain('search-form')->not->toContain('class="block-title"')
            ->and($guide)->toContain('id="guide"')->not->toContain('<h1');
    });
});
