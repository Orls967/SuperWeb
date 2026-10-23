<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Application\DTOs\Ai\StockRecommendationResult;

interface StockPrescriptionEngineInterface
{
    /**
     * Prescribe adaptive stock and calculate statistical Reorder Point (ROP) with seasonal index weighting.
     * Generates PO draft proposal; flags human approval required if estimated cost > autonomous ceiling (Rp 10.000.000).
     */
    public function generateRecommendation(
        string $itemCode,
        string $itemName,
        int $currentStock,
        int $avgDailyDemand,
        int $leadTimeDays,
        int $unitCostIdr,
        string $seasonCode = 'REGULAR',
        float $demandStdDev = 5.0,
        float $leadTimeStdDev = 1.0,
        int $seed = 42
    ): StockRecommendationResult;
}
