<?php

declare(strict_types=1);

namespace OpenSim\Helpers\View;

use OpenSim\Models\Grid;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

/** Renders a page of templates/pages inside the layout; a viewer (the one that shows the splash page) gets the content alone. */
final class View
{
    public const VIEWER_PATTERN = '/SecondLife|OpenSim|Firestorm|Singularity|Kokua|Alchemy|Catznip/i';

    private Twig $twig;

    public function __construct(string $templates)
    {
        $this->twig = new Twig(new FilesystemLoader($templates), ['autoescape' => 'html']);
    }

    /**
     * @param array<string,mixed> $context blocks and variables of the page, by name
     */
    public function page(string $page, Grid $grid, string $agent, array $context = []): string
    {
        $main = $this->twig->render("pages/$page-page.twig", ['grid_name' => $grid->name()] + $context);

        return $this->twig->render('layouts/base-layout.twig', [
            'grid_name' => $grid->name(),
            'viewer' => preg_match(self::VIEWER_PATTERN, $agent) === 1,
            'slug' => $page,
            'main' => new \Twig\Markup($main, 'UTF-8'),
            'sidebars' => false,
            'menu' => [],
            'labels' => ['skip' => _('Skip to content'), 'powered_by' => 'opensim-helpers'],
            'stylesheet' => '',
            'assets' => ['css' => [], 'js' => []],
            'logo_url' => '',
        ]);
    }
}
