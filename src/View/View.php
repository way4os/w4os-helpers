<?php

declare(strict_types=1);

namespace W4OS\Helpers\View;

use W4OS\Helpers\Block\Blocks;
use W4OS\Helpers\Block\ProfileBlock;
use W4OS\Helpers\I18n;
use W4OS\Engine\Hypergrid\Destination;
use W4OS\Engine\Models\Grid;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;
use W4OS\Views\ItemView;
use W4OS\Views\ListDetailView;
use W4OS\Views\ListHtml;
use W4OS\Views\TeleportLink;

/** Renders a page inside the layout; a viewer (the one that shows the splash page) gets the content alone. */
final class View
{

    private Twig $twig;
    private FilesystemLoader $loader;
    private bool $unavailable = false;

    private bool $found = true;

    /** @var array{css:list<string>,js:list<string>} the files the blocks shown need, for the head of the layout */
    private array $files = ['css' => [], 'js' => []];

    /** The prefix of the files of w4os-views among the assets the helpers serve (`views/js/tp-link.js`). */
    public const VIEWS = 'views/';

    /** The class of the visitor the block being made is for (Destination::client()), for its teleport links. */
    private string $client = 'browser';

    /**
     * The files each block needs, under the assets of the helpers: the common ones (`css`, `js`: what it is made of), then the ones of
     * the client it is shown to alone (`web` for a browser, `viewer` for a viewer: their own choices, styles and scripts).
     */
    private const BLOCK_FILES = [
        'destination_guide' => ['css' => ['css/blocks.css', 'css/guide.css'], 'web' => ['css' => ['css/guide.web.css']], 'viewer' => ['css' => ['css/guide.viewer.css']], 'js' => ['js/guide.js']],
        'popular_places' => ['css' => ['css/blocks.css', 'css/places.css'], 'js' => []],
        'profile' => ['css' => ['css/profile.css'], 'js' => []],
        'search' => ['css' => ['css/blocks.css', 'css/search.css', 'css/places.css'], 'web' => ['css' => ['css/search.web.css']], 'js' => ['js/search.js']],
        // The map in a browser (Leaflet); a viewer has its own world map: the list of the regions there
        'map' => ['css' => ['css/map.css'], 'web' => ['css' => [self::LEAFLET . 'leaflet.css', self::TOM_SELECT . 'css/tom-select.min.css', 'css/map.web.css'], 'js' => [self::LEAFLET . 'leaflet.js', self::TOM_SELECT . 'js/tom-select.popular.min.js', 'js/map.js']], 'viewer' => ['css' => ['css/map.viewer.css']], 'js' => []],
    ];

    /** The prefix of the files of Leaflet among the assets the helpers serve (`lib/leaflet/leaflet.js`): the ones of LIBRARIES. */
    public const LEAFLET = 'lib/leaflet/';

    /** The prefix of the files of Tom Select (the region picker of the map): the ones of LIBRARIES. */
    public const TOM_SELECT = 'lib/tom-select/';

    /**
     * The libraries of the browser the helpers serve, by the prefix of their files among the assets: the package (npm releases pinned
     * in composer.json) and its folder of the files served. A site that installs the helpers serves them from its own vendor.
     */
    public const LIBRARIES = [
        self::LEAFLET => ['package' => 'npm-asset/leaflet', 'folder' => 'dist/'],
        self::TOM_SELECT => ['package' => 'npm-asset/tom-select', 'folder' => 'dist/'],
    ];

    /** The layer of the styles of each client (Destination::CLIENTS): a browser has the web's, a viewer the viewer's, never both. */
    public const LAYERS = ['browser' => 'web', 'viewer' => 'viewer'];

    /**
     * The files a block needs, for a site that shows the block in its own pages (and links them from where it has the helpers): its
     * common styles, then the ones of the client (`browser`, `viewer`) it is shown to.
     *
     * @return array{css:list<string>,js:list<string>} paths under the assets of the helpers (`assets/`)
     */
    public static function blockFiles(string $name, string $client = 'browser'): array
    {
        $files = self::BLOCK_FILES[str_replace('-', '_', $name)] ?? [];
        $layer = $files[self::LAYERS[$client] ?? 'web'] ?? [];

        return ['css' => array_merge($files['css'] ?? [], $layer['css'] ?? []), 'js' => array_merge($files['js'] ?? [], $layer['js'] ?? [])];
    }

    /**
     * The styles of a page of the helpers: the common ones, then the ones of its client (`browser`, `viewer`).
     *
     * @return list<string> paths under the assets of the helpers (`assets/`)
     */
    public static function pageStyles(string $client): array
    {
        return ['css/page.css', 'css/page.' . (self::LAYERS[$client] ?? 'web') . '.css'];
    }

    /** The levels of the notices to the visitor, as the sites name them too (Grav's messages). */
    public const NOTICE_LEVELS = ['info', 'warning', 'error'];

    /** @var list<array{level:string,text:string}> the notices of this request, not shown yet */
    private static array $notices = [];

    /**
     * A notice to the visitor (a new viewer, an error, anything to say), at the start of the content of the page. Inside the page
     * of a site, the site shows it in its own zone of messages (notices()). The text is given in the language of the page.
     */
    public static function notify(string $level, string $text): void
    {
        in_array($level, self::NOTICE_LEVELS, true) || ($level = 'info');
        trim($text) === '' || (self::$notices[] = ['level' => $level, 'text' => $text]);
    }

    /**
     * The notices not shown yet, then none: for the page that shows them (the layout of the helpers, or a site).
     *
     * @return list<array{level:string,text:string}>
     */
    public static function notices(): array
    {
        [$notices, self::$notices] = [self::$notices, []];

        return $notices;
    }

    /** Whether what the last block shown is about exists (a profile that does not is a 404). */
    public function found(): bool
    {
        return $this->found;
    }

    /** Whether the last block shown could not be made for now (a database of the grid that cannot be reached: a 503). */
    public function unavailable(): bool
    {
        return $this->unavailable;
    }

    /** @var array{agent?:string} where the page being made is shown, for the links of its blocks (Links::to()) */
    private array $where = [];

    public function __construct(string $templates, private string $assets = '')
    {
        $this->assets = $assets !== '' ? $assets : dirname($templates) . '/assets';
        $this->loader = new FilesystemLoader($templates);
        $this->twig = new Twig($this->loader, ['autoescape' => 'html']);
        $this->twig->addFunction(new \Twig\TwigFunction('picture', Picture::tag(...), ['is_safe' => ['html']]));
        // The language of the text of the page, as it is rendered (I18n::use() of its request)
        $this->twig->addFunction(new \Twig\TwigFunction('page_lang', I18n::tag(...)));
        $this->twig->addFunction(new \Twig\TwigFunction('tp_link', $this->tpLink(...), ['is_safe' => ['html']]));
        $this->twig->addFunction(new \Twig\TwigFunction('list_detail', $this->listDetail(...), ['is_safe' => ['html']]));
    }

    /** A block as an HTML fragment. @param array<string,mixed> $args */
    public function block(string $name, Grid $grid, array $args = []): string
    {
        // A block a site makes in its own language (`lang`): the helpers are a library there, no request tells it
        isset($args['lang']) && I18n::use((string) $args['lang']);
        // The blocks of a page are shown where the page is (a viewer, its login screen)
        $args += $this->where;
        $this->client = in_array($args['client'] ?? null, Destination::CLIENTS, true)
            ? (string) $args['client']
            : Destination::client(isset($args['agent']) ? (string) $args['agent'] : null);
        $data = Blocks::data($name, $grid, $args);
        foreach (self::blockFiles($name, $this->client) as $kind => $files) {
            $this->files[$kind] = array_values(array_unique(array_merge($this->files[$kind], $files)));
        }
        $this->found = (($data['found'] ?? true) !== false);
        $this->unavailable = !empty($data['unavailable']);

        // The pictures of the grid: the address the site gives (`assets`), else the API of the helpers
        // Where the pictures are: the address of the web assets of the grid when the helpers serve them, else their /pictures
        $web = $grid->assetsUrl() ?? ($grid->helperUrl('assets') ?: null);
        $served = $web !== null && $grid->servedByHelpers($web) ? (string) parse_url($web, PHP_URL_PATH) : null;
        $assets = (string) ($args['assets'] ?? rtrim($served ?? rtrim((string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH), '/') . '/pictures', '/'));

        // Its name on its root (data-helpers-block), for a site, and the health, to find it in a page
        // A block with nothing to show shows nothing, its title neither
        $html = $data === null || $data === [] ? '' : $this->twig->render("components/$name-block.twig", $data + ['labels' => self::labels(), 'assets' => $assets, 'helpers_block' => $name]);
        // Its teleport links and its lists need the style and the script of w4os-views on the pages of the helpers (a site loads its own)
        foreach (['data-w4os-tp' => TeleportLink::ASSETS, 'data-w4os-list-detail' => ListDetailView::ASSETS + TeleportLink::ASSETS] as $mark => $assets) {
            if (str_contains($html, $mark)) {
                foreach ($assets as $kind => $files) {
                    $this->files[$kind] = array_values(array_unique([...$this->files[$kind], ...array_map(static fn(string $file): string => self::VIEWS . $file, $files)]));
                }
            }
        }

        return $html;
    }

    /**
     * A teleport link that fits where it is seen (W4OS\Views\TeleportLink, as the sites show them): the main link of the client of
     * the block, a menu of its other links and the address to copy; the templates call it as `tp_link(links, label, html)`.
     *
     * @param array{browser?:list<array{format:int,link:string,label:string}>,viewer?:list<array{format:int,link:string,label:string}>} $links Links::all()
     * @param string|\Stringable|null $html what the main link holds, markup already escaped (a card); its label as text without it
     */
    public function tpLink(array $links, string $label, string|\Stringable|null $html = null): string
    {
        $texts = \W4OS\Engine\I18n\Catalog::messages(I18n::current()) + \W4OS\Engine\I18n\Catalog::MESSAGES;
        $pattern = trim((string) preg_replace('#^/(.*)/[a-z]*$#s', '$1', Destination::VIEWER_PATTERN));

        return TeleportLink::html($links, $label, $this->client, ['more' => (string) ($texts['tp_more'] ?? ''), 'copied' => (string) ($texts['copied'] ?? '')], $pattern, $html === null ? null : (string) $html);
    }

    /**
     * A list with the detail of the result it selects (W4OS\Views\ListHtml, as the sites show it): its texts from the engine's
     * catalog in the language of the page, its pictures at the address of the block, the teleport links of the client of the block,
     * the actions of a result (`searchActions`); the templates call it as `list_detail(view, empty, assets)`.
     *
     * @param ?string $empty the key of what a list without results says, null for nothing
     */
    public function listDetail(?ListDetailView $view, ?string $empty, string $assets): string
    {
        if ($view === null) {
            return '';
        }
        $texts = \W4OS\Engine\I18n\Catalog::messages(I18n::current()) + \W4OS\Engine\I18n\Catalog::MESSAGES;
        $esc = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return ListHtml::listDetail($view, [
            't' => static fn(string $key, array $vars = []): string => vsprintf((string) ($texts[$key] ?? $key), $vars),
            'language' => I18n::current(),
            // Its dates as dates, never relative (an event is weeks ahead)
            'dates' => 'long',
            'empty' => $empty,
            // The rows the name and the time, as the viewers list them: the picture in the detail only
            'rowPictures' => false,
            // A place, a group: its picture or the image of its title, from the engine; a person: the silhouette without a picture
            'picture' => static fn(?string $picture, ?string $default, string $name): string => Picture::tag(rtrim($assets, '/') . '/' . ($picture ?? '00000000-0000-0000-0000-000000000000') . '.jpg', $default === 'person' ? 'person' : null, 'search-picture'),
            'actions' => static fn(ItemView $item): string => implode('', array_map(
                static fn(array $action): string => '<a class="search-action" href="' . $esc($action['url']) . '">' . $esc($action['label']) . '</a>',
                (array) ($item->item['searchActions'] ?? []),
            )),
            'client' => $this->client,
            'pattern' => trim((string) preg_replace('#^/(.*)/[a-z]*$#s', '$1', Destination::VIEWER_PATTERN)),
            'texts' => ['more' => (string) ($texts['tp_more'] ?? ''), 'copied' => (string) ($texts['copied'] ?? '')],
        ]);
    }

    /** @param array<string,mixed> $args the arguments of the request, for the block the page is */
    public function page(string $page, Grid $grid, string $agent, array $args = []): string
    {
        $theme = self::theme($grid);
        $theme === null || !is_dir("$theme/templates") || $this->loader->prependPath("$theme/templates");
        $this->where = ['agent' => $agent];
        $block = Blocks::name($page);
        $template = "pages/$page-page.twig";
        if ($this->loader->exists($template)) {
            // The blocks the template uses are variables of its name
            $source = $this->loader->getSourceContext($template)->getCode();
            $context = ['grid_name' => $grid->name()];
            // Each block with the arguments of the request: a category of the guide asked on the page that shows it
            foreach (array_keys(Blocks::BLOCKS) as $name) {
                $variable = str_replace('-', '_', $name);
                str_contains($source, "{{ $variable }}") &&
                    ($context[$variable] = new Markup($this->block($name, $grid, $args), 'UTF-8'));
            }
            // The blocks of the home page, in the order Helpers.ini gives (HomeBlocks)
            $page === 'home' && ($context['blocks'] = array_map(
                fn(string $name): Markup => new Markup($this->block($name, $grid, $args), 'UTF-8'),
                self::homeBlocks($grid),
            ));
            $main = $this->twig->render($template, $context);
        } else {
            $main = $block === null ? '' : $this->block($block, $grid, $args + ['level' => 'h1']);
        }

        // Whether the page has what it shows: the main block's answer, not the one of the blocks of the layout
        $found = $this->found;
        $viewer = Destination::isViewer($agent);
        $prefix = rtrim((string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH), '/');
        $config = $grid->config();
        $logo = $config?->find('Helpers', 'LogoURL');
        foreach (['svg', 'png'] as $ext) {
            $logo ??= $this->asset($grid, "images/logos/logo.$ext") === null ? null : "$prefix/assets/images/logos/logo.$ext";
        }
        // The styles of the page for its client, versioned by their date
        $stylesheets = array_map(function (string $file) use ($grid, $prefix): string {
            $found = $this->asset($grid, $file);

            return "$prefix/assets/$file" . ($found === null ? '' : '?v=' . filemtime($found));
        }, self::pageStyles($viewer ? 'viewer' : 'browser'));

        // What the grid is and says (its info, its status, its message of the day): none on a host without the database of a grid
        $sidebars = (!$viewer || $page === 'splash') && $grid->hasDatabase();
        $html = $this->twig->render('layouts/base-layout.twig', [
            'grid_name' => $grid->name(),
            'viewer' => $viewer,
            'slug' => $page,
            'main' => new Markup($main, 'UTF-8'),
            'sidebars' => $sidebars,
            'grid_info' => new Markup($sidebars ? $this->block('grid-info', $grid) : '', 'UTF-8'),
            'grid_status' => new Markup($sidebars ? $this->block('grid-status', $grid) : '', 'UTF-8'),
            'motd' => new Markup($sidebars ? $this->block('motd', $grid) : '', 'UTF-8'),
            'news' => '',
            'menu' => $this->menu($grid, $prefix),
            'labels' => self::labels(),
            'stylesheets' => $stylesheets,
            'assets' => array_map(
                fn(array $files): array => array_map(fn(string $file): string => "$prefix/assets/$file", $files),
                $this->files,
            ),
            'logo_url' => $logo,
            // The brand of the site (IconURL, AccentColor, Font, FontURL of [Helpers]): what is not said keeps the look of the helpers
            'brand' => $grid->brand(),
            // Last: what the blocks of the page said is in
            'notices' => self::notices(),
        ]);
        $this->found = $found;

        return $html;
    }

    /** The blocks of the home page when Helpers.ini says none (HomeBlocks): the search, then what is there to visit. */
    public const HOME_BLOCKS = ['search', 'popular_places', 'destination_guide'];

    /**
     * The blocks of the home page of a grid: `HomeBlocks` of `[Helpers]`, their names separated by commas, in their order (a name
     * that is no block left out); HOME_BLOCKS when it says none.
     *
     * @return list<string>
     */
    public static function homeBlocks(Grid $grid): array
    {
        $said = array_filter(array_map(static fn(string $name): ?string => Blocks::name(trim($name)), explode(',', trim((string) $grid->config()?->find('Helpers', 'HomeBlocks'), " \t\""))));

        return $said === [] ? self::HOME_BLOCKS : array_values(array_unique($said));
    }

    /** The folder of the theme of the grid, where what it holds replaces the files of the helpers (`templates/`, `assets/`). */
    public static function theme(Grid $grid): ?string
    {
        $dir = $grid->directory() . '/theme';

        return $grid->directory() !== '' && is_dir($dir) ? $dir : null;
    }

    /** The file of the assets a path names: the theme's, else the helpers'. Null when there is none. */
    public function asset(Grid $grid, string $file): ?string
    {
        if (str_contains($file, '..')) {
            return null;
        }
        // The files of a library (Leaflet), from its package
        foreach (self::LIBRARIES as $prefix => $library) {
            if (str_starts_with($file, $prefix)) {
                $root = \Composer\InstalledVersions::isInstalled($library['package']) ? \Composer\InstalledVersions::getInstallPath($library['package']) : null;
                $path = $root === null ? '' : rtrim($root, '/') . '/' . $library['folder'] . substr($file, strlen($prefix));

                return $path !== '' && is_file($path) ? $path : null;
            }
        }
        // The files of w4os-views (its teleport links), from its package
        if (str_starts_with($file, self::VIEWS)) {
            $path = dirname((string) (new \ReflectionClass(TeleportLink::class))->getFileName(), 2) . '/assets/' . substr($file, strlen(self::VIEWS));

            return is_file($path) ? $path : null;
        }
        foreach ([self::theme($grid) === null ? null : self::theme($grid) . '/assets', $this->assets] as $dir) {
            if ($dir !== null && is_file("$dir/$file")) {
                return "$dir/$file";
            }
        }

        return null;
    }

    /** @return list<array{slug:string,label:string,url:string}> the pages of the menu, at the address the grid gives them */
    private function menu(Grid $grid, string $prefix): array
    {
        $guide = (string) parse_url((string) $grid->address('guide'), PHP_URL_PATH);
        $items = [
            ['home', I18n::text('Home'), $prefix . '/'],
            ['destination_guide', I18n::text('Guide'), $guide !== '' ? $guide : $prefix . '/guide'],
            ['directory_info', I18n::text('Directory'), $prefix . '/directory_info'],
        ];

        return array_map(static fn(array $i): array => ['slug' => $i[0], 'label' => $i[1], 'url' => $i[2]], $items);
    }

    /**
     * The picks or the classifieds of a profile as its block shows them (components/profile-items.twig, ProfileBlock::item()), for a
     * site that shows them elsewhere (the form of a profile), in a language (`fr`, `pt-BR`).
     *
     * @param list<array<string,mixed>> $items what the engine gives of each (Avatar::picks(), classifieds(), or their API)
     * @param string $assets the address the pictures are under (`<assets>/<uuid>.jpg`)
     */
    public function items(string $kind, array $items, string $assets, string $lang = 'en'): string
    {
        I18n::use($lang);

        return $this->twig->render('components/profile-items.twig', [
            'items' => array_map(static fn(array $row): array => ProfileBlock::item($row), $items),
            'kind' => $kind === 'classifieds' ? 'classified' : 'pick',
            'assets' => rtrim($assets, '/'),
            'labels' => self::labels(),
        ]);
    }

    /** The default title of the blocks that are a whole page, by the key of the engine's catalog it is: a site may give its own. */
    public const BLOCK_TITLES = ['search' => 'search_title', 'profile' => 'profile_title'];

    /**
     * The default title of a block that is a whole page, in a language, without making the block: the one its heading shows (a site
     * takes it for its page and its menu, unless it gives its own); null for a block without one.
     */
    public static function blockTitle(string $name, string $lang): ?string
    {
        $key = self::BLOCK_TITLES[str_replace('-', '_', $name)] ?? null;

        return $key === null ? null : (\W4OS\Engine\I18n\Catalog::messages($lang)[$key] ?? \W4OS\Engine\I18n\Catalog::MESSAGES[$key]);
    }

    /** @return array<string,string> the words of the templates */
    public static function labels(): array
    {
        // The texts of the catalog of the engine in the language in use, the English ones where it has none
        $texts = \W4OS\Engine\I18n\Catalog::messages(\W4OS\Helpers\I18n::current()) + \W4OS\Engine\I18n\Catalog::MESSAGES;

        return [
            'skip' => I18n::text('Skip to content'),
            'powered_by' => 'w4os-helpers',
            'profile_not_found' => I18n::text('This profile does not exist.'),
            'unavailable' => I18n::text('This is temporarily unavailable. Please try again later.'),
            'member_since' => I18n::text('Member since'),
            'profile_private' => I18n::text('This profile is not public.'),
            'partner' => I18n::text('Partner'),
            'languages' => I18n::text('Languages'),
            'wants' => I18n::text('Wants to'),
            'skills' => I18n::text('Skills'),
            // The tabs of a profile: the texts of the engine, as every site has them
            'web' => $texts['profile_tab_web'],
            'feed' => $texts['profile_tab_feed'] ?? 'Feed',
            'real_life' => $texts['profile_tab_real_life'],
            'inworld' => $texts['profile_tab_inworld'],
            'about' => $texts['profile_tab_about'] ?? I18n::text('About'),
            'interests' => $texts['profile_tab_interests'],
            'profile_sections' => $texts['profile_sections'],
            'picks' => $texts['profile_tab_picks'],
            'classifieds' => $texts['profile_tab_classifieds'],
            // The in-world tab of a profile, as the viewer shows it
            'name' => $texts['profile_name'] ?? I18n::text('Name'),
            'key' => $texts['profile_key'] ?? I18n::text('Key'),
            'born' => $texts['born'] ?? I18n::text('Born'),
            'account' => $texts['profile_tab_account'] ?? I18n::text('Account'),
            'groups' => $texts['profile_groups'] ?? I18n::text('Groups'),
            // A section of a profile with nothing, and the details of a pick or a classified, as the viewer shows them
            'none' => $texts['profile_none'] ?? I18n::text('None.'),
            'no_description' => $texts['profile_no_description'] ?? I18n::text('No description given.'),
            'location' => $texts['profile_location'] ?? I18n::text('Location'),
            'teleport' => $texts['profile_teleport'] ?? I18n::text('Teleport'),
            'until' => $texts['profile_until'] ?? I18n::text('Until'),
        ];
    }
}
