<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use InvalidArgumentException;

/**
 * H10: PointsLiabilityFifo
 * Alokasi pemotongan poin FIFO batch dan pengakuan pendapatan breakage.
 */
final class PointsLiabilityFifo
{
    /**
     * @param  list<array{batch_id: string, remaining_pts: int, liability_per_pt_idr: int, expires_at_timestamp: int}>  $batches
     */
    public function allocate(
        array $batches,
        int $ptsToRedeem,
        int $currentTimestamp
    ): PointsLiabilityFifoCertificate {
        if ($ptsToRedeem < 0) {
            throw new InvalidArgumentException('Poin yang ditebus tidak boleh negatif.');
        }

        $expiredBatches = [];
        $totalExpiredPts = 0;
        $totalBreakageRevenueIdr = 0;

        $activeBatches = [];

        // 1. Scan expired batches
        foreach ($batches as $batch) {
            if ($batch['expires_at_timestamp'] <= $currentTimestamp && $batch['remaining_pts'] > 0) {
                $expiredPts = $batch['remaining_pts'];
                $breakageRev = $expiredPts * $batch['liability_per_pt_idr'];

                $expiredBatches[] = [
                    'batch_id' => $batch['batch_id'],
                    'expired_pts' => $expiredPts,
                    'breakage_revenue_idr' => $breakageRev,
                ];

                $totalExpiredPts += $expiredPts;
                $totalBreakageRevenueIdr += $breakageRev;
            } elseif ($batch['remaining_pts'] > 0) {
                $activeBatches[] = $batch;
            }
        }

        // Sort active batches ascending by expiration (FIFO)
        usort($activeBatches, fn ($a, $b) => $a['expires_at_timestamp'] <=> $b['expires_at_timestamp']);

        // 2. Allocate redemption using FIFO
        $redemptionAllocations = [];
        $remainingNeeded = $ptsToRedeem;
        $totalLiabilityDeductedIdr = 0;

        foreach ($activeBatches as $batch) {
            if ($remainingNeeded <= 0) {
                break;
            }

            $deduct = min($remainingNeeded, $batch['remaining_pts']);
            $liabilityDeducted = $deduct * $batch['liability_per_pt_idr'];

            $redemptionAllocations[] = [
                'batch_id' => $batch['batch_id'],
                'deducted_pts' => $deduct,
                'liability_deducted_idr' => $liabilityDeducted,
            ];

            $remainingNeeded -= $deduct;
            $totalLiabilityDeductedIdr += $liabilityDeducted;
        }

        $totalPtsDeducted = $ptsToRedeem - $remainingNeeded;

        $payload = json_encode([
            'allocations' => $redemptionAllocations,
            'pts_deducted' => $totalPtsDeducted,
            'liability_deducted' => $totalLiabilityDeductedIdr,
            'expired_batches' => $expiredBatches,
            'expired_pts' => $totalExpiredPts,
            'breakage_revenue' => $totalBreakageRevenueIdr,
        ], JSON_THROW_ON_ERROR);

        $signatureHash = hash('sha256', $payload);

        return new PointsLiabilityFifoCertificate(
            redemptionAllocations: $redemptionAllocations,
            totalPtsDeducted: $totalPtsDeducted,
            totalLiabilityDeductedIdr: $totalLiabilityDeductedIdr,
            expiredBatches: $expiredBatches,
            totalExpiredPts: $totalExpiredPts,
            totalBreakageRevenueIdr: $totalBreakageRevenueIdr,
            signatureHash: $signatureHash
        );
    }
}
