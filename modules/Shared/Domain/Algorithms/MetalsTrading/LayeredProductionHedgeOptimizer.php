<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

use InvalidArgumentException;

/**
 * Algorithm M13: Layered Production Hedge Optimizer & Sensitivity Stress Tester.
 *
 * Implements tiered time-decay hedge ratio allocation across multi-horizon delivery periods
 * for metal smelter production desks, stress-testing downside revenue under LME price shocks.
 */
final class LayeredProductionHedgeOptimizer
{
    /**
     * @param  list<LayeredHedgeHorizonQuote>  $quotes
     * @param  float  $downsideShockPct  e.g. 0.20 for a 20% LME price drop
     */
    public function optimize(array $quotes, float $downsideShockPct = 0.20): LayeredProductionHedgeResult
    {
        if (empty($quotes)) {
            throw new InvalidArgumentException('Hedge optimization requires at least one horizon tenor quote.');
        }

        if ($downsideShockPct <= 0.0 || $downsideShockPct >= 1.0) {
            throw new InvalidArgumentException('Downside shock percentage must be strictly between 0.0 and 1.0.');
        }

        $aggPlanned = 0.0;
        $aggExistingHedged = 0.0;
        $aggNewHedge = 0.0;
        $securedNotionalIdr = 0;
        $tenorRecommendations = [];
        $unhedgedExposureTons = 0.0;
        $weightedAvgForwardUsd = 0.0;
        $weightedExchangeRate = 0.0;

        foreach ($quotes as $quote) {
            $aggPlanned += $quote->plannedProductionTons;
            $aggExistingHedged += $quote->existingHedgedTons;

            $requiredHedgeTons = $quote->plannedProductionTons * $quote->targetHedgeRatio;
            $additionalHedgeNeeded = max(0.0, $requiredHedgeTons - $quote->existingHedgedTons);
            $effectiveHedgeTons = min($quote->plannedProductionTons, $quote->existingHedgedTons + $additionalHedgeNeeded);

            $coveragePct = ($quote->plannedProductionTons > 0.0)
                ? round(($effectiveHedgeTons / $quote->plannedProductionTons) * 100, 2)
                : 0.0;

            $tenorNotionalIdr = (int) round($effectiveHedgeTons * $quote->lmeForwardPriceUsdPerTon * $quote->exchangeRateIdrPerUsd);
            $securedNotionalIdr += $tenorNotionalIdr;

            $unhedgedTons = max(0.0, $quote->plannedProductionTons - $effectiveHedgeTons);
            $unhedgedExposureTons += $unhedgedTons;
            $weightedAvgForwardUsd += ($quote->lmeForwardPriceUsdPerTon * quote_weight($quote->plannedProductionTons));
            $weightedExchangeRate += ($quote->exchangeRateIdrPerUsd * quote_weight($quote->plannedProductionTons));

            $aggNewHedge += $additionalHedgeNeeded;

            $tenorRecommendations[$quote->monthTenor] = [
                'planned_production_tons' => $quote->plannedProductionTons,
                'existing_hedged_tons' => $quote->existingHedgedTons,
                'recommended_additional_hedge_tons' => round($additionalHedgeNeeded, 2),
                'effective_hedged_tons' => round($effectiveHedgeTons, 2),
                'coverage_pct' => $coveragePct,
                'forward_price_usd_per_ton' => $quote->lmeForwardPriceUsdPerTon,
                'secured_notional_idr' => $tenorNotionalIdr,
            ];
        }

        $overallCoveragePct = ($aggPlanned > 0.0)
            ? round((($aggExistingHedged + $aggNewHedge) / $aggPlanned) * 100, 2)
            : 0.0;

        // Stress-test downside revenue under -10%, -20%, -30% shocks
        $shockScenarios = [
            'shock_minus_10_pct' => 0.10,
            'shock_minus_20_pct' => 0.20,
            'shock_minus_30_pct' => 0.30,
        ];

        $avgForwardUsd = $aggPlanned > 0.0 ? ($securedNotionalIdr / ($aggPlanned * 16000.0)) : 18000.0;
        $stressTestResults = [];

        foreach ($shockScenarios as $key => $shockRate) {
            $lossPerTonUsd = $avgForwardUsd * $shockRate;
            $unhedgedLossIdr = (int) round($unhedgedExposureTons * $lossPerTonUsd * 16000.0);
            $stressTestResults[$key] = $unhedgedLossIdr;
        }

        $isCompliant = $overallCoveragePct >= 30.0 && $overallCoveragePct <= 100.0;

        $verificationHash = hash('sha256', sprintf(
            '%.2f:%.2f:%.2f:%.2f:%d',
            $aggPlanned,
            $aggExistingHedged,
            $aggNewHedge,
            $overallCoveragePct,
            $securedNotionalIdr
        ));

        return new LayeredProductionHedgeResult(
            aggregatePlannedProductionTons: round($aggPlanned, 2),
            aggregateExistingHedgedTons: round($aggExistingHedged, 2),
            aggregateNewHedgeRecommendedTons: round($aggNewHedge, 2),
            portfolioCoveragePct: $overallCoveragePct,
            securedNotionalValueIdr: $securedNotionalIdr,
            tenorRecommendations: $tenorRecommendations,
            stressTestDownsideLossIdr: $stressTestResults,
            isHedgePolicyCompliant: $isCompliant,
            verificationHash: $verificationHash
        );
    }
}

/**
 * @internal
 */
function quote_weight(float $planned): float
{
    return max(0.0001, $planned);
}
