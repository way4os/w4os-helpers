<?php

use W4OS\Helpers\I18n;

describe('language', function () {
    test('the locale of a language tag', function () {
        expect(I18n::locale('fr'))->toBe('fr_FR')
            ->and(I18n::locale('pt-BR'))->toBe('pt_BR')
            ->and(I18n::locale('nl-be'))->toBe('nl_NL')
            ->and(I18n::locale('en-GB'))->toBe('en')
            ->and(I18n::locale('ja'))->toBeNull();
    });

    test('the texts follow the language asked, English when there is no translation', function () {
        expect(I18n::use('ja, fr-FR;q=0.9, en;q=0.5'))->toBe('fr_FR')
            ->and(I18n::text('Grid Information'))->toBe('Informations sur la grille')
            // The plural rule of the catalog: none is singular in French, not in English
            ->and(I18n::plural('%s destination', '%s destinations', 0))->toBe('%s destination')
            ->and(I18n::plural('%s destination', '%s destinations', 2))->toBe('%s destinations')
            ->and(I18n::text('A text the helpers do not have'))->toBe('A text the helpers do not have')
            ->and(I18n::use('ja'))->toBe('en')
            ->and(I18n::text('Grid Information'))->toBe('Grid Information')
            ->and(I18n::plural('%s destination', '%s destinations', 0))->toBe('%s destinations');
    });

    test('are read without the gettext extension and without the locales of the system', function () {
        // Another PHP, without its configuration (no extension loaded as a module: gettext where it is one), in the C locale
        $code = 'require "' . dirname(__DIR__, 2) . '/vendor/autoload.php"; W4OS\\Helpers\\I18n::use("de"); '
            . 'echo json_encode([extension_loaded("gettext"), W4OS\\Helpers\\I18n::text("Grid Information"), W4OS\\Helpers\\I18n::current()]);';
        $process = proc_open([PHP_BINARY, '-n', '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['LC_ALL' => 'C', 'LANG' => 'C', 'LANGUAGE' => '', 'PATH' => getenv('PATH')]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        proc_close($process);
        [$gettext, $text, $locale] = json_decode((string) $out, true) ?? [null, $err, null];

        expect([$text, $locale])->toBe(['Grid-Informationen', 'de_DE'])
            ->and(is_bool($gettext))->toBeTrue();
    });

    test('a time in the language in use: a medium date, with its time, or a short one with today and yesterday by their name', function () {
        I18n::use('en');

        expect(I18n::date(1700000000, 'date', 'UTC'))->toBe('Nov 14, 2023')
            ->and(I18n::date(1700000000, 'long', 'UTC'))->toMatch('~^Nov 14, 2023, 10:13\\s?PM$~u')
            ->and(I18n::date(1700000000, 'list', 'UTC'))->toMatch('~^11/14/23, 10:13\\s?PM$~u')
            ->and(I18n::date(time() - 60, 'list'))->toStartWith('today')
            ->and(I18n::date(time() - 86400, 'list'))->toStartWith('yesterday');
    });

    test('a time in French in French', function () {
        I18n::use('fr');

        expect(I18n::date(1700000000, 'date', 'UTC'))->toBe('14 nov. 2023')
            ->and(I18n::date(1700000000, 'long', 'UTC'))->toBe('14 nov. 2023, 22:13');
        I18n::use('en');
    })->skip(fn () => !class_exists(IntlDateFormatter::class), 'no intl here');
});
