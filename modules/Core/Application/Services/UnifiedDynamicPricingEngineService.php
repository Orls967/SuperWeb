<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

class UnifiedDynamicPricingEngineService
{
    /**
     * @return array{final_price_idr: int, pricing_strategy: string, contract_enforced: bool}
     */
    public function calculateFinalPrice(
        int $basePriceIdr,
        int $floorPriceIdr,
        int $ceilingPriceIdr,
        int $demandMultiplierBps,
        ?int $contractPriceIdr = null
    ): array {
        if ($contractPriceIdr !== null && $contractPriceIdr > 0) {
            return [
                'final_price_idr' => $contractPriceIdr,
                'pricing_strategy' => 'CONTRACT_PRIORITY',
                'contract_enforced' => true,
            ];
        }

        $calculated = (int) round(($basePriceIdr * $demandMultiplierBps) / 10000);
        $clamped = max($floorPriceIdr, min($ceilingPriceIdr, $calculated));

        return [
            'final_price_idr' => $clamped,
            'pricing_strategy' => 'DYNAMIC_BOUNDED',
            'contract_enforced' => false,
        ];
    }
}
