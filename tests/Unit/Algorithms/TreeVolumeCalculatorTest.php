<?php

declare(strict_types=1);

use Modules\Shared\Domain\Algorithms\Forestry\TreeVolumeCalculator;

it('calculates tree volume in dm3 with silviculture formula', function (): void {
    // DBH = 50 cm, Height = 20 m, Form Factor = 70%
    // Radius = 0.25 m, Basal Area = pi * 0.25^2 = ~0.19635 m2
    // Volume m3 = 0.19635 * 20 * 0.70 = ~2.74889 m3 = 2749 dm3
    $volDm3 = TreeVolumeCalculator::calculateVolumeDm3(50, 20, 70);
    expect($volDm3)->toBe(2749)
        ->and(TreeVolumeCalculator::toM3($volDm3))->toBe(2);
});

it('rejects invalid parameters for tree volume calculation', function (): void {
    TreeVolumeCalculator::calculateVolumeDm3(0, 20, 70);
})->throws(InvalidArgumentException::class);
