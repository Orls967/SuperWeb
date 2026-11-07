<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class WarehouseReceiptDigitalCollateralHaircutModel
{
    private const BASE_HAIRCUT_NPI_PCT = 15.0; // Base haircut for NPI / FeNi

    private const BASE_HAIRCUT_COPPER_PCT = 10.0; // Base haircut for LME Grade A Copper cathode

    public function calculateHaircut(
        string $metalCommodity, // NPI, FENI, CU_CATHODE
        float $certifiedWeightMetricTons,
        int $unitMarketPriceIdrPerTon,
        float $historicalVolatility30DayPct = 20.0,
        int $storageAgeDays = 15,
        float $assayVarianceTolerancePct = 0.5,
        float $bankLtvCapPct = 80.0
    ): WarehouseReceiptDigitalCollateralHaircutResult {
        if ($certifiedWeightMetricTons <= 0.0) {
            throw new \InvalidArgumentException('Certified weight must be positive.');
        }
        if ($unitMarketPriceIdrPerTon <= 0) {
            throw new \InvalidArgumentException('Unit market price IDR must be positive.');
        }

        $commodity = strtoupper($metalCommodity);
        $baseHaircut = match ($commodity) {
            'CU_CATHODE' => self::BASE_HAIRCUT_COPPER_PCT,
            default => self::BASE_HAIRCUT_NPI_PCT,
        };

        // 1. Volatility Haircut: add 0.25% for every 1% volatility above 15%
        $volatilityHaircut = max(0.0, ($historicalVolatility30DayPct - 15.0) * 0.25);

        // 2. Storage Duration Risk: add 0.05% per day past 30 days
        $storageDurationHaircut = $storageAgeDays > 30 ? ($storageAgeDays - 30) * 0.05 : 0.0;

        // 3. Assay Quality Risk: add 2.0% per 1% assay variance above 0.5%
        $assayRiskHaircut = max(0.0, ($assayVarianceTolerancePct - 0.5) * 2.0);

        // Total Haircut capped between 10% and 50%
        $totalHaircutPct = min(50.0, max(10.0, round($baseHaircut + $volatilityHaircut + $storageDurationHaircut + $assayRiskHaircut, 2)));
        $effectiveLtvPct = round(100.0 - $totalHaircutPct, 2);

        // Monotonic check with bank cap
        $finalLtvPct = min($effectiveLtvPct, $bankLtvCapPct);

        // Valuations in IDR (all int)
        $grossCollateralValueIdr = (int) round($certifiedWeightMetricTons * $unitMarketPriceIdrPerTon);
        $haircutDeductionIdr = (int) round($grossCollateralValueIdr * ($totalHaircutPct / 100.0));
        $netCollateralValueIdr = (int) round($grossCollateralValueIdr * (1.0 - ($totalHaircutPct / 100.0)));
        $maxFinancingCreditLimitIdr = (int) round($grossCollateralValueIdr * ($finalLtvPct / 100.0));

        // Liquidation trigger at 115% of max financing credit limit
        $liquidationThresholdIdr = (int) round($maxFinancingCreditLimitIdr * 1.15);

        $isApproved = $finalLtvPct >= 50.0 && $maxFinancingCreditLimitIdr > 0;

        $riskBreakdown = [
            'metal_commodity' => $commodity,
            'base_haircut_pct' => $baseHaircut,
            'volatility_30d_pct' => $historicalVolatility30DayPct,
            'volatility_haircut_pct' => round($volatilityHaircut, 2),
            'storage_age_days' => $storageAgeDays,
            'storage_duration_haircut_pct' => round($storageDurationHaircut, 2),
            'assay_variance_pct' => $assayVarianceTolerancePct,
            'assay_risk_haircut_pct' => round($assayRiskHaircut, 2),
            'bank_ltv_cap_pct' => $bankLtvCapPct,
            'final_ltv_pct' => $finalLtvPct,
        ];

        $digitalSealSignature = hash('sha256', sprintf(
            '%.3f:%d:%d:%.2f:%d:%d',
            $certifiedWeightMetricTons,
            $unitMarketPriceIdrPerTon,
            $grossCollateralValueIdr,
            $totalHaircutPct,
            $maxFinancingCreditLimitIdr,
            $liquidationThresholdIdr
        ));

        return new WarehouseReceiptDigitalCollateralHaircutResult(
            certifiedWeightMetricTons: $certifiedWeightMetricTons,
            unitMarketPriceIdrPerTon: $unitMarketPriceIdrPerTon,
            grossCollateralValueIdr: $grossCollateralValueIdr,
            baseHaircutPct: $baseHaircut,
            volatilityHaircutPct: round($volatilityHaircut, 2),
            storageDurationHaircutPct: round($storageDurationHaircut, 2),
            assayRiskHaircutPct: round($assayRiskHaircut, 2),
            totalHaircutPct: $totalHaircutPct,
            effectiveLtvPct: $effectiveLtvPct,
            haircutDeductionIdr: $haircutDeductionIdr,
            netCollateralValueIdr: $netCollateralValueIdr,
            maxFinancingCreditLimitIdr: $maxFinancingCreditLimitIdr,
            liquidationThresholdIdr: $liquidationThresholdIdr,
            isCollateralApproved: $isApproved,
            riskFactorBreakdown: $riskBreakdown,
            digitalSealSignature: $digitalSealSignature
        );
    }
}
