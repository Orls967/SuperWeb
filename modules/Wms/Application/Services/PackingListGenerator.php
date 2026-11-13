<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use InvalidArgumentException;

/**
 * Generator packing list & kartonisasi deterministik (berat gram & volume mm3 integer murni).
 */
final class PackingListGenerator
{
    /**
     * @param  list<array{product_id: int, qty: int, unit_weight_g: int, unit_volume_cm3: int}>  $items
     * @return array{
     *     carton_count: int,
     *     total_weight_g: int,
     *     total_volume_cm3: int,
     *     cartons: list<array{carton_no: int, weight_g: int, volume_cm3: int, items: list<array{product_id: int, qty: int}>}>
     * }
     */
    public function generate(array $items, int $maxWeightGramsPerCarton = 25000, int $maxVolumeCm3PerCarton = 120000): array
    {
        if ($maxWeightGramsPerCarton <= 0 || $maxVolumeCm3PerCarton <= 0) {
            throw new InvalidArgumentException('Batas karton harus lebih dari 0.');
        }

        $cartons = [];
        $currentItems = [];
        $currentWeight = 0;
        $currentVol = 0;
        $cartonNo = 1;
        $totalWeight = 0;
        $totalVol = 0;

        foreach ($items as $item) {
            $qty = (int) $item['qty'];
            if ($qty <= 0) {
                continue;
            }
            $lineWeight = $qty * (int) $item['unit_weight_g'];
            $lineVol = $qty * (int) $item['unit_volume_cm3'];
            $totalWeight += $lineWeight;
            $totalVol += $lineVol;

            if ($currentItems !== [] && ($currentWeight + $lineWeight > $maxWeightGramsPerCarton || $currentVol + $lineVol > $maxVolumeCm3PerCarton)) {
                $cartons[] = [
                    'carton_no' => $cartonNo++,
                    'weight_g' => $currentWeight,
                    'volume_cm3' => $currentVol,
                    'items' => $currentItems,
                ];
                $currentItems = [];
                $currentWeight = 0;
                $currentVol = 0;
            }

            $currentItems[] = ['product_id' => (int) $item['product_id'], 'qty' => $qty];
            $currentWeight += $lineWeight;
            $currentVol += $lineVol;
        }

        if ($currentItems !== []) {
            $cartons[] = [
                'carton_no' => $cartonNo,
                'weight_g' => $currentWeight,
                'volume_cm3' => $currentVol,
                'items' => $currentItems,
            ];
        }

        return [
            'carton_count' => count($cartons),
            'total_weight_g' => $totalWeight,
            'total_volume_cm3' => $totalVol,
            'cartons' => $cartons,
        ];
    }
}
