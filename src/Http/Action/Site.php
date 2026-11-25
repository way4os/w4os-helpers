<?php

declare(strict_types=1);

namespace W4OS\Helpers\Http\Action;

use W4OS\Helpers\Block\Blocks;
use W4OS\Helpers\I18n;
use W4OS\Helpers\View\View;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Security\ApiKeys;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Whatever is asked of the helpers under their base: first the services of the simulators and the viewers (the engine's, see Engine),
 * then the pages whose address the grid puts under the base (`search`, `welcome`, `guide`, `GridStatus`... of its config), the home
 * page, the blocks alone. A page the grid puts elsewhere is the site's: asked at its default path here, it is a redirect there.
 */
final class Site
{
    /** The page of each address the grid gives (the key of its service), by the name of the page. */
    private const PAGES = [
        'search' => 'search',
        'welcome' => 'splash',
        'guide' => 'destination_guide',
        'grid-status' => 'grid_status',
        'grid-status-rss' => 'grid_status_rss',
        'web-profile' => 'profile',
    ];

    /** The default path of a page under the base (Services::PAGES), where it is served when the grid gives it no address. */
    private const DEFAULTS = [
        'search' => 'search',
        'welcome' => 'welcome',
        'guide' => 'guide',
        'grid-status' => 'grid_status',
        'web-profile' => 'profile',
    ];

    public function __construct(private View $view, private Engine $engine) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // The language of the visitor: the one a page asks (?lang=fr), else the one of the browser
        I18n::use((string) ($request->getQueryParams()['lang'] ?? $request->getHeaderLine('Accept-Language')));
        $answer = $this->engine->answer($request);
        if ($answer !== null) {
            return $answer;
        }
        /** @var Grid $grid */
        $grid = $request->getAttribute('grid');
        $path = '/' . trim($request->getUri()->getPath(), '/');
        if ($path === '/get_grid_info') {
            return (new GridInfo())($request, $response);
        }
        if (preg_match('#^/assets/(.+)$#', $path, $m) === 1) {
            return $this->asset($response, $grid, $m[1]);
        }
        // A tile of the map of the grid (the block map), as the engine gives it (Grid::mapTile())
        if (preg_match('#^/map/(\d+)/(-?\d+)/(-?\d+)\.jpg$#', $path, $m) === 1) {
            return $this->tile($response, $grid, (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('#^/blocks/([\w-]+)$#', $path, $m) === 1) {
            // Where the block is shown: a site gives the class of its visitor (`client`) or its user agent (`agent`), else the one of
            // the request
            $query = $request->getQueryParams();
            $query['agent'] ??= $request->getHeaderLine('User-Agent');

            return $this->block($response, $grid, $m[1], $query);
        }
        // A page the grid sets under the base of the helpers; any other path is nobody's here, not even the one a page of the site
        // would have under the helpers (an address the grid does not set is no address of the grid)
        $page = $this->page($grid, $path);
        if ($page === null) {
            return self::notProvided($response, (string) ($request->getAttribute('asked') ?? $path), $grid, (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));
        }
        if ($page === 'grid_status_rss') {
            return GridStatus::respondRss($response, $grid);
        }
        // A viewer takes one line of text from the status
        $query = $request->getQueryParams();
        if ($page === 'grid_status' && (($query['format'] ?? '') === 'text' || str_contains($request->getHeaderLine('Accept'), 'text/plain'))) {
            return GridStatus::respondText($response, $grid);
        }
        // The health is for who manages the grid: the keys of the environment of the grid served (the application's, never the install
        // of the server)
        if ($page === 'health') {
            if (!ApiKeys::for($grid->environment())->allows(ApiKeys::token($request->getHeaderLine('Authorization')))) {
                return $response->withStatus(401)->withHeader('WWW-Authenticate', 'Bearer');
            }
        }
        // The name of a profile follows the address of the profiles: /profile/First.Last
        if ($page === 'profile') {
            $query['name'] = rawurldecode(substr($path, (int) strrpos($path, '/') + 1));
            // Its stream for a reader: a JSON Feed, an RSS feed
            $format = (string) ($query['format'] ?? '');
            if (in_array($format, ['feed', 'rss'], true)) {
                $profile = \W4OS\Helpers\Block\Blocks::data('profile', $grid, ['feed_only' => 'true'] + $query);
                $url = (string) $request->getUri()->withQuery('')->withFragment('');
                if (!($profile['found'] ?? false)) {
                    return $response->withStatus(404);
                }
                $response->getBody()->write($format === 'rss'
                    ? \W4OS\Helpers\Block\ProfileFeed::rss($profile, $url)
                    : (string) json_encode(\W4OS\Helpers\Block\ProfileFeed::json($profile, $url), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', $format === 'rss' ? 'application/rss+xml; charset=utf-8' : 'application/feed+json; charset=utf-8');
            }
        }
        // The topic of the search is in the path: /search/places/
        if ($page === 'search' && preg_match('#/([a-z]+)$#', $path, $m) === 1) {
            $query['topic'] ??= $m[1];
        }
        $html = $this->view->page($page, $grid, $request->getHeaderLine('User-Agent'), $query);
        $response->getBody()->write($html);

        // Unavailable for now (a database of the grid that cannot be reached) is no missing page
        $status = $this->view->unavailable() ? 503 : ($this->view->found() ? 200 : 404);

        return $response->withStatus($status)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** The scripts the viewers and the simulators call in XML-RPC, which read an answer in that format: an error as they read it. */
    private const XMLRPC = ['currency.php', 'landtool.php', 'query.php'];

    /** How long a refusal is logged once for a caller and a path: a day. */
    private const LOGGED = 86400;

    /**
     * The answer to what the helpers do not provide here (the grid gives it another address, or none): a plain 404 Not found in the
     * language of the visitor, a fault of XML-RPC for what a viewer or a simulator reads so (the currency, the land tool, the
     * search), which the simulator logs and never says to the avatar; for those the log of the server says why, once a day for each
     * caller and the address asked. A page is the access log's alone.
     */
    private static function notProvided(ResponseInterface $response, string $path, Grid $grid, string $caller): ResponseInterface
    {
        $text = '404 ' . (\W4OS\Engine\I18n\Catalog::messages(I18n::current()) + \W4OS\Engine\I18n\Catalog::MESSAGES)['error_not_found'];
        // A service a simulator or a viewer calls here is said in the log (a setting to fix); a page is the access log's alone
        if (in_array(basename($path), self::XMLRPC, true)) {
            self::logOnce($grid, $caller, $path, "$path is not provided here: the grid gives it another address, or none.");
            $response->getBody()->write(\W4OS\Engine\Services\XmlRpc::fault(404, $text));

            return $response->withHeader('Content-Type', 'text/xml; charset=utf-8');
        }
        $response->getBody()->write($text . "\n");

        return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /** A refusal in the log of the web server, once a day for each caller and path (the engine's cache of the grid keeps when). */
    private static function logOnce(Grid $grid, string $caller, string $path, string $reason): void
    {
        $key = 'helpers-not-provided:' . sha1($grid->name() . "|$caller|$path");
        try {
            $cache = \W4OS\Engine\Data\Cache::for($grid->environment(), $grid->config());
            if ($cache->has($key)) {
                return;
            }
            $cache->set($key, time(), self::LOGGED);
        } catch (\Throwable) {
            // Without a cache, logged each time rather than never
        }
        error_log("w4os-helpers: $reason (asked by " . ($caller !== '' ? $caller : 'an unknown caller') . ')');
    }

    /**
     * The page a path under the base of the helpers asks: the home page, the health, the directory, or a page whose address the grid
     * puts under the base. A page the grid puts elsewhere (on the site), or does not set, is not the helpers'.
     */
    private function page(Grid $grid, string $path): ?string
    {
        if ($path === '/') {
            return 'home';
        }
        if ($path === '/health') {
            return 'health';
        }
        if ($path === '/directory_info') {
            return 'directory_info';
        }
        foreach (self::PAGES as $service => $page) {
            // Its address under the base, else its default path when the grid gives it none (the menu links it there)
            $address = $this->local($grid, $service);
            $address ??= isset(self::DEFAULTS[$service]) && trim((string) $grid->address($service)) === ''
                ? \W4OS\Engine\Provisioning\Web\Services::PAGES[self::DEFAULTS[$service]]
                : null;
            if ($address !== null && $address !== '/' && self::matches($path, $address, $page)) {
                return $page;
            }
        }

        return null;
    }

    /** The path of the address of a page under the base of the helpers (without the base), null when the grid puts it elsewhere. */
    private function local(Grid $grid, string $service): ?string
    {
        $address = (string) $grid->address($service);
        if ($address === '' || !$grid->servedByHelpers($address)) {
            return null;
        }
        $prefix = rtrim((string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH), '/');
        // The address of a profile has the name in it, which the path adds
        $path = (string) preg_replace('#/?\[AGENT_NAME\].*$#', '', (string) parse_url($address, PHP_URL_PATH));

        return '/' . trim(substr($path, strlen($prefix)), '/');
    }

    private static function matches(string $path, string $address, string $page): bool
    {
        return $path === $address || (in_array($page, ['profile', 'search'], true) && str_starts_with($path, "$address/"));
    }

    /**
     * @param array<string,mixed> $query the arguments of the block; `format=json` gives its data
     */
    private function block(ResponseInterface $response, Grid $grid, string $name, array $query): ResponseInterface
    {
        $name = Blocks::name($name);
        if ($name === null) {
            return $response->withStatus(404);
        }
        if (($query['format'] ?? '') === 'json') {
            $data = Blocks::data($name, $grid, $query);
            $response->getBody()->write((string) json_encode($data));

            return $response->withStatus(empty($data['unavailable']) ? 200 : 503)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write($this->view->block($name, $grid, $query));
        // Unavailable for now (a database of the grid that cannot be reached): an error the site that shows it can tell
        $response = $response->withStatus($this->view->unavailable() ? 503 : 200)->withHeader('Content-Type', 'text/html; charset=utf-8');
        // The notices of the block, for the zone of messages of the site that shows it
        $notices = View::notices();

        return $notices === [] ? $response : $response->withHeader('X-Helpers-Notices', (string) json_encode($notices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private const MIME = [
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

    /** A file of the assets: the theme of the grid first, then the helpers'. */
    /** A tile of the map of the grid (zoom 1 to 8, the region of its bottom left corner), a 404 when the grid has no map. */
    private function tile(ResponseInterface $response, Grid $grid, int $zoom, int $x, int $y): ResponseInterface
    {
        $jpeg = $zoom >= 1 && $zoom <= 8 ? $grid->mapTile($zoom, $x, $y) : null;
        if ($jpeg === null) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($jpeg);

        return $response->withHeader('Content-Type', 'image/jpeg')->withHeader('Cache-Control', 'public, max-age=86400');
    }

    private function asset(ResponseInterface $response, Grid $grid, string $file): ResponseInterface
    {
        $path = $this->view->asset($grid, $file);
        $mime = self::MIME[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
        if ($path === null || $mime === null) {
            return $response->withStatus(404);
        }
        $response->getBody()->write((string) file_get_contents($path));

        return $response->withHeader('Content-Type', $mime)->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
