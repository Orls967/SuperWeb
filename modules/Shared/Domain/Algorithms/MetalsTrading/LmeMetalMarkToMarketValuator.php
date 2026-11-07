<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class LmeMetalMarkToMarketValuator
{
    private const INITIAL_MARGIN_PCT = 0.12; // 12% initial margin requirement

    private const MAINTENANCE_MARGIN_PCT = 0.08; // 8% maintenance margin threshold

    public function valuePosition(
        string $positionType, // LONG or SHORT
        float $metricTons,
        int $entryPriceUsdCentsPerTon,
        LmeMarketQuote $quote,
        float $qualityGradeAdjustmentFactor = 1.0,
        int $freightAdjustmentUsdCentsPerTon = 0
    ): LmeMetalMarkToMarketResult {
        if ($metricTons <= 0.0) {
            throw new \InvalidArgumentException('Position metric tons must be positive.');
        }
        if ($entryPriceUsdCentsPerTon <= 0) {
            throw new \InvalidArgumentException('Entry price must be positive.');
        }

        $type = strtoupper($positionType) === 'SHORT' ? 'SHORT' : 'LONG';

        // Effective market price adjusted for quality grade (e.g. 12.5% Ni vs benchmark 99.8%) and CIF/FOB freight
        $adjustedMarketPriceUsdCents = (int) round(($quote->cashPriceUsdCentsPerTon * $qualityGradeAdjustmentFactor) + $freightAdjustmentUsdCentsPerTon);

        $priceDeltaUsdCents = $adjustedMarketPriceUsdCents - $entryPriceUsdCentsPerTon;
        $sign = ($type === 'SHORT') ? -1 : 1;

        // Unrealized PnL in USD Cents and IDR (int)
        $unrealizedPnlUsdCents = (int) round($sign * $priceDeltaUsdCents * $metricTons);
        $unrealizedPnlIdr = (int) round(($unrealizedPnlUsdCents / 100.0) * $quote->exchangeRateIdrPerUsd);

        // Notional value
        $currentNotionalUsd = ($adjustedMarketPriceUsdCents / 100.0) * $metricTons;
        $currentNotionalValueIdr = (int) round($currentNotionalUsd * $quote->exchangeRateIdrPerUsd);

        // Margin requirements
        $initialMarginIdr = (int) round($currentNotionalValueIdr * self::INITIAL_MARGIN_PCT);
        $maintenanceMarginIdr = (int) round($currentNotionalValueIdr * self::MAINTENANCE_MARGIN_PCT);

        // Margin Call Trigger & Variation Margin
        $variationMarginRequiredIdr = 0;
        $isMarginCallTriggered = false;

        if ($unrealizedPnlIdr < 0) {
            $accumulatedLossIdr = abs($unrealizedPnlIdr);
            $marginBuffer = $initialMarginIdr - $maintenanceMarginIdr;

            if ($accumulatedLossIdr > $marginBuffer) {
                $isMarginCallTriggered = true;
                // Variation margin must top up equity back to initial margin level
                $variationMarginRequiredIdr = $accumulatedLossIdr;
            }
        }

        $entryNotionalUsd = ($entryPriceUsdCentsPerTon / 100.0) * $metricTons;
        $pnlPercentage = $entryNotionalUsd > 0.0 ? round(($unrealizedPnlUsdCents / 100.0 / $entryNotionalUsd) * 100.0, 2) : 0.0;

        $breakdown = [
            'benchmark_cash_usd_cents' => $quote->cashPriceUsdCentsPerTon,
            'three_month_spread_usd_cents' => $quote->spreadContangoBackwardationUsdCents(),
            'is_contango' => $quote->isContango(),
            'quality_grade_adjustment_factor' => $qualityGradeAdjustmentFactor,
            'freight_adjustment_usd_cents' => $freightAdjustmentUsdCentsPerTon,
            'adjusted_effective_market_price_usd_cents' => $adjustedMarketPriceUsdCents,
            'exchange_rate_idr_per_usd' => $quote->exchangeRateIdrPerUsd,
            'current_notional_usd' => round($currentNotionalUsd, 2),
        ];

        $verificationHash = hash('sha256', sprintf(
            '%s:%.2f:%d:%d:%d:%d:%s',
            $type,
            $metricTons,
            $adjustedMarketPriceUsdCents,
            $unrealizedPnlUsdCents,
            $unrealizedPnlIdr,
            $variationMarginRequiredIdr,
            $isMarginCallTriggered ? '1' : '0'
        ));

        return new LmeMetalMarkToMarketResult(
            positionType: $type,
            metricTons: $metricTons,
            entryPriceUsdCentsPerTon: $entryPriceUsdCentsPerTon,
            currentMarketPriceUsdCentsPerTon: $adjustedMarketPriceUsdCents,
            priceDeltaUsdCentsPerTon: $priceDeltaUsdCents,
            unrealizedPnlUsdCents: $unrealizedPnlUsdCents,
            unrealizedPnlIdr: $unrealizedPnlIdr,
            currentNotionalValueIdr: $currentNotionalValueIdr,
            initialMarginRequirementIdr: $initialMarginIdr,
            maintenanceMarginThresholdIdr: $maintenanceMarginIdr,
            variationMarginRequiredIdr: $variationMarginRequiredIdr,
            isMarginCallTriggered: $isMarginCallTriggered,
            pnlPercentage: $pnlPercentage,
            valuationBreakdown: $breakdown,
            verificationHash: $verificationHash
        );
    }
}
