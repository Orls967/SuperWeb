<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * TenderBidManagementService (Fase 248)
 *
 * Implements:
 *  - 248.1 Enterprise bid desk for cross-line B2B/B2G tenders & deterministic bid/no-bid qualification
 *  - 248.2 Bid cost accounting (engineering, legal, research) & ROI analytics
 *  - 248.3 AI bid agent federation with mandatory human approval before submission
 *  - 248.4 Post-award resource mobilization & first 90 days project health index
 *  - 248.6 Edge case: Tender cancelled by client preserves cost accounting & ROI attribution
 *  - 248.7 Related-party Conflict of Interest (COI) screening before bid evaluation
 */
class TenderBidManagementService
{
    /**
     * Evaluate tender bid qualification with COI screening (248.1, 248.5, 248.7).
     */
    public function evaluateBidQualification(
        string $tenderCode,
        string $clientAgency,
        string $businessLine,
        float $contractEstimateUsd,
        float $strategicFitScore,
        float $marginScore,
        float $riskScore,
        bool $hasRelatedPartyCoi = false
    ): object {
        $code = strtoupper($tenderCode);

        // Deterministic qualification scoring: (fit * 0.4) + (margin * 0.4) - (risk * 0.2)
        $score = round(($strategicFitScore * 0.4) + ($marginScore * 0.4) - ($riskScore * 0.2), 2);

        // COI screening (248.7): COI violation forces NO_BID
        $coiCleared = ! $hasRelatedPartyCoi;
        $verdict = ($score >= 65.0 && $coiCleared) ? 'BID' : 'NO_BID';

        $id = DB::table('tender_bids')->insertGetId([
            'tender_code' => $code,
            'client_agency' => $clientAgency,
            'business_line' => strtoupper($businessLine),
            'contract_estimate_usd' => $contractEstimateUsd,
            'qualification_score' => $score,
            'qualification_verdict' => $verdict,
            'coi_screened' => true,
            'coi_cleared' => $coiCleared,
            'human_approval_status' => 'PENDING',
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('tender_bids')->find($id);
    }

    /**
     * Log preparation cost for tender (248.2).
     */
    public function logBidCost(
        int $tenderId,
        string $costCategory,
        float $amountUsd,
        string $accountingTreatment = 'EXPENSE'
    ): object {
        $id = DB::table('tender_bid_costs')->insertGetId([
            'tender_id' => $tenderId,
            'cost_category' => strtoupper($costCategory),
            'amount_usd' => $amountUsd,
            'accounting_treatment' => strtoupper($accountingTreatment),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('tender_bid_costs')->find($id);
    }

    /**
     * Human approval & submission of bid (248.3 & 248.5).
     */
    public function approveAndSubmitBid(int $tenderId, string $humanApprover): object
    {
        $bid = DB::table('tender_bids')->find($tenderId);
        if (! $bid) {
            throw new InvalidArgumentException("Tender #{$tenderId} not found.");
        }

        if ($bid->qualification_verdict === 'NO_BID') {
            throw new InvalidArgumentException('Cannot submit bid: Tender is disqualified as NO_BID (248.5).');
        }

        if (! $bid->coi_cleared) {
            throw new InvalidArgumentException('Cannot submit bid: Unresolved conflict of interest detected (248.7).');
        }

        DB::table('tender_bids')
            ->where('id', $tenderId)
            ->update([
                'human_approval_status' => 'APPROVED',
                'status' => 'SUBMITTED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('tender_bids')->find($tenderId);
    }

    /**
     * Cancel tender by client post-submission (248.6 Edge Case).
     * Costs remain documented and ROI remains measurable.
     */
    public function cancelTenderByClient(int $tenderId, string $cancellationReason): object
    {
        $bid = DB::table('tender_bids')->find($tenderId);
        if (! $bid) {
            throw new InvalidArgumentException("Tender #{$tenderId} not found.");
        }

        DB::table('tender_bids')
            ->where('id', $tenderId)
            ->update([
                'status' => 'CANCELLED_BY_CLIENT',
                'updated_at' => now(),
            ]);

        return (object) DB::table('tender_bids')->find($tenderId);
    }

    /**
     * Calculate bid cost and ROI (248.2 & 248.6).
     */
    public function calculateBidRoi(int $tenderId): array
    {
        $bid = DB::table('tender_bids')->find($tenderId);
        if (! $bid) {
            throw new InvalidArgumentException("Tender #{$tenderId} not found.");
        }

        $totalCost = (float) DB::table('tender_bid_costs')
            ->where('tender_id', $tenderId)
            ->sum('amount_usd');

        $contractEst = (float) $bid->contract_estimate_usd;
        $costRatioPct = $contractEst > 0 ? round(($totalCost / $contractEst) * 100.0, 2) : 0.0;

        return [
            'tender_id' => $tenderId,
            'tender_code' => $bid->tender_code,
            'status' => $bid->status,
            'contract_estimate_usd' => $contractEst,
            'total_bid_cost_usd' => $totalCost,
            'bid_cost_ratio_pct' => $costRatioPct,
            'is_cost_accounted' => $totalCost > 0,
        ];
    }

    /**
     * Post-award resource mobilization & first 90 days health index (248.4 & 248.5).
     */
    public function mobilizePostAwardProject(
        int $tenderId,
        string $projectCode,
        float $first90DaysChecklistPct
    ): object {
        $healthIndex = 'AT_RISK';
        if ($first90DaysChecklistPct >= 80.0) {
            $healthIndex = 'HEALTHY';
        } elseif ($first90DaysChecklistPct >= 50.0) {
            $healthIndex = 'CAUTION';
        }

        $cleared = ($first90DaysChecklistPct >= 80.0);

        $id = DB::table('tender_post_award_mobilizations')->insertGetId([
            'tender_id' => $tenderId,
            'project_code' => strtoupper($projectCode),
            'first_90_days_checklist_pct' => $first90DaysChecklistPct,
            'project_health_index' => $healthIndex,
            'mobilization_cleared' => $cleared,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('tender_post_award_mobilizations')->find($id);
    }

    /**
     * Tender & Bid Platform Audit (`psv:audit`) (248.5, 248.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Bids submitted without human approval
        $unapprovedSubmissions = DB::table('tender_bids')
            ->where('status', 'SUBMITTED')
            ->where('human_approval_status', '!=', 'APPROVED')
            ->count();

        // Discrepancy 2: Bids with COI breach marked BID verdict
        $compromisedBids = DB::table('tender_bids')
            ->where('coi_cleared', false)
            ->where('qualification_verdict', 'BID')
            ->count();

        // Discrepancy 3: Mobilizations cleared without >= 80% checklist completion
        $unqualifiedMobilizations = DB::table('tender_post_award_mobilizations')
            ->where('mobilization_cleared', true)
            ->where('first_90_days_checklist_pct', '<', 80.0)
            ->count();

        $discrepancies = $unapprovedSubmissions + $compromisedBids + $unqualifiedMobilizations;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_bids' => DB::table('tender_bids')->count(),
            'total_costs' => DB::table('tender_bid_costs')->count(),
            'total_mobilizations' => DB::table('tender_post_award_mobilizations')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
