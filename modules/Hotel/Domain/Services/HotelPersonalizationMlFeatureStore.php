<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;

final class HotelPersonalizationMlFeatureStore
{
    /**
     * @return array{
     *     travel_pass_id: string,
     *     feature_vector: array{
     *         recency_days: int,
     *         frequency_365d: int,
     *         monetary_pts_lifetime: int,
     *         preferred_brand_tier: string,
     *         price_sensitivity_score: float,
     *         fnb_affinity_score: float,
     *         wellness_spa_affinity_score: float,
     *         family_traveler_probability: float,
     *         churn_risk_score: float
     *     },
     *     extracted_at: string
     * }
     */
    public function extractMemberFeatures(string $travelPassId): array
    {
        $pass = HtlTravelPass::findOrFail($travelPassId);

        $batches = HtlTravelPassPointBatch::where('travel_pass_id', $pass->id)->get();
        $latestBatch = $batches->sortByDesc('earned_at')->first();
        $recencyDays = $latestBatch ? (int) $latestBatch->earned_at->diffInDays(now()) : 999;
        $frequency = $batches->count();
        $monetary = (int) $batches->sum('initial_pts');

        // Dynamic feature inference based on batch originating lines
        $originatingCounts = $batches->groupBy('originating_line')->map->count();
        $restoCount = $originatingCounts->get('RESTO', 0);
        $wellnessCount = $originatingCounts->get('WELLNESS_SPA', 0) + $originatingCounts->get('RESORT_ACTIVITIES', 0);

        $fnbAffinity = $frequency > 0 ? round($restoCount / $frequency, 2) : 0.0;
        $wellnessAffinity = $frequency > 0 ? round($wellnessCount / $frequency, 2) : 0.0;

        // Churn score increases if inactive for > 180 days
        $churnRisk = match (true) {
            $recencyDays > 365 => 0.95,
            $recencyDays > 180 => 0.70,
            $recencyDays > 90 => 0.40,
            default => 0.10,
        };

        // Price sensitivity is higher for Bronze/Silver and lower for Black Tier
        $priceSensitivity = match ($pass->tier) {
            'BLACK' => 0.15,
            'PLATINUM' => 0.35,
            'GOLD' => 0.60,
            default => 0.85,
        };

        return [
            'travel_pass_id' => $pass->id,
            'feature_vector' => [
                'recency_days' => $recencyDays,
                'frequency_365d' => $frequency,
                'monetary_pts_lifetime' => $monetary,
                'preferred_brand_tier' => $pass->tier === 'BLACK' ? 'LUXURY_HERITAGE' : 'UPSCALE_BUSINESS',
                'price_sensitivity_score' => $priceSensitivity,
                'fnb_affinity_score' => $fnbAffinity,
                'wellness_spa_affinity_score' => $wellnessAffinity,
                'family_traveler_probability' => ($pass->pts_balance > 100000) ? 0.65 : 0.25,
                'churn_risk_score' => $churnRisk,
            ],
            'extracted_at' => now()->toIso8601String(),
        ];
    }
}
