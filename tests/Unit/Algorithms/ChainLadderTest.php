<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Actuarial\ChainLadder;

test('it estimates ibnr reserves correctly using chain ladder link ratios', function (): void {
    // 3x3 loss triangle:
    // Year 0: [1000, 1500, 1800]
    // Year 1: [1200, 1800]
    // Year 2: [1400]
    $triangle = [
        0 => [1000, 1500, 1800],
        1 => [1200, 1800],
        2 => [1400],
    ];

    $result = ChainLadder::estimateIbnr($triangle);

    // f_0 = (1500 + 1800) / (1000 + 1200) = 3300 / 2200 = 1.5
    // f_1 = 1800 / 1500 = 1.2
    expect($result['development_factors'][0])->toEqualWithDelta(1.5, 0.0001)
        ->and($result['development_factors'][1])->toEqualWithDelta(1.2, 0.0001);

    // Projected Year 0: already fully developed -> ultimate = 1800, ibnr = 0
    expect($result['ultimate_losses'][0])->toBe(1800)
        ->and($result['ibnr_reserves'][0])->toBe(0);

    // Projected Year 1: 1800 * 1.2 = 2160 -> ibnr = 2160 - 1800 = 360
    expect($result['ultimate_losses'][1])->toBe(2160)
        ->and($result['ibnr_reserves'][1])->toBe(360);

    // Projected Year 2: 1400 * 1.5 = 2100; 2100 * 1.2 = 2520 -> ibnr = 2520 - 1400 = 1120
    expect($result['ultimate_losses'][2])->toBe(2520)
        ->and($result['ibnr_reserves'][2])->toBe(1120);

    // Total IBNR: 0 + 360 + 1120 = 1480
    expect($result['total_ibnr_reserve'])->toBe(1480);
});

test('it throws on triangle with less than 2 years', function (): void {
    ChainLadder::estimateIbnr([[1000]]);
})->throws(InvalidArgumentException::class);
