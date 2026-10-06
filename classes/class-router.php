<?php
/**
 * Router: the single entry of the helpers. It reads the URL asked and decides which script, page, block or static file
 * answers.
 *
 * A web server that sends every URL it has no file for to index.php can serve any URL with the helpers: the
 * services at the paths the operator wants (`/search`, `/guide`...), the pages (`/`, `/welcome`) and the
 * scripts under any prefix (`/helpers/query.php`).
 *
 * What answers, by kind:
 * - the scripts are the API (query.php, register.php, currency.php...), at the root of the helpers under their well known
 *   names, and the pages that keep theirs (guide.php, directory_info.php, in pages/);
 * - the pages are all made by the same template, with a main content by page (`@home`, `@splash`, see OpenSim_Helpers_View);
 * - the blocks are pieces of pages, served alone as fragments to embed (`<prefix>/blocks/grid-status`);
 * - the files of the pages (css, js, images, fonts) are in assets/, served at `<prefix>/assets/...`.
 *
 * The routes of the operator are the constant OPENSIM_ROUTES: URL path => script (`query.php`) or page (`@home`,
 * `@splash`, `@profile`, `@health`). The defaults are `/` for the home page, `/welcome` for the splash page, `/profile` for
 * the profiles (and `/profile/<first>.<last>`, `/profile/<first>-<last>`, `/@<first>.<last>`) and `/health` for the health page.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
require_once __DIR__ . '/class-view.php';
require_once __DIR__ . '/class-blocks.php';

class OpenSim_Helpers_Router
{
    /** The scripts that answer requests: the name in the URL => the file, from the root of the helpers. */
    const SCRIPTS = [
        'query.php' => 'query.php',
        'register.php' => 'register.php',
        'offline.php' => 'offline.php',
        'currency.php' => 'currency.php',
        'landtool.php' => 'landtool.php',
        'parser.php' => 'parser.php',
        'eventsparser.php' => 'eventsparser.php',
        'motd.php' => 'motd.php',
        'textgen.php' => 'textgen.php',
        'guide.php' => 'pages/guide.php',
        'directory_info.php' => 'pages/directory_info.php',
    ];

    /** The pages: the slug of what each shows (see OpenSim_Helpers_View::main). */
    const PAGES = ['home', 'splash', 'profile', 'health'];

    const MIME = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    /**
     * The routes: the defaults, then those of the operator.
     *
     * @param  array<string,string> $own
     * @return array<string,string>
     */
    public static function routes($own = [])
    {
        $routes = ['/' => '@home', '/welcome' => '@splash', '/profile' => '@profile', '/health' => '@health'];
        foreach ($own as $path => $target) {
            $routes[self::normalize($path)] = $target;
        }

        return $routes;
    }

    /** A path without trailing slash, the root being `/`. */
    public static function normalize($path)
    {
        return '/' . trim((string) $path, '/');
    }

    /**
     * What answers a path.
     *
     * @param  string               $path
     * @param  array<string,string> $own the routes of the operator
     * @return array{0:string,1:string,2?:array<string,string>}|null [kind, what, arguments]: script (name), page (slug),
     *                                          block (name), asset (path under assets/); a profile gives its `name`
     */
    public static function match($path, $own = [])
    {
        $path = self::normalize(rawurldecode((string) $path));
        if (strpos($path, '..') !== false) {
            return null;
        }
        $routes = self::routes($own);
        if (isset($routes[$path])) {
            $target = $routes[$path];
            if ($target[0] === '@' && in_array(substr($target, 1), self::PAGES, true)) {
                return ['page', substr($target, 1)];
            }
            if (isset(self::SCRIPTS[$target])) {
                return ['script', $target];
            }

            return null;
        }
        // A profile: <profile>/<first>.<last>, <profile>/<first>-<last>, and /@<first>.<last> (a name of another grid has @grid)
        $name = '([A-Za-z0-9_]+)[.-]([A-Za-z0-9_]+)(@[A-Za-z0-9._:-]+)?';
        foreach ($routes as $route => $target) {
            if ($target === '@profile' && preg_match('#^' . preg_quote($route === '/' ? '' : $route, '#') . "/$name$#", $path, $m)) {
                return ['page', 'profile', ['name' => "$m[1].$m[2]" . ($m[3] ?? '')]];
            }
        }
        if (preg_match("#^/@$name$#", $path, $m)) {
            return ['page', 'profile', ['name' => "$m[1].$m[2]" . ($m[3] ?? '')]];
        }
        // The assets and the blocks, under any prefix
        if (preg_match('#^(?:/.*)?/assets/(.+)$#', $path, $m)) {
            return ['asset', $m[1]];
        }
        if (preg_match('#^(?:/.*)?/blocks/([a-z0-9-]+)$#', $path, $m) && OpenSim_Helpers_Blocks::exists($m[1])) {
            return ['block', $m[1]];
        }
        // A script by its name, under any prefix, with or without what OpenSimulator adds to it (offline.php/SaveMessage/)
        foreach (explode('/', $path) as $segment) {
            if (isset(self::SCRIPTS[$segment])) {
                return ['script', $segment];
            }
        }

        return null;
    }

    /**
     * What follows a script in a URL, as the web server gives it in PATH_INFO: /helpers/offline.php/SaveMessage/ gives
     * /SaveMessage/, empty when nothing follows.
     *
     * @param  string $path The path of the request, as it was asked (the slashes at its end count).
     * @return string
     */
    public static function pathInfo($path)
    {
        $scripts = implode('|', array_map(fn($script) => preg_quote($script, '#'), array_keys(self::SCRIPTS)));

        return preg_match("#/(?:$scripts)(/.*)$#", rawurldecode((string) $path), $found) ? $found[1] : '';
    }

    /**
     * Answer the current request: a page, a block or a static file is answered here, a script is given back for the entry
     * to include. It has to run in the global scope, as when the web server calls it: the scripts keep the database and the
     * settings in global variables ($SearchDB...) that their functions read.
     *
     * @return string|null The script to include, none when the request is answered.
     */
    public static function run()
    {
        $own = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $requested = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $found = self::match($requested, $own);
        if ($found === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Not found\n";

            return null;
        }
        [$kind, $what] = $found;
        // What the address says (the name in a profile) is what a query would say
        $_GET = ($found[2] ?? []) + $_GET;
        $root = dirname(__DIR__);
        switch ($kind) {
            case 'page':
                // The data of what the page shows, for what makes its own page or watches it
                if (($_GET['format'] ?? '') === 'json' && OpenSim_Helpers_Blocks::exists($what)) {
                    self::answer_block($what);

                    return null;
                }
                $html = OpenSim_Helpers_View::page($what);
                if ($html === null) {
                    http_response_code(404);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo "Not found\n";

                    return null;
                }
                header('Content-Type: text/html; charset=utf-8');
                echo $html;

                return null;
            case 'block':
                self::answer_block($what);

                return null;
            case 'asset':
                $file = "$root/assets/$what";
                $type = self::MIME[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
                if ($type === null || !is_file($file)) {
                    http_response_code(404);

                    return null;
                }
                header("Content-Type: $type");
                header('Cache-Control: public, max-age=86400');
                readfile($file);

                return null;
            default:
                // The scripts that can also be included (guide.php) know they are the answer
                defined('OPENSIM_ROUTED') || define('OPENSIM_ROUTED', true);
                // The scripts expect the root folder of the helpers as the working one
                chdir($root);
                $_SERVER['SCRIPT_NAME'] = '/' . $what;
                $_SERVER['SCRIPT_FILENAME'] = "$root/" . self::SCRIPTS[$what];
                $_SERVER['PHP_SELF'] = '/' . $what;
                // As the web server gives what follows the script (offline.php/SaveMessage/), which offline.php reads
                if (($info = self::pathInfo($requested)) !== '') {
                    $_SERVER['PATH_INFO'] = $info;
                }

                return $root . '/' . self::SCRIPTS[$what];
        }
    }

    /**
     * A block alone: the fragment of HTML, or its data with `?format=json`. The arguments of the query go to the block
     * (title, level, what the block takes).
     */
    private static function answer_block($name)
    {
        $args = array_diff_key($_GET, ['format' => 1, 'viewer' => 1, 'l' => 1]);
        if (($_GET['format'] ?? '') === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(OpenSim_Helpers_Blocks::data($name, $args), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo OpenSim_Helpers_Blocks::render($name, $args);
    }
}
