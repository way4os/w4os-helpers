<?php

declare(strict_types=1);

namespace W4OS\Helpers\Block;

use W4OS\Helpers\I18n;
use W4OS\Engine\I18n\Catalog;
use W4OS\Engine\Models\Grid;
use W4OS\Engine\Runtime\GridMonitor;

/** The state of the grid at a glance, from the monitor of the engine. */
final class GridStatusBlock implements Block
{
    /** The keyword the viewers read (Firestorm takes the state of the grid from it): not translated. Null when the machine cannot tell. */
    public static function keyword(array $monitor): ?string
    {
        return [
            GridMonitor::OK => 'ONLINE',
            GridMonitor::DEGRADED => 'DEGRADED',
            GridMonitor::DOWN => 'OFFLINE',
        ][$monitor['level']] ?? null;
    }

    /** The level as a page says it, in a language (Online, En ligne): the text of the catalog of the engine. Null when the machine cannot tell. */
    public static function label(array $monitor, string $locale = 'en'): ?string
    {
        $key = GridMonitor::labelKey((string) ($monitor['level'] ?? ''));

        return $key === null ? null : (string) ((Catalog::messages($locale) + Catalog::MESSAGES)[$key] ?? $key);
    }

    /** An area in square kilometers, with its unit: two decimals under 100 km² (a region of 256 m is 0.07 km²). */
    public static function area(float $squareKilometers, string $locale = 'en'): string
    {
        return self::number($squareKilometers, $locale, $squareKilometers < 100 ? 2 : 0) . ' km²';
    }

    /** A number as a language writes it (1,234.5 in English, 1 234,5 in French). */
    public static function number(float $value, string $locale = 'en', int $decimals = 0): string
    {
        $format = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $format->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $format->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $format->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        return (string) $format->format($value);
    }

    /** @return array<string,string> the label of each number of the monitor, as w4os-wp says them */
    private static function labels(): array
    {
        return [
            'members' => I18n::text('Members'),
            'active_members' => I18n::text('Active members (30 days)'),
            'members_in_world' => I18n::text('Members in world'),
            'active_users' => I18n::text('Active users (30 days)'),
            'total_users' => I18n::text('Total users in world'),
        ];
    }

    /**
     * The rows of the block, as w4os-wp shows them: the status, the numbers of the users, the regions online, the total area.
     *
     * @param array<string,mixed> $monitor what GridMonitor::of() says
     * @return list<array{label:string,value:string}>
     */
    public static function rows(array $monitor, string $locale = 'en'): array
    {
        $rows = [];
        $label = self::label($monitor, $locale);
        $label === null || ($rows[] = ['label' => I18n::text('Status'), 'value' => $label]);
        $users = (array) ($monitor['users'] ?? []);
        foreach (self::labels() as $key => $text) {
            isset($users[$key]) && ($rows[] = ['label' => $text, 'value' => self::number((float) $users[$key], $locale)]);
        }
        $rows[] = ['label' => I18n::text('Regions'), 'value' => self::number((float) ($monitor['regions']['online'] ?? 0), $locale)];
        isset($users['area']) && ($rows[] = ['label' => I18n::text('Total area'), 'value' => self::area((float) $users['area'], $locale)]);

        return $rows;
    }

    public function data(Grid $grid, array $args = []): array
    {
        $monitor = GridMonitor::of($grid);
        $rows = self::rows($monitor, I18n::current());

        return ['title' => I18n::text('Grid Status'), 'rows' => $rows, 'monitor' => $monitor];
    }
}
