<?php
/**
 * The destination guide: the destinations of a file in both formats and the board format, and the block that shows them, as
 * the page of a viewer or a browser has them.
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/classes/class-view.php';
require_once dirname(__DIR__, 2) . '/classes/class-blocks.php';

// An empty guide says why in the log: not in the output of the tests
ini_set('error_log', '/dev/null');

const GUIDE_FILE = <<<'TXT'
# a comment
// another comment
Places to see
Welcome Plaza|testgrid.example:8002:Welcome Plaza/128/128/22
Old Format|ignored|ignored|http://other.example:8002|Old Region/10/20/30
<b>Bold</b> & "quotes"|http://x.example:8002:Odd Place/1/2/3
Residences|testgrid.example:8002:Residences|128,128,30
|
-

Shops
Mall|http://shop.example:8002:Mall/1/2/3
TXT;

/** The page of the guide for a file and a query, as the block makes it. */
function guide_block(string $content, array $args = []): string
{
    $file = tempnam(sys_get_temp_dir(), 'guide');
    file_put_contents($file, $content);
    $html = OpenSim_Helpers_Blocks::render('guide', $args + ['source' => $file]);
    unlink($file);

    return $html;
}

describe('The destinations of a text', function () {
    test('are by category, in the order of the file', function () {
        $destinations = OpenSim_Helpers_Guide::parse(GUIDE_FILE);

        expect(array_keys($destinations))
            ->toBe(['Places to see', 'Shops'])
            ->and(array_column($destinations['Places to see'], 'name'))
            ->toBe(['Welcome Plaza', 'Old Format', '<b>Bold</b> & "quotes"', 'Residences']);
    });

    test('can be in the older format, the grid and the region in the 4th and 5th columns', function () {
        expect(OpenSim_Helpers_Guide::parse(GUIDE_FILE)['Places to see'][1]['url'])->toBe('http://other.example:8002/Old Region/10/20/30');
    });

    test('have the place in the region of a third column of numbers', function () {
        expect(OpenSim_Helpers_Guide::parse(GUIDE_FILE)['Places to see'][3]['url'])->toBe('testgrid.example:8002:Residences/128/128/30');
    });

    test('leave out the comments and the layout hints of a board (a dash, a pipe alone)', function () {
        $names = array_merge(...array_values(array_map(fn($list) => array_column($list, 'name'), OpenSim_Helpers_Guide::parse(GUIDE_FILE))));

        expect($names)->not->toContain('-')->not->toContain('a comment');
    });

    test('are read from a file, or a URL, or not at all', function () {
        $file = tempnam(sys_get_temp_dir(), 'guide');
        file_put_contents($file, 'Hello');

        expect(OpenSim_Helpers_Guide::read($file))
            ->toBe('Hello')
            ->and(OpenSim_Helpers_Guide::read('/does/not/exist.txt'))
            ->toBeNull()
            ->and(OpenSim_Helpers_Guide::read(null))
            ->toBeNull();
    });
});

describe('The guide block', function () {
    test('lists the categories of the file, with their number of destinations', function () {
        $html = guide_block(GUIDE_FILE);

        expect($html)
            ->toContain('id="guide"')
            ->toContain('Places to see')
            ->toContain('4 destinations')
            ->toContain('Shops')
            ->toContain('1 destinations')
            ->not->toContain('Welcome Plaza');
    });

    test('lists the destinations of a category, as links to follow, with the way back', function () {
        $html = guide_block(GUIDE_FILE, ['category' => 'Places to see']);

        expect($html)
            ->toContain('href="secondlife://testgrid.example:8002%20Welcome%20Plaza/128/128/22"')
            ->toContain('href="secondlife://other.example:8002%20Old%20Region/10/20/30"')
            ->toContain('Back to categories')
            ->toContain('<h2>Places to see</h2>')
            ->not->toContain('Mall');
    });

    test('shows what the file holds as text, not as markup', function () {
        $html = guide_block(GUIDE_FILE, ['category' => 'Places to see']);

        expect($html)->toContain('&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;quotes&quot;')->not->toContain('<b>Bold</b>');
    });

    test('gives the categories for a category that is not there', function () {
        expect(guide_block(GUIDE_FILE, ['category' => 'Nowhere']))->toContain('4 destinations');
    });

    test('opens the only category of a file, with no way back', function () {
        $html = guide_block("Only category\nSingle|http://one.example:8002:One/1/2/3\n");

        expect($html)->toContain('Only category')->toContain('Single')->not->toContain('Back to categories');
    });

    test('says there is nothing to show when the file holds no destination, and why to the one who reads the source', function () {
        expect(guide_block("# nothing here\n\n"))
            ->toContain('realm of destinations')
            ->toContain('<!-- no destination: guide_source');
    });

    test('links its style and its script from the head of the page, not from its own body', function () {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Firefox/130.0';
        $file = tempnam(sys_get_temp_dir(), 'guide');
        file_put_contents($file, GUIDE_FILE);
        $_GET = ['source' => $file];
        $html = OpenSim_Helpers_View::page('guide');
        $_GET = [];
        unlink($file);

        expect($html)
            ->toMatch('#<head>.*guide\.css\?v=\d+.*</head>#s')
            ->toMatch('#<script src="[^"]*guide\.js\?v=\d+"></script>\s*</body>#')
            ->toContain('class="topbar"')
            ->toContain('sidebar-left');
    });
});
