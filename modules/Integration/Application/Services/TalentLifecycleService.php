<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TalentLifecycleService (Fase 225)
 *
 * Implements:
 *  - 225.1 Recruitment pipeline & consent verification
 *  - 225.3 Day-1 scoped access provisioning and onboarding progression gates
 *  - 225.4 Offboarding checklist & timely access deprovisioning
 *  - 225.5 Asset return gate blocking final settlement disbursement until clearance
 *  - 225.6 Edge case: Documented background check failure with data retention compliance
 *  - 225.7 Documented rehire eligibility policy strictly blocking blacklisted candidates
 */
class TalentLifecycleService
{
    /**
     * Source candidate with consent verification (225.1 & 225.2).
     */
    public function sourceCandidate(
        string $reqCode,
        string $fullName,
        float $skillScore,
        bool $consentGiven = true,
        string $rehireEligibility = 'ELIGIBLE'
    ): object {
        if (! $consentGiven) {
            throw new \InvalidArgumentException('Candidate consent required for processing application data.');
        }

        $code = 'CAND-'.strtoupper(Str::random(8));

        $id = DB::table('hcm_candidates')->insertGetId([
            'candidate_code' => $code,
            'requisition_code' => strtoupper($reqCode),
            'full_name' => $fullName,
            'skill_match_score' => $skillScore,
            'consent_given' => true,
            'background_check_status' => 'PENDING',
            'background_check_notes' => null,
            'rehire_eligibility' => strtoupper($rehireEligibility),
            'status' => 'SOURCED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_candidates')->find($id);
    }

    /**
     * Record background check decision (225.6 Edge Case: Failures documented & retained).
     */
    public function recordBackgroundCheck(string $candCode, bool $passed, ?string $notes = null): object
    {
        $candidate = DB::table('hcm_candidates')->where('candidate_code', strtoupper($candCode))->first();
        if (! $candidate) {
            throw new \InvalidArgumentException("Candidate {$candCode} not found.");
        }

        if (! $passed) {
            DB::table('hcm_candidates')->where('candidate_code', strtoupper($candCode))->update([
                'background_check_status' => 'FAILED',
                'background_check_notes' => $notes ?? 'Failed background verification screening',
                'status' => 'REJECTED',
                'updated_at' => now(),
            ]);
        } else {
            DB::table('hcm_candidates')->where('candidate_code', strtoupper($candCode))->update([
                'background_check_status' => 'PASSED',
                'background_check_notes' => $notes,
                'status' => 'SCREENED',
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('hcm_candidates')->where('candidate_code', strtoupper($candCode))->first();
    }

    /**
     * Extend and accept offer enforcing rehire eligibility and background clearance (225.7).
     */
    public function acceptCandidateOffer(string $candCode): object
    {
        $candidate = DB::table('hcm_candidates')->where('candidate_code', strtoupper($candCode))->first();
        if (! $candidate) {
            throw new \InvalidArgumentException("Candidate {$candCode} not found.");
        }

        // Rehire eligibility check (225.7)
        if ($candidate->rehire_eligibility === 'INELIGIBLE_BLACKLIST') {
            throw new \InvalidArgumentException("Offer rejected: Candidate {$candCode} is blacklisted from rehire.");
        }

        if ($candidate->background_check_status === 'FAILED') {
            throw new \RuntimeException("Offer rejected: Candidate {$candCode} failed background check.");
        }

        DB::table('hcm_candidates')->where('candidate_code', strtoupper($candCode))->update([
            'status' => 'ACCEPTED',
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_candidates')->where('candidate_code', strtoupper($candCode))->first();
    }

    /**
     * Initialize onboarding & day-1 scoped access provisioning (225.3).
     */
    public function initializeOnboarding(string $employeeId, array $accessScopes = ['ERP_CORE']): void
    {
        $stages = ['PRE_DAY', 'DAY_1_PROVISIONING', 'TRAINING_PATH', 'PROBATION_REVIEW'];
        foreach ($stages as $st) {
            DB::table('hcm_onboarding_tasks')->insert([
                'employee_id' => strtoupper($employeeId),
                'task_name' => "Task for {$st}",
                'stage' => $st,
                'is_completed' => false,
                'completed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Day-1 Access Provisioning
        DB::table('hcm_employee_access_provisioning')->updateOrInsert(
            ['employee_id' => strtoupper($employeeId)],
            [
                'system_access_active' => true,
                'access_scopes' => json_encode($accessScopes),
                'deprovisioned_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Complete specific onboarding task stage.
     */
    public function completeOnboardingStage(string $employeeId, string $stage): void
    {
        DB::table('hcm_onboarding_tasks')
            ->where('employee_id', strtoupper($employeeId))
            ->where('stage', strtoupper($stage))
            ->update([
                'is_completed' => true,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Check if onboarding gate before independent shift is cleared (225.5).
     */
    public function isOnboardingGateCleared(string $employeeId): bool
    {
        $day1 = DB::table('hcm_onboarding_tasks')
            ->where('employee_id', strtoupper($employeeId))
            ->where('stage', 'DAY_1_PROVISIONING')
            ->where('is_completed', true)
            ->exists();

        $training = DB::table('hcm_onboarding_tasks')
            ->where('employee_id', strtoupper($employeeId))
            ->where('stage', 'TRAINING_PATH')
            ->where('is_completed', true)
            ->exists();

        return $day1 && $training;
    }

    /**
     * Initiate offboarding checklist.
     */
    public function initiateOffboarding(string $employeeId, string $resignationDate): object
    {
        $code = 'OFF-'.strtoupper(Str::random(8));

        $id = DB::table('hcm_offboarding_checklists')->insertGetId([
            'offboarding_code' => $code,
            'employee_id' => strtoupper($employeeId),
            'resignation_date' => $resignationDate,
            'knowledge_transferred' => false,
            'assets_returned' => false,
            'account_deprovisioned' => false,
            'final_settlement_paid' => false,
            'final_settlement_amount' => 0,
            'status' => 'INITIATED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_offboarding_checklists')->find($id);
    }

    /**
     * Record physical asset return.
     */
    public function returnAssets(string $offboardingCode): void
    {
        DB::table('hcm_offboarding_checklists')
            ->where('offboarding_code', strtoupper($offboardingCode))
            ->update([
                'assets_returned' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Deprovision employee system access in timely fashion (225.4 & 225.5).
     */
    public function deprovisionAccess(string $employeeId): void
    {
        DB::table('hcm_employee_access_provisioning')
            ->where('employee_id', strtoupper($employeeId))
            ->update([
                'system_access_active' => false,
                'deprovisioned_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('hcm_offboarding_checklists')
            ->where('employee_id', strtoupper($employeeId))
            ->update([
                'account_deprovisioned' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Disburse final settlement gated by asset return (225.5 Asset Return Gate).
     */
    public function processFinalSettlement(string $offboardingCode, float $amount): object
    {
        $checklist = DB::table('hcm_offboarding_checklists')
            ->where('offboarding_code', strtoupper($offboardingCode))
            ->first();

        if (! $checklist) {
            throw new \InvalidArgumentException("Offboarding checklist {$offboardingCode} not found.");
        }

        // 225.5 Asset return gate final pay
        if (! $checklist->assets_returned) {
            throw new \RuntimeException(
                "Final settlement gate violation: Assets must be returned before final settlement can be disbursed for {$offboardingCode}."
            );
        }

        DB::table('hcm_offboarding_checklists')
            ->where('offboarding_code', strtoupper($offboardingCode))
            ->update([
                'final_settlement_paid' => true,
                'final_settlement_amount' => $amount,
                'status' => 'COMPLETED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('hcm_offboarding_checklists')->where('offboarding_code', strtoupper($offboardingCode))->first();
    }

    /**
     * Quality audit gate (`hcm:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Completed offboardings where employee access remains active
        $lingeringAccess = DB::table('hcm_offboarding_checklists as o')
            ->join('hcm_employee_access_provisioning as a', 'o.employee_id', '=', 'a.employee_id')
            ->where('o.status', 'COMPLETED')
            ->where('a.system_access_active', true)
            ->count();

        // Discrepancy 2: Final settlement paid without returned assets
        $unreturnedAssetSettlements = DB::table('hcm_offboarding_checklists')
            ->where('final_settlement_paid', true)
            ->where('assets_returned', false)
            ->count();

        // Discrepancy 3: Blacklisted candidates accepted
        $blacklistedAccepted = DB::table('hcm_candidates')
            ->where('rehire_eligibility', 'INELIGIBLE_BLACKLIST')
            ->where('status', 'ACCEPTED')
            ->count();

        $discrepancies = $lingeringAccess + $unreturnedAssetSettlements + $blacklistedAccepted;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_candidates' => DB::table('hcm_candidates')->count(),
            'total_offboardings' => DB::table('hcm_offboarding_checklists')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
