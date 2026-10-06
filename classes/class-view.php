<?php
/**
 * View: what the pages and the blocks are made of. The layout adapts to who asks: a browser gets the page with the top
 * bar (the logo of the grid) and the footer, a viewer (the one that shows the splash page, the guide, the profiles) gets
 * the content alone.
 *
 * @package     magicoli/opensim-helpers
 * @license     AGPLv3
 */
class OpenSim_Helpers_View
{
    /** What the viewers say they are in their User-Agent (Second Life and its derivatives, OpenSimulator ones). */
    const VIEWER_PATTERN = '/SecondLife|OpenSim|Firestorm|Singularity|Kokua|Alchemy|Catznip/i';

    /** Where the helpers are on the web site when the grid says nothing. */
    const DEFAULT_PATH = '/helpers';

    /**
     * Whether the page is for a viewer: the User-Agent says so, `?viewer=1` or `?viewer=0` decides for a browser that wants to
     * see what a viewer sees.
     *
     * @param  string|null          $agent
     * @param  array<string,mixed>  $query
     * @return bool
     */
    public static function is_viewer($agent = null, $query = null)
    {
        $agent = $agent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $query = $query ?? $_GET;
        if (isset($query['viewer'])) {
            return filter_var($query['viewer'], FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) preg_match(self::VIEWER_PATTERN, (string) $agent);
    }

    /** Where the helpers are on the web site: the path of helpers.ini, `/helpers` by default. */
    public static function base_path()
    {
        $path = defined('OPENSIM_HELPERS_PATH') ? OPENSIM_HELPERS_PATH : self::DEFAULT_PATH;

        return '/' . trim((string) $path, '/') === '/' ? '' : '/' . trim((string) $path, '/');
    }

    /** The URL of a file of the assets of the helpers (css, js, images, fonts): <path>/assets/<file>. */
    public static function assets_url($file)
    {
        return self::base_path() . '/assets/' . ltrim((string) $file, '/');
    }

    /** The URL of a block, as a fragment to embed: <path>/blocks/<name>. */
    public static function block_url($name)
    {
        return self::base_path() . '/blocks/' . $name;
    }

    /**
     * The texts of the layout, translatable.
     *
     * @return array<string,string>
     */
    public static function labels()
    {
        return [
            'home' => _('Home'),
            'guide' => _('Destinations'),
            'powered_by' => _('Powered by OpenSimulator Helpers'),
            'skip' => _('Skip to main content'),
        ];
    }

    /**
     * What every page has: the grid, the logo, the menu, who asks, and which columns it shows. A browser has the top bar, the
     * footer and the sidebars; a viewer has the content alone, with the sidebars on the splash page only.
     *
     * @param  string|null $slug the page (home, splash, guide...)
     * @return array<string,mixed>
     */
    public static function context($slug = null)
    {
        $logo = defined('OPENSIM_GRID_LOGO_URL') ? OPENSIM_GRID_LOGO_URL : null;
        if (empty($logo)) {
            foreach (['svg', 'png'] as $ext) {
                if (is_file(dirname(__DIR__) . "/assets/images/logos/logo.$ext")) {
                    $logo = self::assets_url("images/logos/logo.$ext");
                    break;
                }
            }
        }
        $labels = self::labels();
        $routes = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $menu = [['label' => $labels['home'], 'url' => '/']];
        $guide = array_search('guide.php', $routes, true);
        $menu[] = ['label' => $labels['guide'], 'url' => $guide !== false ? $guide : self::base_path() . '/guide.php'];
        $viewer = self::is_viewer();

        return [
            'grid_name' => defined('OPENSIM_GRID_NAME') ? OPENSIM_GRID_NAME : 'OpenSimulator grid',
            'login_uri' => defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : null,
            'login_host' => defined('OPENSIM_LOGIN_URI') ? preg_replace('#^https?://#', '', OPENSIM_LOGIN_URI) : null,
            'logo_url' => $logo,
            'viewer' => $viewer,
            'sidebars' => !$viewer || $slug === 'splash',
            'slug' => $slug,
            'menu' => $menu,
            'labels' => $labels,
            'assets_url' => self::assets_url(''),
        ];
    }

    /**
     * The template folders: the one of the operator (OPENSIM_TEMPLATES_DIR) first, so a template can be customized.
     *
     * @return string[]
     */
    public static function template_dirs()
    {
        $dirs = [];
        if (defined('OPENSIM_TEMPLATES_DIR')) {
            $dirs[] = OPENSIM_TEMPLATES_DIR;
        }
        $dirs[] = dirname(__DIR__) . '/templates';

        return $dirs;
    }

    /** Whether a template exists in one of the folders. */
    public static function has_template($name, $dirs = null)
    {
        foreach ($dirs ?? self::template_dirs() as $dir) {
            if (is_file("$dir/$name")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render a template (`template.twig`, `template-content-home.twig`). The blocks it shows are plain variables:
     * `{{ grid_info }}`, `{{ grid_status }}`, `{{ motd }}`... (the name of the block, dashes as underscores); only the ones
     * it uses are made. `render_block('grid-status', { title: 'Status' })` is there for what needs more.
     *
     * @param  string               $template
     * @param  array<string,mixed>  $context
     * @param  string[]|null        $dirs where to look for templates, the first wins
     * @return string
     */
    public static function render($template, $context, $dirs = null)
    {
        $loader = new \Twig\Loader\FilesystemLoader(array_values(array_filter($dirs ?? self::template_dirs(), 'is_dir')));
        $twig = new \Twig\Environment($loader, ['autoescape' => 'html']);
        $twig->addFunction(
            new \Twig\TwigFunction(
                'render_block',
                fn($name, $args = []) => OpenSim_Helpers_Blocks::render($name, $args, $dirs),
                ['is_safe' => ['html']],
            ),
        );

        // The blocks the template uses as variables
        preg_match_all('/\{\{-?\s*([a-z][a-z0-9_]*)\s*(?:\|[^}]*)?-?\}\}/', $loader->getSourceContext($template)->getCode(), $found);
        foreach (array_unique($found[1]) as $variable) {
            $name = str_replace('_', '-', $variable);
            if (!isset($context[$variable]) && OpenSim_Helpers_Blocks::exists($name)) {
                $context[$variable] = new \Twig\Markup(OpenSim_Helpers_Blocks::render($name, [], $dirs), 'UTF-8');
            }
        }

        return $twig->render($template, $context);
    }

    /**
     * The main content of a page: the template of the grid for that page (`template-content-<slug>.twig`) if there is one,
     * else the block of the same name (the guide, the search...), else nothing.
     *
     * @param  string               $slug
     * @param  array<string,mixed>  $context
     * @param  string[]|null        $dirs
     * @param  array<string,mixed>  $args what the block takes (the query)
     * @return string|null null when the page has no content
     */
    public static function main($slug, $context, $dirs = null, $args = [])
    {
        if (self::has_template("template-content-$slug.twig", $dirs)) {
            return self::render("template-content-$slug.twig", $context, $dirs);
        }
        if (OpenSim_Helpers_Blocks::exists($slug)) {
            return OpenSim_Helpers_Blocks::render($slug, $args + ['title' => null], $dirs);
        }

        return null;
    }

    /**
     * A page: the layout (`template.twig`) around the main content of the slug. Null when there is nothing to show for it.
     *
     * @param  string               $slug
     * @param  array<string,mixed>  $extra what the page adds to the context
     * @param  string[]|null        $dirs
     * @return string|null
     */
    public static function page($slug, $extra = [], $dirs = null)
    {
        $context = $extra + self::context($slug);
        $main = self::main($slug, $context, $dirs, $_GET);
        if ($main === null) {
            return null;
        }
        $context['main'] = new \Twig\Markup($main, 'UTF-8');

        return self::render('template.twig', $context, $dirs);
    }
}
