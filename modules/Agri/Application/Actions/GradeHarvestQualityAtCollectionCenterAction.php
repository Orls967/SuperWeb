<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Actions;

use InvalidArgumentException;
use Modules\Agri\Domain\Models\AgriContract;

class GradeHarvestQualityAtCollectionCenterAction
{
    /**
     * @param array{
     *     contract_id: string,
     *     gross_weight_kg: float|int,
     *     moisture_percentage?: float|int,
     *     defect_percentage?: float|int,
     *     foreign_matter_percentage?: float|int
     * } $data
     * @return array{
     *     contract_id: string,
     *     commodity: string,
     *     gross_weight_kg: float,
     *     grade: string,
     *     moisture_percentage: float,
     *     defect_percentage: float,
     *     price_multiplier: float,
     *     buying_price_per_kg: int,
     *     gross_payout_idr: int
     * }
     */
    public function execute(array $data): array
    {
        $contract = AgriContract::where('id', $data['contract_id'])->firstOrFail();

        $grossWeight = (float) ($data['gross_weight_kg'] ?? 0);
        if ($grossWeight <= 0) {
            throw new InvalidArgumentException('Gross weight must be greater than zero kg.');
        }

        $moisture = (float) ($data['moisture_percentage'] ?? 14.0);
        $defects = (float) ($data['defect_percentage'] ?? 2.0);
        $foreignMatter = (float) ($data['foreign_matter_percentage'] ?? 0.5);

        // Grade Determination
        // Grade A: Moisture <= 14.0%, defects < 3.0%, foreign matter < 1.0%
        // Grade B: Moisture <= 16.0%, defects < 7.0%, foreign matter < 2.0%
        // Grade C: Moisture > 16.0% or defects >= 7.0% or foreign matter >= 2.0%
        if ($moisture <= 14.0 && $defects < 3.0 && $foreignMatter < 1.0) {
            $grade = 'GRADE_A';
            $multiplier = 1.00;
        } elseif ($moisture <= 16.0 && $defects < 7.0 && $foreignMatter < 2.0) {
            $grade = 'GRADE_B';
            $multiplier = 0.90;
        } else {
            $grade = 'GRADE_C';
            $multiplier = 0.80;
        }

        $floorPrice = $contract->guaranteed_floor_price_idr_per_kg;
        $buyingPricePerKg = (int) round($floorPrice * $multiplier);
        $grossPayout = (int) round($grossWeight * $buyingPricePerKg);

        return [
            'contract_id' => (string) $contract->id,
            'commodity' => $contract->commodity,
            'gross_weight_kg' => $grossWeight,
            'grade' => $grade,
            'moisture_percentage' => $moisture,
            'defect_percentage' => $defects,
            'price_multiplier' => $multiplier,
            'buying_price_per_kg' => $buyingPricePerKg,
            'gross_payout_idr' => $grossPayout,
        ];
    }
}
