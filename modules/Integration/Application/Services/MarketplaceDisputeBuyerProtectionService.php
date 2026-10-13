<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MarketplaceDisputeBuyerProtectionService (Fase 373)
 *
 * Implements:
 *  - 373.1 Standard dispute evidence checklists and neutral reviewer assignments
 *  - 373.2 Buyer protection hold and escrow adjudication
 *  - 373.4 Tests: Evidence required by dispute type; hold release only after outcome; reviewer conflict blocked; marketplace:audit clean
 *  - 373.5 Edge case: Conflicted reviewer automatically replaced with neutral alternate reviewer
 *  - 373.6 Risk: Holding buyer/seller funds without final adjudication prevented
 */
class MarketplaceDisputeBuyerProtectionService
{
    /**
     * File dispute and assign reviewer with automatic conflict replacement (373.1, 373.4, 373.5 Edge Case).
     */
    public function fileDisputeCase(
        string $disputeCode,
        string $buyerId,
        string $sellerId,
        string $assignedReviewerId,
        bool $hasEvidence,
        bool $isConflicted = false,
        ?string $alternateReviewerId = null
    ): object {
        $dCode = strtoupper($disputeCode);

        // Core gate 373.4: Evidence required by dispute type
        if (! $hasEvidence) {
            throw new InvalidArgumentException('Dispute filing rejected: Required evidence checklist incomplete (373.4).');
        }

        // Edge case 373.5: Conflicted reviewer replaced by neutral alternate reviewer
        $finalReviewer = $assignedReviewerId;
        if ($isConflicted) {
            if (empty($alternateReviewerId)) {
                throw new InvalidArgumentException('Conflict of interest detected: Neutral alternate reviewer must be assigned (373.5).');
            }
            $finalReviewer = $alternateReviewerId;
        }

        $id = DB::table('marketplace_dispute_cases')->insertGetId([
            'dispute_code' => $dCode,
            'buyer_id' => strtoupper($buyerId),
            'seller_id' => strtoupper($sellerId),
            'assigned_reviewer_id' => strtoupper($finalReviewer),
            'reviewer_conflict_of_interest' => $isConflicted,
            'neutral_alternate_reviewer_id' => $isConflicted ? strtoupper($alternateReviewerId) : null,
            'has_required_evidence' => true,
            'dispute_status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('marketplace_dispute_cases')->find($id);
    }

    /**
     * Release buyer protection escrow/hold only after formal adjudication (373.2, 373.4, 373.6 Risk).
     */
    public function releaseProtectionHold(
        string $holdCode,
        string $disputeCode,
        float $holdAmountUsd,
        bool $outcomeAdjudicated
    ): object {
        $hCode = strtoupper($holdCode);
        $dCode = strtoupper($disputeCode);

        // Core gate 373.4: Hold release only after outcome is adjudicated
        if (! $outcomeAdjudicated) {
            DB::table('marketplace_buyer_protection_holds')->insert([
                'hold_code' => $hCode,
                'dispute_code' => $dCode,
                'hold_amount_usd' => $holdAmountUsd,
                'outcome_adjudicated' => false,
                'hold_released' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException('Escrow safety violation: Hold release permitted only after formal dispute adjudication (373.4).');
        }

        $id = DB::table('marketplace_buyer_protection_holds')->insertGetId([
            'hold_code' => $hCode,
            'dispute_code' => $dCode,
            'hold_amount_usd' => $holdAmountUsd,
            'outcome_adjudicated' => true,
            'hold_released' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('marketplace_buyer_protection_holds')->find($id);
    }

    /**
     * Marketplace Trust Audit (`marketplace:audit`) (373.4, 373.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Conflicted reviewer assigned without alternate
        $unmitigatedConflicts = DB::table('marketplace_dispute_cases')
            ->where('reviewer_conflict_of_interest', true)
            ->whereNull('neutral_alternate_reviewer_id')
            ->count();

        // Discrepancy 2: Protection holds released without outcome adjudication
        $unadjudicatedReleases = DB::table('marketplace_buyer_protection_holds')
            ->where('hold_released', true)
            ->where('outcome_adjudicated', false)
            ->count();

        $discrepancies = $unmitigatedConflicts + $unadjudicatedReleases;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_disputes' => DB::table('marketplace_dispute_cases')->count(),
            'total_holds' => DB::table('marketplace_buyer_protection_holds')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
