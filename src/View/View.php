<?php

declare(strict_types=1);

namespace OpenSim\Helpers\View;

use OpenSim\Helpers\Block\Blocks;
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

    /** Whether what the last block shown is about exists (a profile that does not is a 404). */
    public function found(): bool
    {
        return $this->found;
    }

    public function __construct(string $templates)
    {
        $this->loader = new FilesystemLoader($templates);
        $this->twig = new Twig($this->loader, ['autoescape' => 'html']);
    }

    /** A block as an HTML fragment. @param array<string,mixed> $args */
    public function block(string $name, Grid $grid, array $args = []): string
    {
        $data = Blocks::data($name, $grid, $args);
        $this->found = (($data['found'] ?? true) !== false);

        return $data === null ? '' : $this->twig->render("components/$name-block.twig", $data + ['labels' => self::labels()]);
    }

    /** @param array<string,mixed> $args the arguments of the request, for the block the page is */
    public function page(string $page, Grid $grid, string $agent, array $args = []): string
    {
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

        return $this->twig->render('layouts/base-layout.twig', [
            'grid_name' => $grid->name(),
            'viewer' => preg_match(self::VIEWER_PATTERN, $agent) === 1,
            'slug' => $page,
            'main' => new Markup($main, 'UTF-8'),
            'sidebars' => false,
            'menu' => [],
            'labels' => self::labels(),
            'stylesheet' => '',
            'assets' => ['css' => [], 'js' => []],
            'logo_url' => '',
        ]);
    }

    /** @return array<string,string> the words of the templates */
    public static function labels(): array
    {
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
            'profile_private' => _('This profile is not published.'),
            'partner' => _('Partner'),
            'languages' => _('Languages'),
            'wants' => _('Wants to'),
            'skills' => _('Skills'),
            'web' => _('Web'),
            'first_life' => _('First life'),
            'picks' => _('Picks'),
            'classifieds' => _('Classifieds'),
        ];
    }
}
