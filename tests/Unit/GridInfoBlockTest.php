<?php

use OpenSim\Hypergrid\Destination;

describe('grid info', function () {
    test('the login address is the teleport link of the engine', function () {
        expect(Destination::format('http://grid.example.org:8002/', Destination::HOP))->toBe('hop://grid.example.org:8002');
    });
});
