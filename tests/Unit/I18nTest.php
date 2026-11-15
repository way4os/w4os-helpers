<?php

use OpenSim\Helpers\I18n;

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
});
