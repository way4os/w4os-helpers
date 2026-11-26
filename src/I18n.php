<?php

declare(strict_types=1);

namespace W4OS\Helpers;

use Gettext\Loader\MoLoader;
use Gettext\Translator;

/**
 * The language of the texts of the helpers (locales/<locale>/LC_MESSAGES/messages.mo): the one a request asks (`lang`, else
 * `Accept-Language`), English when the helpers have no translation for it. The catalogs are read by gettext/gettext and
 * gettext/translator, in PHP: neither the gettext extension nor the locales of the system are needed.
 *
 *     I18n::use('fr');
 *     I18n::text('Popular places');                          // Lieux populaires
 *     I18n::plural('%s destination', '%s destinations', 3);  // %s destinations
 */
final class I18n
{
    /** The languages the helpers are translated in. */
    public const LOCALES = ['cy_GB', 'de_DE', 'fr_FR', 'it_IT', 'nl_NL', 'pt_BR'];

    private static string $current = 'en';

    /** The translator of the language in use, null for English. */
    private static ?Translator $translator = null;

    /** @var array<string,Translator> the catalogs read, by locale */
    private static array $read = [];

    /**
     * Uses the first language of a list the helpers have, for the texts that follow.
     *
     * @param string $languages `fr`, `pt-BR`, or an Accept-Language header (`fr-FR,fr;q=0.9,en;q=0.8`)
     * @return string the locale used (`fr_FR`), `en` for English
     */
    public static function use(string $languages): string
    {
        foreach (self::tags($languages) as $tag) {
            $locale = self::locale($tag);
            if ($locale === 'en') {
                break;
            }
            if ($locale !== null && ($translator = self::catalog($locale)) !== null) {
                self::$translator = $translator;

                return self::$current = $locale;
            }
        }
        self::$translator = null;

        return self::$current = 'en';
    }

    /**
     * A text in the language in use, the English one when it has no translation. A context (gettext's msgctxt) keeps a text apart
     * from the same English words elsewhere, its translation its own (the map's "Location": grid coordinates, not a place).
     */
    public static function text(string $message, ?string $context = null): string
    {
        if (self::$translator === null) {
            return $message;
        }

        return $context === null ? self::$translator->gettext($message) : self::$translator->pgettext($context, $message);
    }

    /** A text that depends on a number, in the language in use (its plural forms), the English one when it has no translation. */
    public static function plural(string $singular, string $plural, int $count): string
    {
        return self::$translator?->ngettext($singular, $plural, $count) ?? ($count === 1 ? $singular : $plural);
    }

    /** Whether the helpers have a language (fr, pt-BR): English, or one they have a catalog of. */
    public static function has(string $code): bool
    {
        $locale = self::locale($code);

        return $locale === 'en' || ($locale !== null && self::catalog($locale) !== null);
    }

    /** The catalog of a language, read once; null when the helpers have none. */
    private static function catalog(string $locale): ?Translator
    {
        if (!isset(self::$read[$locale])) {
            $file = dirname(__DIR__) . "/locales/$locale/LC_MESSAGES/messages.mo";
            if (!is_file($file)) {
                return null;
            }
            self::$read[$locale] = Translator::createFromTranslations((new MoLoader())->loadFile($file));
        }

        return self::$read[$locale];
    }

    /** The locale in use (`fr_FR`), `en` for English: for the numbers (NumberFormatter). */
    public static function current(): string
    {
        return self::$current;
    }

    /** The language in use as a page says it (`lang` of html: fr-FR), `en` for English. */
    public static function tag(): string
    {
        return str_replace('_', '-', self::$current);
    }

    /**
     * A time in the language in use, by PHP's Intl formatter (ISO without it): `list` the short date, today and yesterday by their
     * name, with its time (a list: when someone joined); `date` the medium date (a profile: since when); `long` the medium date with
     * its time (an event).
     *
     * @param int $time a Unix time
     * @param ?string $timezone the zone it is told in (`UTC`); the one of PHP without it
     */
    public static function date(int $time, string $style = 'date', ?string $timezone = null): string
    {
        $date = (new \DateTimeImmutable('@' . $time))->setTimezone(new \DateTimeZone($timezone ?? date_default_timezone_get()));
        if (!class_exists(\IntlDateFormatter::class)) {
            return $date->format($style === 'date' ? 'Y-m-d' : 'Y-m-d H:i');
        }
        [$day, $hour] = match ($style) {
            'list' => [\IntlDateFormatter::RELATIVE_SHORT, \IntlDateFormatter::SHORT],
            'long' => [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT],
            default => [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE],
        };

        return (string) (new \IntlDateFormatter(self::$current, $day, $hour, $date->getTimezone()))->format($date);
    }

    /**
     * A time told relatively in the language in use, as Carbon says it in each language (`2 hours ago`, `il y a 2 heures`), where
     * the host lets Carbon run (w4os-views' Dates::canTellRelative()); the short date with its time else (a site of Grav 1.7).
     */
    public static function ago(int $time, ?int $now = null): string
    {
        if (!\W4OS\Views\Dates::canTellRelative()) {
            return self::date($time, 'list');
        }

        return \Carbon\Carbon::createFromTimestamp($time)->locale(self::$current)
            ->diffForHumans(\Carbon\Carbon::createFromTimestamp($now ?? time()), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW);
    }

    /** The locale of the helpers for a language tag (fr, fr-FR, pt-br), `en` for English, null for one they do not have. */
    public static function locale(string $tag): ?string
    {
        [$language, $region] = array_pad(explode('_', str_replace('-', '_', strtolower(trim($tag))), 2), 2, '');
        if ($language === 'en') {
            return 'en';
        }
        $match = null;
        foreach (self::LOCALES as $locale) {
            if (strtolower($locale) === "{$language}_$region") {
                return $locale;
            }
            if ($match === null && str_starts_with(strtolower($locale), "{$language}_")) {
                $match = $locale;
            }
        }

        return $match;
    }

    /** @return list<string> the language tags of a list, in the order of their weight */
    private static function tags(string $languages): array
    {
        $tags = [];
        foreach (explode(',', $languages) as $i => $part) {
            [$tag, $weight] = array_pad(explode(';q=', trim($part), 2), 2, '1');
            trim($tag) === '' || ($tags[trim($tag)] = (float) $weight - $i / 1000);
        }
        arsort($tags);

        return array_keys($tags);
    }
}
