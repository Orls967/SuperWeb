<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H11: AwardNightPricer
 * Penetapan tarif kamar reward dinamis berbasis okupansi dengan proteksi batas bawah 115% floor margin.
 */
final class AwardNightPricer
{
    public const FLOOR_MULTIPLIER = 1.15;

    public function calculate(
        int $basePointsRequired,
        float $occupancyRate,
        float $seasonalityMultiplier,
        int $marginalCostIdr,
        string $tier = 'SILVER',
        int $pointValuationIdr = 100
    ): AwardNightPriceCertificate {
        if ($basePointsRequired <= 0) {
            throw new InvalidArgumentException('Base points required harus positif.');
        }

        if ($marginalCostIdr < 0) {
            throw new InvalidArgumentException('Marginal cost tidak boleh negatif.');
        }

        // Tier discount factor
        $discountRatio = match (strtoupper($tier)) {
            'BLACK' => 0.15,
            'PLATINUM' => 0.10,
            'GOLD' => 0.05,
            default => 0.00,
        };

        // Sigmoid occupancy surge factor
        // When occupancy is 70% (0.70), surge is centered. Max surge +1.5x at 100%
        $k = 10.0;
        $theta0 = 0.70;
        $sigmoid = 1.0 / (1.0 + exp(-$k * ($occupancyRate - $theta0)));
        $surgeFactor = 1.0 + (1.5 * $sigmoid);

        $demandPoints = (int) ceil($basePointsRequired * $surgeFactor * $seasonalityMultiplier);
        $discountPts = (int) floor($demandPoints * $discountRatio);
        $netDemandPoints = max(100, $demandPoints - $discountPts);

        // Floor rule: reimbursement cannot be below 115% of marginal cost
        $floorGuaranteedIdr = (int) ceil($marginalCostIdr * self::FLOOR_MULTIPLIER);
        $floorPointsRequired = (int) ceil($floorGuaranteedIdr / max(1, $pointValuationIdr));

        $isFloorApplied = false;
        $finalPointsRequired = $netDemandPoints;

        if ($finalPointsRequired < $floorPointsRequired) {
            $finalPointsRequired = $floorPointsRequired;
            $isFloorApplied = true;
        }

        $reimbursementValueIdr = $finalPointsRequired * $pointValuationIdr;

        $payload = json_encode([
            'final_points' => $finalPointsRequired,
            'reimbursement' => $reimbursementValueIdr,
            'floor_guaranteed' => $floorGuaranteedIdr,
            'is_floor_applied' => $isFloorApplied,
            'tier_discount' => $discountPts,
        ], JSON_THROW_ON_ERROR);

        $signatureHash = hash('sha256', $payload);

        return new AwardNightPriceCertificate(
            finalPointsRequired: $finalPointsRequired,
            reimbursementValueIdr: $reimbursementValueIdr,
            floorGuaranteedIdr: $floorGuaranteedIdr,
            isFloorApplied: $isFloorApplied,
            tierDiscountPts: $discountPts,
            signatureHash: $signatureHash
        );
    }
}
