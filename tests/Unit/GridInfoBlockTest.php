<?php

use W4OS\Engine\Hypergrid\Destination;

describe('grid info', function () {
    test('the login URI is shown as host:port, linked to its teleport address', function () {
        expect(Destination::format('http://grid.example.org:8002/', Destination::TXT))->toBe('grid.example.org:8002')
            ->and(Destination::format('http://grid.example.org:8002/', Destination::HOP))->toBe('hop://grid.example.org:8002');
    });
});
