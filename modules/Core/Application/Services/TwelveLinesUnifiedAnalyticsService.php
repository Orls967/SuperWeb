<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

class TwelveLinesUnifiedAnalyticsService
{
    /**
     * Unified dynamic pricing calculation with strict floor & ceiling guardrails
     */
    public function computeUnifiedPrice(
        string $domain,
        int $baseCostIdr,
        float $demandMultiplier,
        int $floorPriceIdr,
        int $ceilingPriceIdr,
        ?int $contractPriceIdr = null
    ): array {
        // Contract price takes absolute priority if present
        if ($contractPriceIdr !== null) {
            return [
                'domain' => $domain,
                'final_price_idr' => $contractPriceIdr,
                'pricing_mode' => 'CONTRACT_PRIORITY',
            ];
        }

        // Dynamic price calculation
        $calculatedPrice = (int) round($baseCostIdr * $demandMultiplier);

        // Enforce floor and ceiling bounds
        $boundedPrice = max($floorPriceIdr, min($ceilingPriceIdr, $calculatedPrice));

        return [
            'domain' => $domain,
            'calculated_price_idr' => $calculatedPrice,
            'final_price_idr' => $boundedPrice,
            'pricing_mode' => 'DYNAMIC_BOUNDED',
            'clamped_at_floor' => $calculatedPrice < $floorPriceIdr,
            'clamped_at_ceiling' => $calculatedPrice > $ceilingPriceIdr,
        ];
    }

    /**
     * Cross-line anomaly detector & fraud score calculation (0 - 100)
     */
    public function evaluateCrossLineRisk(array $indicators): array
    {
        $score = 0;

        // Example indicators: rapid velocity, high deviation, geo distance, time-of-day
        if (! empty($indicators['rapid_velocity'])) {
            $score += 30;
        }
        if (! empty($indicators['extreme_amount_deviation'])) {
            $score += 35;
        }
        if (! empty($indicators['suspicious_ip_or_device'])) {
            $score += 25;
        }
        if (! empty($indicators['contract_mismatch'])) {
            $score += 20;
        }

        $riskScore = min(100, $score);
        $quarantine = $riskScore >= 60;

        return [
            'risk_score' => $riskScore,
            'quarantine_required' => $quarantine,
            'status' => $quarantine ? 'QUARANTINED' : 'CLEARED',
        ];
    }
}
