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

    /** The URL of a static file of the helpers (css, js, pictures): <path>/static/<file>. */
    public static function static_url($file)
    {
        return self::base_path() . '/static/' . ltrim((string) $file, '/');
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
     * What every page has: the grid, the logo, the menu, who asks.
     *
     * @return array<string,mixed>
     */
    public static function context()
    {
        $logo = defined('OPENSIM_GRID_LOGO_URL') ? OPENSIM_GRID_LOGO_URL : null;
        if (empty($logo)) {
            foreach (['svg', 'png'] as $ext) {
                if (is_file(dirname(__DIR__) . "/static/logos/logo.$ext")) {
                    $logo = self::static_url("logos/logo.$ext");
                    break;
                }
            }
        }
        $labels = self::labels();
        $routes = defined('OPENSIM_ROUTES') ? OPENSIM_ROUTES : [];
        $menu = [['label' => $labels['home'], 'url' => '/']];
        $guide = array_search('guide.php', $routes, true);
        $menu[] = ['label' => $labels['guide'], 'url' => $guide !== false ? $guide : self::base_path() . '/guide.php'];

        return [
            'grid_name' => defined('OPENSIM_GRID_NAME') ? OPENSIM_GRID_NAME : 'OpenSimulator grid',
            'login_uri' => defined('OPENSIM_LOGIN_URI') ? OPENSIM_LOGIN_URI : null,
            'login_host' => defined('OPENSIM_LOGIN_URI') ? preg_replace('#^https?://#', '', OPENSIM_LOGIN_URI) : null,
            'logo_url' => $logo,
            'viewer' => self::is_viewer(),
            'menu' => $menu,
            'labels' => $labels,
            'static_url' => self::static_url(''),
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

    /**
     * Render a template (`pages/home.twig`, `blocks/grid-info.twig`).
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
        // The pages show blocks where they want them: {{ render_block('grid-status', { title: 'Status' }) }}
        $twig->addFunction(
            new \Twig\TwigFunction(
                'render_block',
                fn($name, $args = []) => OpenSim_Helpers_Blocks::render($name, $args, $dirs),
                ['is_safe' => ['html']],
            ),
        );

        return $twig->render($template, $context);
    }
}
