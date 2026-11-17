<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Http\Action;

use OpenSim\Config\Layout;
use OpenSim\Helpers\Block\Blocks;
use OpenSim\Helpers\I18n;
use OpenSim\Helpers\View\View;
use OpenSim\Models\Grid;
use OpenSim\Runtime\Environment;
use OpenSim\Security\ApiKeys;
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
        if (preg_match('#^/blocks/([\w-]+)$#', $path, $m) === 1) {
            return $this->block($response, $grid, $m[1], $request->getQueryParams());
        }
        $page = $this->page($grid, $path);
        if ($page === null) {
            // A page the site serves, asked at the helpers' default path: there
            $to = $this->redirect($grid, $path, $request->getUri()->getQuery());

            return $to === null ? $response->withStatus(404) : $response->withStatus(302)->withHeader('Location', $to);
        }
        if ($page === 'grid_status_rss') {
            return GridStatus::respondRss($response, $grid);
        }
        // A viewer takes one line of text from the status
        $query = $request->getQueryParams();
        if ($page === 'grid_status' && (($query['format'] ?? '') === 'text' || str_contains($request->getHeaderLine('Accept'), 'text/plain'))) {
            return GridStatus::respondText($response, $grid);
        }
        // The health is for who manages the grid
        if ($page === 'health') {
            $environment = Environment::system(Layout::load());
            if (!ApiKeys::for($environment)->allows(ApiKeys::token($request->getHeaderLine('Authorization')))) {
                return $response->withStatus(401)->withHeader('WWW-Authenticate', 'Bearer');
            }
        }
        // The name of a profile follows the address of the profiles: /profile/First.Last
        if ($page === 'profile') {
            $query['name'] = rawurldecode(substr($path, (int) strrpos($path, '/') + 1));
            // The Feed tab of a viewer opens the page of the profile: its stream alone, the viewer shows the rest itself
            View::isViewer($request->getHeaderLine('User-Agent')) && ($query['feed_only'] = 'true');
            // Its stream for a reader: a JSON Feed, an RSS feed
            $format = (string) ($query['format'] ?? '');
            if (in_array($format, ['feed', 'rss'], true)) {
                $profile = \OpenSim\Helpers\Block\Blocks::data('profile', $grid, ['feed_only' => 'true'] + $query);
                $url = (string) $request->getUri()->withQuery('')->withFragment('');
                if (!($profile['found'] ?? false)) {
                    return $response->withStatus(404);
                }
                $response->getBody()->write($format === 'rss'
                    ? \OpenSim\Helpers\Block\ProfileFeed::rss($profile, $url)
                    : (string) json_encode(\OpenSim\Helpers\Block\ProfileFeed::json($profile, $url), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return $response->withHeader('Content-Type', $format === 'rss' ? 'application/rss+xml; charset=utf-8' : 'application/feed+json; charset=utf-8');
            }
        }
        // The topic of the search is in the path: /search/places/
        if ($page === 'search' && preg_match('#/([a-z]+)$#', $path, $m) === 1) {
            $query['topic'] ??= $m[1];
        }
        $html = $this->view->page($page, $grid, $request->getHeaderLine('User-Agent'), $query);
        $response->getBody()->write($html);

        return $response->withStatus($this->view->found() ? 200 : 404)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** The path of each page under the base of the helpers when the grid gives none. */
    private const DEFAULT_PATHS = [
        'search' => ['/search'],
        'welcome' => ['/welcome'],
        'guide' => ['/guide', '/guide.php'],
        'grid-status' => ['/grid_status'],
        'grid-status-rss' => ['/grid_status.rss'],
        'web-profile' => ['/profile'],
    ];

    /**
     * The page a path under the base of the helpers asks: the home page, the health, the directory, or a page whose address the grid
     * puts under the base. A page the grid puts elsewhere (on the site) is not the helpers' (see redirect()).
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
            $address = $this->local($grid, $service);
            if ($address !== null && $address !== '/' && self::matches($path, $address, $page)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Where a page asked at its default path under the base of the helpers is, when the grid gives it an address elsewhere (the
     * site serves it): that address, the rest of the path (a name, a topic) and the query kept. Null otherwise.
     */
    private function redirect(Grid $grid, string $path, string $query): ?string
    {
        foreach (self::DEFAULT_PATHS as $service => $defaults) {
            $address = (string) $grid->address($service);
            if ($address === '' || $this->local($grid, $service) !== null) {
                continue;
            }
            foreach ($defaults as $default) {
                if ($path === $default || str_starts_with($path, "$default/")) {
                    $rest = substr($path, strlen($default));
                    $target = str_contains($address, '[AGENT_NAME]')
                        ? str_replace('[AGENT_NAME]', ltrim($rest, '/'), $address)
                        : rtrim($address, '/') . $rest;

                    return $target . ($query !== '' ? (str_contains($target, '?') ? '&' : '?') . $query : '');
                }
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
            $response->getBody()->write((string) json_encode(Blocks::data($name, $grid, $query)));

            return $response->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write($this->view->block($name, $grid, $query));
        $response = $response->withHeader('Content-Type', 'text/html; charset=utf-8');
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
