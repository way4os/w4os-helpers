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
            ->and(_('Grid Information'))->not->toBe('Grid Information')
            ->and(I18n::use('en'))->toBe('en')
            ->and(_('Grid Information'))->toBe('Grid Information');
    })->skip(fn () => setlocale(LC_MESSAGES, ['fr_FR.UTF-8', 'fr_FR.utf8']) === false, 'the system has no French locale');

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
    })->skip(fn () => setlocale(LC_MESSAGES, ['fr_FR.UTF-8', 'fr_FR.utf8']) === false, 'the system has no French locale');
});
