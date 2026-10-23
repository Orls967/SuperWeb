<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\RoyaltyCalculation;
use Modules\Shared\Application\BaseAction;

class CalculatePnbpRoyaltyFromVerifiedTonnageAction extends BaseAction
{
    /**
     * Calculate statutory Minerba PNBP royalty from verified weighbridge tonnage and HMA benchmark price.
     *
     * @return array{calculation: RoyaltyCalculation, total_production_ton: float, benchmark_price_idr: int, royalty_rate_pct: float, royalty_due_idr: int}
     */
    public function handle(
        string $siteId,
        string $period,
        float $totalProductionTon,
        int $commodityBenchmarkPriceIdr,
        float $royaltyRatePercentage = 10.00
    ): array {
        return DB::transaction(function () use (
            $siteId,
            $period,
            $totalProductionTon,
            $commodityBenchmarkPriceIdr,
            $royaltyRatePercentage
        ) {
            // Statutory PNBP Royalty: Production (Tons) * HMA Benchmark (IDR) * Royalty Rate %
            $grossValueIdr = $totalProductionTon * $commodityBenchmarkPriceIdr;
            $royaltyDueIdr = (int) round(($grossValueIdr * $royaltyRatePercentage) / 100);

            $calculation = RoyaltyCalculation::updateOrCreate(
                [
                    'site_id' => $siteId,
                    'period' => $period,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'total_production_ton' => $totalProductionTon,
                    'commodity_benchmark_price_idr' => $commodityBenchmarkPriceIdr,
                    'royalty_rate_percentage' => $royaltyRatePercentage,
                    'royalty_due_idr' => $royaltyDueIdr,
                    'status' => 'accrued',
                ]
            );

            $this->audit(
                action: 'mining.royalty.pnbp_accrued',
                auditable: $calculation,
                oldValues: null,
                newValues: [
                    'site_id' => $siteId,
                    'period' => $period,
                    'total_production_ton' => $totalProductionTon,
                    'royalty_due_idr' => $royaltyDueIdr,
                    'royalty_rate_pct' => $royaltyRatePercentage,
                ],
                correlationId: "min_roy_{$siteId}_{$period}",
                impactType: 'statutory_pnbp_royalty'
            );

            $this->outbox(
                eventType: 'MiningPnbpRoyaltyAccrued',
                payload: [
                    'site_id' => $siteId,
                    'period' => $period,
                    'total_production_ton' => $totalProductionTon,
                    'royalty_due_idr' => $royaltyDueIdr,
                ],
                idempotencyKey: hash('sha256', "royalty_{$siteId}_{$period}")
            );

            return [
                'calculation' => $calculation,
                'total_production_ton' => $totalProductionTon,
                'benchmark_price_idr' => $commodityBenchmarkPriceIdr,
                'royalty_rate_pct' => $royaltyRatePercentage,
                'royalty_due_idr' => $royaltyDueIdr,
            ];
        });
    }
}
