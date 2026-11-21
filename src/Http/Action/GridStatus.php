<?php

declare(strict_types=1);

namespace W4OS\Helpers\Http\Action;

use W4OS\Helpers\Block\GridStatusBlock;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Runtime\GridMonitor;
use Psr\Http\Message\ResponseInterface;

/** The status of the grid for the viewers: a line of plain text with the keyword, or the RSS feed they read. */
final class GridStatus
{
    /** The line of text: `ONLINE`, `OFFLINE`, `DEGRADED`, with the summary of the monitor. */
    public static function text(Grid $grid): string
    {
        $monitor = GridMonitor::of($grid);

        return trim((GridStatusBlock::keyword($monitor) ?? '') . ' ' . ($monitor['summary'] ?? ''));
    }

    public static function respondText(ResponseInterface $response, Grid $grid): ResponseInterface
    {
        $response->getBody()->write(self::text($grid) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public static function respondRss(ResponseInterface $response, Grid $grid): ResponseInterface
    {
        $text = htmlspecialchars(self::text($grid), ENT_XML1);
        $name = htmlspecialchars($grid->name(), ENT_XML1);
        $response->getBody()->write(
            '<?xml version="1.0" encoding="UTF-8"?>' .
                "<rss version=\"2.0\"><channel><title>$name</title><description>$name</description>" .
                "<item><title>$text</title><pubDate>" .
                gmdate(DATE_RSS) .
                '</pubDate><guid isPermaLink="false">' .
                md5($text) .
                "</guid></item></channel></rss>\n",
        );

        return $response->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
