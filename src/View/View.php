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

    public function __construct(string $templates)
    {
        $this->loader = new FilesystemLoader($templates);
        $this->twig = new Twig($this->loader, ['autoescape' => 'html']);
    }

    /** A block as an HTML fragment. @param array<string,mixed> $args */
    public function block(string $name, Grid $grid, array $args = []): string
    {
        $data = Blocks::data($name, $grid, $args);

        return $data === null ? '' : $this->twig->render("components/$name-block.twig", $data);
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
            'labels' => ['skip' => _('Skip to content'), 'powered_by' => 'opensim-helpers'],
            'stylesheet' => '',
            'assets' => ['css' => [], 'js' => []],
            'logo_url' => '',
        ]);
    }
}
