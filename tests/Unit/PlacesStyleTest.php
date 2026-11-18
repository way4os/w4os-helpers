<?php

declare(strict_types=1);

describe('The cards of the popular places', function () {
    test('stay cards on a narrow screen: two of them a row at least, never one as wide as the page', function () {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/css/places.css');

        expect($css)->toContain('minmax(min(11rem, calc(50% - 0.5rem)), 1fr)');
    });
});
