<?php
/**
 * guide.php: the destinations of a file, in both formats, as the page of the viewer shows them. The script outputs when it
 * is loaded, so each case runs it in its own process, with the file under test as the source of the grid.
 */

const GUIDE_FILE = <<<'TXT'
# a comment
// another comment
Places to see
Welcome Plaza|testgrid.example:8002:Welcome Plaza/128/128/22
Old Format|ignored|ignored|http://other.example:8002|Old Region/10/20/30
<b>Bold</b> & "quotes"|http://x.example:8002:Odd Place/1/2/3

Shops
Mall|http://shop.example:8002:Mall/1/2/3
TXT;

/**
 * The page the guide gives for a file and a query string.
 *
 * @param array<string,string> $query
 */
function guide_page(string $content, array $query = []): string
{
    $file = tempnam(sys_get_temp_dir(), 'guide');
    file_put_contents($file, $content);
    $root = dirname(__DIR__, 2);
    $script =
        "require '$root/vendor/autoload.php'; define('OPENSIM_ROUTED', true); define('OPENSIM_GUIDE_SOURCE', " .
        var_export($file, true) .
        ');' .
        '$_SERVER = ["HTTP_HOST" => "grid.example", "REQUEST_URI" => "/helpers/guide.php", "DOCUMENT_ROOT" => "/var/www"];' .
        '$_GET = ' .
        var_export($query, true) .
        ";require '$root/pages/guide.php';";
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = (string) stream_get_contents($pipes[1]);
    proc_close($process);
    unlink($file);

    return $out;
}

describe('The destination guide', function () {
    test('lists the categories of the file, with their number of destinations', function () {
        $page = guide_page(GUIDE_FILE);

        expect($page)
            ->toContain('<!DOCTYPE html>')
            ->toContain('Places to see')
            ->toContain('3 destinations')
            ->toContain('Shops')
            ->toContain('1 destinations')
            ->not->toContain('a comment')
            ->not->toContain('Welcome Plaza');
    });

    test('lists the destinations of a category, as links to follow', function () {
        $page = guide_page(GUIDE_FILE, ['category' => 'Places to see']);

        expect($page)
            ->toContain('href="secondlife://testgrid.example:8002%20Welcome%20Plaza/128/128/22"')
            ->toContain('Back to categories')
            ->not->toContain('Mall');
    });

    test('reads the older format, the grid and the region in the 4th and 5th columns', function () {
        expect(guide_page(GUIDE_FILE, ['category' => 'Places to see']))->toContain(
            'href="secondlife://other.example:8002%20Old%20Region/10/20/30"',
        );
    });

    test('shows what the file holds as text, not as markup', function () {
        $page = guide_page(GUIDE_FILE, ['category' => 'Places to see']);

        expect($page)
            ->toContain('&lt;b&gt;Bold&lt;/b&gt; &amp; &quot;quotes&quot;')
            ->not->toContain('<b>Bold</b>');
    });

    test('gives the categories for a category that is not there', function () {
        expect(guide_page(GUIDE_FILE, ['category' => 'Nowhere']))->toContain('3 destinations');
    });

    test('opens the only category of a file, with no way back', function () {
        $page = guide_page("Only category\nSingle|http://one.example:8002:One/1/2/3\n");

        expect($page)->toContain('Single')->not->toContain('Back to categories');
    });

    test('says there is nothing to show when the file holds no destination, and why to the one who reads the source', function () {
        expect(guide_page("# nothing here\n\n"))
            ->toContain('realm of destinations')
            ->toContain('<!-- no destination: guide_source');
    });
});
