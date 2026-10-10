<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CorporateVentureIncubationService (Fase 234)
 *
 * Implements:
 *  - 234.1 Venture pipeline, due diligence & stage-gate funding
 *  - 234.2 Shared incubation platform assets usage metering (marketplace, API, logistics)
 *  - 234.3 Portfolio valuation, mark-to-market tracking & kill/scale decisions
 *  - 234.5 Stage funding strictly bounded by approved limits & kill decision revokes data access
 *  - 234.6 Edge case: Internal competition detection enforces non-compete data isolation
 *  - 234.7 Stage gate: Tranche disbursement gated by milestone achievement and formal audit
 */
class CorporateVentureIncubationService
{
    /**
     * Create corporate venture entry (234.1).
     */
    public function createVenture(
        string $ventureCode,
        string $ventureName,
        string $optionType,
        float $initialValuation,
        float $totalApprovedFunding
    ): object {
        $id = DB::table('ppm_corporate_ventures')->insertGetId([
            'venture_code' => strtoupper($ventureCode),
            'venture_name' => $ventureName,
            'option_type' => strtoupper($optionType),
            'funnel_stage' => 'EXPERIMENTS',
            'current_valuation' => $initialValuation,
            'total_approved_funding' => $totalApprovedFunding,
            'disbursed_funding' => 0,
            'shared_platform_access_active' => true,
            'is_internal_competitor' => false,
            'non_compete_isolated' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_corporate_ventures')->find($id);
    }

    /**
     * Create staged funding tranche verifying total limit ceiling (234.1 & 234.5).
     */
    public function createFundingTranche(
        string $ventureCode,
        string $trancheStage,
        float $approvedAmount,
        string $milestoneDescription
    ): object {
        $venture = DB::table('ppm_corporate_ventures')->where('venture_code', strtoupper($ventureCode))->first();
        if (! $venture) {
            throw new \InvalidArgumentException("Venture {$ventureCode} not found.");
        }

        $existingTranchesTotal = (float) DB::table('ppm_venture_funding_tranches')
            ->where('venture_code', $venture->venture_code)
            ->sum('approved_amount');

        // 234.5: Funding staged cannot exceed total approved ceiling
        if (($existingTranchesTotal + $approvedAmount) > (float) $venture->total_approved_funding) {
            $allowed = (float) $venture->total_approved_funding - $existingTranchesTotal;
            throw new \InvalidArgumentException(
                "Staged funding ceiling exceeded: Requested {$approvedAmount} exceeds allowable remaining budget {$allowed} (total approved: {$venture->total_approved_funding})."
            );
        }

        $code = 'TRN-'.strtoupper(Str::random(8));

        $id = DB::table('ppm_venture_funding_tranches')->insertGetId([
            'tranche_code' => $code,
            'venture_code' => $venture->venture_code,
            'tranche_stage' => strtoupper($trancheStage),
            'approved_amount' => $approvedAmount,
            'milestone_description' => $milestoneDescription,
            'milestone_achieved' => false,
            'is_audited' => false,
            'disbursed' => false,
            'disbursed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_venture_funding_tranches')->find($id);
    }

    /**
     * Mark tranche milestone achieved and audited (234.7 Stage Gate).
     */
    public function verifyAndAuditMilestone(string $trancheCode): void
    {
        DB::table('ppm_venture_funding_tranches')
            ->where('tranche_code', strtoupper($trancheCode))
            ->update([
                'milestone_achieved' => true,
                'is_audited' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Disburse funding tranche enforcing stage-gate milestone audit (234.7).
     */
    public function disburseTranche(string $trancheCode): object
    {
        $tranche = DB::table('ppm_venture_funding_tranches')->where('tranche_code', strtoupper($trancheCode))->first();
        if (! $tranche) {
            throw new \InvalidArgumentException("Tranche {$trancheCode} not found.");
        }

        if ($tranche->disbursed) {
            return (object) $tranche;
        }

        // 234.7 Stage gate: Tranche cair hanya bila milestone tercapai & diaudit
        if (! $tranche->milestone_achieved || ! $tranche->is_audited) {
            throw new \RuntimeException(
                "Stage gate enforcement: Tranche {$trancheCode} cannot be disbursed until required milestone is achieved and audited."
            );
        }

        $venture = DB::table('ppm_corporate_ventures')->where('venture_code', $tranche->venture_code)->first();
        if ($venture->funnel_stage === 'KILLED') {
            throw new \RuntimeException("Cannot disburse funding to killed venture {$venture->venture_code}.");
        }

        DB::table('ppm_venture_funding_tranches')
            ->where('tranche_code', strtoupper($trancheCode))
            ->update([
                'disbursed' => true,
                'disbursed_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('ppm_corporate_ventures')
            ->where('venture_code', $tranche->venture_code)
            ->increment('disbursed_funding', $tranche->approved_amount);

        return (object) DB::table('ppm_venture_funding_tranches')->where('tranche_code', strtoupper($trancheCode))->first();
    }

    /**
     * Record metered usage of shared platform assets (234.2).
     */
    public function recordPlatformAssetUsage(
        string $ventureCode,
        string $sharedAssetType,
        float $meteredUnits,
        float $costRate
    ): object {
        $venture = DB::table('ppm_corporate_ventures')->where('venture_code', strtoupper($ventureCode))->first();
        if (! $venture) {
            throw new \InvalidArgumentException("Venture {$ventureCode} not found.");
        }

        // Access check: Cut off if killed or isolated
        if (! $venture->shared_platform_access_active) {
            throw new \RuntimeException(
                "Platform access denied: Venture {$ventureCode} has been data-isolated or terminated."
            );
        }

        $totalCost = round($meteredUnits * $costRate, 2);

        $id = DB::table('ppm_incubation_asset_usage')->insertGetId([
            'venture_code' => $venture->venture_code,
            'shared_asset_type' => strtoupper($sharedAssetType),
            'metered_units' => $meteredUnits,
            'cost_rate' => $costRate,
            'total_shared_cost' => $totalCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_incubation_asset_usage')->find($id);
    }

    /**
     * Enforce non-compete and data isolation for internal competitors (234.6 Edge Case).
     */
    public function isolateInternalCompetitor(string $ventureCode, string $conflictReason): object
    {
        DB::table('ppm_corporate_ventures')
            ->where('venture_code', strtoupper($ventureCode))
            ->update([
                'is_internal_competitor' => true,
                'non_compete_isolated' => true,
                'shared_platform_access_active' => false, // Cut off data and platform assets
                'updated_at' => now(),
            ]);

        return (object) DB::table('ppm_corporate_ventures')->where('venture_code', strtoupper($ventureCode))->first();
    }

    /**
     * Execute kill decision shutting down data access (234.3 & 234.5).
     */
    public function executeKillDecision(string $ventureCode, string $rationale): object
    {
        DB::table('ppm_corporate_ventures')
            ->where('venture_code', strtoupper($ventureCode))
            ->update([
                'funnel_stage' => 'KILLED',
                'shared_platform_access_active' => false, // 234.5: kill decision closes data access
                'updated_at' => now(),
            ]);

        return (object) DB::table('ppm_corporate_ventures')->where('venture_code', strtoupper($ventureCode))->first();
    }

    /**
     * Quality audit gate (`ppm:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Ventures where disbursed funding exceeds approved funding ceiling
        $overfundedVentures = DB::table('ppm_corporate_ventures')
            ->whereRaw('disbursed_funding > total_approved_funding')
            ->count();

        // Discrepancy 2: Tranches disbursed without achieved and audited milestone
        $unauditedDisbursements = DB::table('ppm_venture_funding_tranches')
            ->where('disbursed', true)
            ->where(function ($q) {
                $q->where('milestone_achieved', false)
                    ->orWhere('is_audited', false);
            })
            ->count();

        // Discrepancy 3: Killed or isolated ventures with active platform access
        $improperAccess = DB::table('ppm_corporate_ventures')
            ->where('shared_platform_access_active', true)
            ->where(function ($q) {
                $q->where('funnel_stage', 'KILLED')
                    ->orWhere('non_compete_isolated', true);
            })
            ->count();

        $discrepancies = $overfundedVentures + $unauditedDisbursements + $improperAccess;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_ventures' => DB::table('ppm_corporate_ventures')->count(),
            'total_tranches' => DB::table('ppm_venture_funding_tranches')->count(),
            'total_asset_usage_records' => DB::table('ppm_incubation_asset_usage')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
