<?php

declare(strict_types=1);

namespace OpenSim\Helpers;

/**
 * The language of the texts of the helpers (gettext, locales/<locale>/LC_MESSAGES/messages.mo): the one a request asks (`lang`, else
 * `Accept-Language`), English when the helpers have no translation for it or the system has not its locale.
 */
final class I18n
{
    /** The languages the helpers are translated in. */
    public const LOCALES = ['cy_GB', 'de_DE', 'fr_FR', 'it_IT', 'nl_NL', 'pt_BR'];

    private static string $current = 'en';

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
            if ($locale !== null && setlocale(LC_MESSAGES, ["$locale.UTF-8", "$locale.utf8", $locale]) !== false) {
                return self::$current = self::domain($locale);
            }
        }
        // English: a language gettext has no catalog for (in the C locale, some systems take the language of the environment)
        setlocale(LC_MESSAGES, ['en_US.UTF-8', 'en_US.utf8', 'C.UTF-8', 'C']);

        return self::$current = self::domain('en');
    }

    /** The texts of the helpers in a language; textdomain() again makes gettext forget the texts of the language before. */
    private static function domain(string $locale): string
    {
        putenv("LANGUAGE=$locale");
        bindtextdomain('messages', dirname(__DIR__) . '/locales');
        bind_textdomain_codeset('messages', 'UTF-8');
        textdomain('messages');

        return $locale;
    }

    /** The locale in use (`fr_FR`), `en` for English: for the numbers (NumberFormatter). */
    public static function current(): string
    {
        return self::$current;
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
