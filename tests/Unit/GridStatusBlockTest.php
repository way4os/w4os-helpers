<?php

use OpenSim\Helpers\Block\GridStatusBlock;

describe('grid status', function () {
    test('the area has its unit, and two decimals under 100 km²', function () {
        expect(GridStatusBlock::area(0.065536))->toBe('0.07 km²')
            ->and(GridStatusBlock::area(1.638))->toBe('1.64 km²')
            ->and(GridStatusBlock::area(0))->toBe('0.00 km²')
            ->and(GridStatusBlock::area(1234.5))->toBe('1,235 km²');
    });
});
