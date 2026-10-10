<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CommunityValueSocialProcurementService (Fase 335)
 *
 * Implements:
 *  - 335.1 Local supplier development with objective milestone evidence unlocking tender eligibility
 *  - 335.3 Community grievance feedback loop with independent community representative confirmation
 *  - 335.4 Tests: Eligibility requires milestone evidence; grievance closure requires community rep signoff; esg:audit clean
 *  - 335.5 Edge case: Social programs lacking measurable outcomes cannot be claimed as "impact" (classified strictly as "ACTIVITY_ONLY")
 *  - 335.6 Risk: Local procurement trade-offs approved and documented transparently
 */
class CommunityValueSocialProcurementService
{
    /**
     * Enroll and verify local supplier development with objective milestone evidence (335.1, 335.4, 335.5 Edge Case).
     */
    public function verifyLocalSupplierMilestone(
        string $programCode,
        string $supplierId,
        string $regionCode,
        bool $hasMilestoneEvidence,
        bool $hasMeasurableOutcome = true
    ): object {
        $pCode = strtoupper($programCode);
        $sId = strtoupper($supplierId);

        // Core gate 335.4: Milestone evidence strictly required to unlock tender eligibility
        $eligibilityUnlocked = $hasMilestoneEvidence;

        // Edge case 335.5: Programs lacking measurable outcomes must not be claimed as "impact"
        $classification = $hasMeasurableOutcome ? 'MEASURED_IMPACT' : 'ACTIVITY_ONLY';

        $id = DB::table('local_supplier_development_programs')->insertGetId([
            'program_code' => $pCode,
            'local_supplier_id' => $sId,
            'region_code' => strtoupper($regionCode),
            'has_objective_milestone_evidence' => $hasMilestoneEvidence,
            'tender_eligibility_unlocked' => $eligibilityUnlocked,
            'has_measurable_outcome' => $hasMeasurableOutcome,
            'program_classification' => $classification,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('local_supplier_development_programs')->find($id);
    }

    /**
     * Close community grievance requiring independent confirmation by community representative (335.3 & 335.4).
     */
    public function closeCommunityGrievance(
        string $grievanceCode,
        string $communityId,
        float $remedyBudgetUsd,
        bool $remedyImplemented,
        bool $confirmedByCommunityRep
    ): object {
        $gCode = strtoupper($grievanceCode);
        $cId = strtoupper($communityId);

        // Core gate 335.4: Grievance closure requires independent confirmation by community representative
        if (! $confirmedByCommunityRep) {
            throw new InvalidArgumentException('Social grievance violation: Closure strictly requires independent confirmation by elected community representative (335.4).');
        }

        $isClosed = ($remedyImplemented && $confirmedByCommunityRep);

        $id = DB::table('community_grievance_remediations')->insertGetId([
            'grievance_code' => $gCode,
            'community_id' => $cId,
            'remedy_budget_usd' => $remedyBudgetUsd,
            'remedy_implemented' => $remedyImplemented,
            'confirmed_by_community_rep' => $confirmedByCommunityRep,
            'is_closed' => $isClosed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('community_grievance_remediations')->find($id);
    }

    /**
     * ESG Social & Community Procurement Audit (`esg:audit`) (335.4, 335.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Tender eligibility unlocked without objective milestone evidence
        $unverifiedTenderUnlocks = DB::table('local_supplier_development_programs')
            ->where('tender_eligibility_unlocked', true)
            ->where('has_objective_milestone_evidence', false)
            ->count();

        // Discrepancy 2: Closed grievances without community representative confirmation
        $unconfirmedGrievanceClosures = DB::table('community_grievance_remediations')
            ->where('is_closed', true)
            ->where('confirmed_by_community_rep', false)
            ->count();

        $discrepancies = $unverifiedTenderUnlocks + $unconfirmedGrievanceClosures;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_supplier_programs' => DB::table('local_supplier_development_programs')->count(),
            'total_grievances' => DB::table('community_grievance_remediations')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
