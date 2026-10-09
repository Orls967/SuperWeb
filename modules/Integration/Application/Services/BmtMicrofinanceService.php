<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BmtMicrofinanceService (Fase 163 — Lini 19)
 *
 * Implements:
 *  - 163.1 BMT cooperative group financing with mutual group liability (tanggung renteng)
 *  - 163.2 Gig worker microcredit with waterfall auto-deduct from payout
 *  - 163.3 Farmer microfinance milestone disbursement strictly guarded by NDVI
 *  - 163.5 Aggregation of measurable social impact metrics
 */
class BmtMicrofinanceService
{
    /**
     * Create group microfinance facility.
     */
    public function createGroupFacility(string $groupName, int $members, float $amount): object
    {
        $code = 'GRP-BMT-'.strtoupper(Str::random(6));

        $id = DB::table('bmt_group_financings')->insertGetId([
            'group_code' => $code,
            'group_name' => $groupName,
            'total_members' => $members,
            'total_facility_amount' => $amount,
            'outstanding_balance' => $amount,
            'group_liability_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('bmt_group_financings')->find($id);
    }

    /**
     * Create gig worker microcredit.
     */
    public function issueGigMicrocredit(int $workerId, float $amount, float $deductPct = 20.0): object
    {
        $code = 'GIG-'.strtoupper(Str::random(8));

        $id = DB::table('bmt_gig_microcredits')->insertGetId([
            'loan_code' => $code,
            'worker_id' => $workerId,
            'principal_amount' => $amount,
            'remaining_amount' => $amount,
            'waterfall_deduct_pct' => $deductPct,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('bmt_gig_microcredits')->find($id);
    }

    /**
     * Waterfall auto-deduction from gig worker payout.
     */
    public function processPayoutWaterfall(string $loanCode, float $incomingPayout): array
    {
        $loan = DB::table('bmt_gig_microcredits')->where('loan_code', $loanCode)->first();
        $deductPct = (float) ($loan->waterfall_deduct_pct ?? 20.0);
        $remaining = (float) ($loan->remaining_amount ?? 0.0);

        $deduction = min($remaining, round($incomingPayout * ($deductPct / 100.0), 2));
        $netPayoutToWorker = round($incomingPayout - $deduction, 2);
        $newRemaining = round($remaining - $deduction, 2);

        DB::table('bmt_gig_microcredits')->where('loan_code', $loanCode)->update([
            'remaining_amount' => $newRemaining,
            'status' => $newRemaining <= 0 ? 'PAID_OFF' : 'ACTIVE',
            'updated_at' => now(),
        ]);

        return [
            'loan_code' => $loanCode,
            'incoming_payout' => $incomingPayout,
            'auto_deducted' => $deduction,
            'net_payout' => $netPayoutToWorker,
            'new_remaining_loan' => $newRemaining,
        ];
    }

    /**
     * Disburse farmer milestone only if satellite NDVI threshold is met.
     */
    public function disburseFarmerMilestone(string $farmerId, string $milestone, float $amount, float $minNdvi, float $currentNdvi): object
    {
        $meetsGate = ($currentNdvi >= $minNdvi);

        $id = DB::table('bmt_farmer_milestones')->insertGetId([
            'farmer_id' => $farmerId,
            'milestone_name' => strtoupper($milestone),
            'disbursement_amount' => $amount,
            'required_min_ndvi' => $minNdvi,
            'current_ndvi' => $currentNdvi,
            'disbursed' => $meetsGate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('bmt_farmer_milestones')->find($id);
    }

    /**
     * Record social impact metrics.
     */
    public function recordSocialImpact(string $year, int $beneficiaries, int $jobs, int $msmeGraduated): object
    {
        DB::table('bmt_social_impact_metrics')->updateOrInsert(
            ['period_year' => $year],
            [
                'beneficiaries_count' => $beneficiaries,
                'jobs_created_count' => $jobs,
                'msme_graduated_count' => $msmeGraduated,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('bmt_social_impact_metrics')->where('period_year', $year)->first();
    }

    /**
     * Audit: verify no milestones disbursed below required NDVI.
     */
    public function audit(): array
    {
        $invalidDisbursements = DB::table('bmt_farmer_milestones')
            ->where('disbursed', true)
            ->whereRaw('current_ndvi < required_min_ndvi')
            ->count();

        return [
            'status' => $invalidDisbursements === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_groups' => DB::table('bmt_group_financings')->count(),
            'total_gig_loans' => DB::table('bmt_gig_microcredits')->count(),
            'total_farmer_milestones' => DB::table('bmt_farmer_milestones')->count(),
            'discrepancy_count' => $invalidDisbursements,
        ];
    }
}
