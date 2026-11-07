<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningSmelterByproduct;

final class RecycleSmelterByproductSlagAction
{
    public function execute(
        MiningSmelterByproduct $byproduct,
        float $recycleTons,
        string $circularApplication = 'Geopolymer Concrete Eco-Brick'
    ): MiningSmelterByproduct {
        if ($recycleTons <= 0.0) {
            throw new InvalidArgumentException('Kuantitas daur ulang harus lebih besar dari 0.');
        }

        $byproduct->dispatched_quantity_tons = (float) $byproduct->dispatched_quantity_tons + $recycleTons;
        $byproduct->stock_remaining_tons = max(0.0, (float) $byproduct->stock_remaining_tons - $recycleTons);
        $byproduct->utilization_application = $circularApplication;
        $byproduct->status = 'RECYCLED';
        $byproduct->metadata = array_merge($byproduct->metadata ?? [], [
            'recycled_tons' => $recycleTons,
            'circular_application' => $circularApplication,
            'recycled_at' => now()->toIso8601String(),
        ]);
        $byproduct->save();

        return $byproduct;
    }
}
