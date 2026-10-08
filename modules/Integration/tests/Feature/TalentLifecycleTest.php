<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\TalentLifecycleService;
use Tests\TestCase;

/**
 * Fase 225 — SDM: Talent Acquisition, Onboarding & Offboarding Lifecycle Tests
 *
 * Covers:
 *  (a) Recruitment pipeline & consent verification
 *  (b) Edge Case 225.6: Documented background check failure with data retention
 *  (c) Edge Case 225.7: Blacklist rehire policy enforcement
 *  (d) Onboarding gating before independent shift
 *  (e) Offboarding access deprovisioning and asset return gate before final settlement
 *  (f) Quality audit hcm:audit clean with 0 discrepancies
 */
class TalentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected TalentLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TalentLifecycleService::class);
    }

    /**
     * (a) Sourcing consent requirement (225.2).
     */
    public function test_candidate_sourcing_consent(): void
    {
        // Without consent -> Exception
        try {
            $this->service->sourceCandidate('REQ-ENG-01', 'John Doe', 85.0, false);
            $this->fail('Expected exception when candidate consent is missing.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('consent required', $e->getMessage());
        }

        // With consent -> Sourced
        $cand = $this->service->sourceCandidate('REQ-ENG-01', 'Jane Doe', 92.0, true);
        $this->assertSame('SOURCED', $cand->status);
        $this->assertTrue((bool) $cand->consent_given);
    }

    /**
     * (b) Edge Case 225.6: Documented background check failure.
     */
    public function test_background_check_failure_documented(): void
    {
        $cand = $this->service->sourceCandidate('REQ-MED-01', 'Dr. Smith', 90.0, true);
        $updated = $this->service->recordBackgroundCheck($cand->candidate_code, false, 'Medical license discrepancy found');

        $this->assertSame('FAILED', $updated->background_check_status);
        $this->assertSame('REJECTED', $updated->status);
        $this->assertStringContainsString('license discrepancy', $updated->background_check_notes);

        // Attempting to accept rejected candidate throws exception
        try {
            $this->service->acceptCandidateOffer($cand->candidate_code);
            $this->fail('Expected exception for accepting candidate who failed background check.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('failed background check', $e->getMessage());
        }
    }

    /**
     * (c) Edge Case 225.7: Rehire blacklist enforcement.
     */
    public function test_rehire_blacklist_enforcement(): void
    {
        $cand = $this->service->sourceCandidate(
            'REQ-OPS-01',
            'Former Bad Actor',
            80.0,
            true,
            'INELIGIBLE_BLACKLIST'
        );
        $this->service->recordBackgroundCheck($cand->candidate_code, true);

        try {
            $this->service->acceptCandidateOffer($cand->candidate_code);
            $this->fail('Expected exception for accepting blacklisted candidate.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('blacklisted from rehire', $e->getMessage());
        }
    }

    /**
     * (d) Onboarding gate before independent shift (225.3 & 225.5).
     */
    public function test_onboarding_progression_gate(): void
    {
        $empId = 'EMP-NEW-99';
        $this->service->initializeOnboarding($empId);

        // Initially gate is not cleared
        $this->assertFalse($this->service->isOnboardingGateCleared($empId));

        // Complete Day 1
        $this->service->completeOnboardingStage($empId, 'DAY_1_PROVISIONING');
        $this->assertFalse($this->service->isOnboardingGateCleared($empId));

        // Complete Training Path
        $this->service->completeOnboardingStage($empId, 'TRAINING_PATH');
        $this->assertTrue($this->service->isOnboardingGateCleared($empId));
    }

    /**
     * (e) Offboarding access removal and asset return gate final pay (225.4 & 225.5).
     */
    public function test_offboarding_asset_gate_and_access_deprovisioning(): void
    {
        $empId = 'EMP-OFF-01';
        $this->service->initializeOnboarding($empId);

        $off = $this->service->initiateOffboarding($empId, '2026-10-31');

        // 1. Try paying final settlement before assets returned -> Throws exception
        try {
            $this->service->processFinalSettlement($off->offboarding_code, 35000000);
            $this->fail('Expected exception for paying final settlement before assets returned.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Assets must be returned before final settlement', $e->getMessage());
        }

        // 2. Return physical assets
        $this->service->returnAssets($off->offboarding_code);

        // 3. Deprovision system access
        $this->service->deprovisionAccess($empId);
        $access = DB::table('hcm_employee_access_provisioning')->where('employee_id', $empId)->first();
        $this->assertFalse((bool) $access->system_access_active);
        $this->assertNotNull($access->deprovisioned_at);

        // 4. Now final settlement succeeds
        $completedOff = $this->service->processFinalSettlement($off->offboarding_code, 35000000);
        $this->assertSame('COMPLETED', $completedOff->status);
        $this->assertTrue((bool) $completedOff->final_settlement_paid);
    }

    /**
     * (f) Audit status healthy with 0 discrepancies.
     */
    public function test_talent_lifecycle_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
