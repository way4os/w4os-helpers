<?php

declare(strict_types=1);

namespace OpenSim\Helpers\View;

use OpenSim\Config\GridConfig;
use OpenSim\Helpers\Block\Blocks;
use OpenSim\Helpers\I18n;
use OpenSim\Models\Grid;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;
use Twig\Markup;

/** Renders a page inside the layout; a viewer (the one that shows the splash page) gets the content alone. */
final class View
{
    public const VIEWER_PATTERN = '/SecondLife|OpenSim|Firestorm|Singularity|Kokua|Alchemy|Catznip/i';

    private Twig $twig;
    private FilesystemLoader $loader;
    private bool $found = true;

    /** @var array{css:list<string>,js:list<string>} the files the blocks shown need, for the head of the layout */
    private array $files = ['css' => [], 'js' => []];

    /** The files each block needs. */
    private const BLOCK_FILES = ['destination_guide' => ['css' => ['css/guide.css'], 'js' => ['js/guide.js']]];

    /** Whether what the last block shown is about exists (a profile that does not is a 404). */
    public function found(): bool
    {
        return $this->found;
    }

    public function __construct(string $templates, private string $assets = '')
    {
        $this->assets = $assets !== '' ? $assets : dirname($templates) . '/assets';
        $this->loader = new FilesystemLoader($templates);
        $this->twig = new Twig($this->loader, ['autoescape' => 'html']);
    }

    /** A block as an HTML fragment. @param array<string,mixed> $args */
    public function block(string $name, Grid $grid, array $args = []): string
    {
        // A block a site makes in its own language (`lang`): the helpers are a library there, no request tells it
        isset($args['lang']) && I18n::use((string) $args['lang']);
        $data = Blocks::data($name, $grid, $args);
        foreach (self::BLOCK_FILES[$name] ?? [] as $kind => $files) {
            $this->files[$kind] = array_values(array_unique(array_merge($this->files[$kind], $files)));
        }
        $this->found = (($data['found'] ?? true) !== false);

        // The pictures of the grid: the address the site gives (`assets`), else the API of the helpers
        $assets = (string) ($args['assets'] ?? rtrim((string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH), '/') . '/api/grids/' . rawurlencode($grid->id()) . '/assets');

        return $data === null ? '' : $this->twig->render("components/$name-block.twig", $data + ['labels' => self::labels(), 'assets' => $assets]);
    }

    /** @param array<string,mixed> $args the arguments of the request, for the block the page is */
    public function page(string $page, Grid $grid, string $agent, array $args = []): string
    {
        $theme = self::theme($grid);
        $theme === null || !is_dir("$theme/templates") || $this->loader->prependPath("$theme/templates");
        $block = Blocks::name($page);
        $template = "pages/$page-page.twig";
        if ($this->loader->exists($template)) {
            // The blocks the template uses are variables of its name
            $source = $this->loader->getSourceContext($template)->getCode();
            $context = ['grid_name' => $grid->name()];
            foreach (array_keys(Blocks::BLOCKS) as $name) {
                $variable = str_replace('-', '_', $name);
                str_contains($source, "{{ $variable }}") &&
                    ($context[$variable] = new Markup($this->block($name, $grid), 'UTF-8'));
            }
            $main = $this->twig->render($template, $context);
        } else {
            $main = $block === null ? '' : $this->block($block, $grid, $args + ['level' => 'h1']);
        }

        // Whether the page has what it shows: the main block's answer, not the one of the blocks of the layout
        $found = $this->found;
        $viewer = self::isViewer($agent);
        $prefix = rtrim((string) parse_url((string) $grid->helpersUrl(), PHP_URL_PATH), '/');
        $config = is_dir($grid->directory()) ? GridConfig::fromFolder($grid->directory(), ['Helpers' => 'Helpers.ini']) : null;
        $logo = $config?->find('Helpers', 'LogoURL');
        foreach (['svg', 'png'] as $ext) {
            $logo ??= $this->asset($grid, "images/logos/logo.$ext") === null ? null : "$prefix/assets/images/logos/logo.$ext";
        }
        $stylesheet = $this->asset($grid, 'css/page.css');

        $html = $this->twig->render('layouts/base-layout.twig', [
            'grid_name' => $grid->name(),
            'viewer' => $viewer,
            'slug' => $page,
            'main' => new Markup($main, 'UTF-8'),
            'sidebars' => !$viewer || $page === 'splash',
            'grid_info' => new Markup($this->block('grid-info', $grid), 'UTF-8'),
            'grid_status' => new Markup($this->block('grid-status', $grid), 'UTF-8'),
            'motd' => new Markup($this->block('motd', $grid), 'UTF-8'),
            'news' => '',
            'menu' => $this->menu($grid, $prefix),
            'labels' => self::labels(),
            'stylesheet' => "$prefix/assets/css/page.css" . ($stylesheet === null ? '' : '?v=' . filemtime($stylesheet)),
            'assets' => array_map(
                fn(array $files): array => array_map(fn(string $file): string => "$prefix/assets/$file", $files),
                $this->files,
            ),
            'logo_url' => $logo,
        ]);
        $this->found = $found;

        return $html;
    }

    /** Whether a request comes from a viewer (its user agent): a page then shows its content alone. For the sites too. */
    public static function isViewer(string $userAgent): bool
    {
        return preg_match(self::VIEWER_PATTERN, $userAgent) === 1;
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
            ['home', _('Home'), $prefix . '/'],
            ['destination_guide', _('Guide'), $guide !== '' ? $guide : $prefix . '/guide'],
            ['directory_info', _('Directory'), $prefix . '/directory_info'],
        ];

        return array_map(static fn(array $i): array => ['slug' => $i[0], 'label' => $i[1], 'url' => $i[2]], $items);
    }

    /** @return array<string,string> the words of the templates */
    public static function labels(): array
    {
        // The texts of the catalog of the engine in the language in use, the English ones where it has none
        $texts = \OpenSim\I18n\Catalog::messages(\OpenSim\Helpers\I18n::current()) + \OpenSim\I18n\Catalog::MESSAGES;

        return [
            'skip' => _('Skip to content'),
            'powered_by' => 'opensim-helpers',
            'search' => _('Search'),
            'search_for' => _('Search for'),
            'search_all' => _('All'),
            'search_nothing' => _('Nothing found.'),
            'search_empty' => _('Type something to look for.'),
            'profile_not_found' => _('This profile does not exist.'),
            'member_since' => _('Member since'),
            'profile_private' => _('This profile is not public.'),
            'partner' => _('Partner'),
            'languages' => _('Languages'),
            'wants' => _('Wants to'),
            'skills' => _('Skills'),
            // The tabs of a profile: the texts of the engine, as every site has them
            'web' => $texts['profile_tab_web'],
            'first_life' => $texts['profile_tab_first_life'],
            'second_life' => $texts['profile_tab_second_life'],
            'interests' => $texts['profile_tab_interests'],
            'profile_sections' => $texts['profile_sections'],
            'picks' => $texts['profile_tab_picks'],
            'classifieds' => $texts['profile_tab_classifieds'],
            // The 2nd Life tab of a profile, as the viewer shows it
            'name' => $texts['profile_name'] ?? _('Name'),
            'key' => $texts['profile_key'] ?? _('Key'),
            'born' => $texts['born'] ?? _('Born'),
            'account' => $texts['profile_tab_account'] ?? _('Account'),
            'groups' => $texts['profile_groups'] ?? _('Groups'),
            // A section of a profile with nothing, and the details of a pick or a classified, as the viewer shows them
            'none' => $texts['profile_none'] ?? _('None.'),
            'no_description' => $texts['profile_no_description'] ?? _('No description given.'),
            'location' => $texts['profile_location'] ?? _('Location'),
            'teleport' => $texts['profile_teleport'] ?? _('Teleport'),
            'until' => $texts['profile_until'] ?? _('Until'),
        ];
    }
}
