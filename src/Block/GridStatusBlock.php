<?php

declare(strict_types=1);

namespace OpenSim\Helpers\Block;

use OpenSim\Helpers\I18n;
use OpenSim\I18n\Catalog;
use OpenSim\Models\Grid;
use OpenSim\Runtime\GridMonitor;

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

    /** @return array<string,string> the label of each number of the monitor */
    private static function labels(): array
    {
        return [
            'members' => _('Members'),
            'active_members' => _('Active members (30 days)'),
            'members_in_world' => _('Members in world'),
            'active_users' => _('Active users (30 days)'),
            'total_users' => _('Users in world'),
        ];
    }

    public function data(Grid $grid, array $args = []): array
    {
        $monitor = GridMonitor::of($grid);
        $rows = [];
        $locale = I18n::current();
        $label = self::label($monitor, $locale);
        $label === null || ($rows[] = ['label' => _('Status'), 'value' => $label]);
        $rows[] = [
            'label' => _('Regions'),
            'value' => self::number($monitor['regions']['online'], $locale) . ' / ' . self::number($monitor['regions']['total'], $locale),
        ];
        $labels = self::labels();
        foreach ((array) ($monitor['users'] ?? []) as $key => $count) {
            if ($key === 'regions') {
                continue; // the row of the regions above, online and total
            }
            $rows[] = $key === 'area'
                ? ['label' => _('Area'), 'value' => self::area((float) $count, $locale)]
                : ['label' => $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key)), 'value' => self::number((float) $count, $locale)];
        }

        return ['title' => _('Grid Status'), 'rows' => $rows, 'monitor' => $monitor];
    }
}
