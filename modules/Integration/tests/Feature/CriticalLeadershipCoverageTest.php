<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CriticalLeadershipCoverageService;
use Tests\TestCase;

class CriticalLeadershipCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected CriticalLeadershipCoverageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CriticalLeadershipCoverageService::class);
    }

    public function test_critical_role_dependency_and_organizational_risk_flag(): void
    {
        // 1. Critical role with single person dependency is automatically marked as organizational risk (324.1 & 324.6 Risk)
        $role = $this->service->registerCriticalRole(
            roleCode: 'ROLE-HEAD-SMELTER-METALLURGY',
            siteCode: 'SITE-SMELTER-WEDA',
            currentHolderId: 'DR_IRFAN_METALLURGY',
            hasDependency: true
        );
        $this->assertTrue((bool) $role->has_single_person_dependency);
        $this->assertTrue((bool) $role->marked_as_organizational_risk);
    }

    public function test_acting_appointment_qualification_and_duration_bounding(): void
    {
        $this->service->registerCriticalRole('ROLE-MINE-SAFETY-CHIEF', 'SITE-MINE', 'HOLDER_01', true);

        // 1. Unqualified candidate (low readiness score < 80) is rejected (324.4)
        try {
            $this->service->appointActingSuccessor(
                appointmentCode: 'ACT-UNQUALIFIED',
                roleCode: 'ROLE-MINE-SAFETY-CHIEF',
                candidateId: 'CAND_JUNIOR',
                readinessScore: 65.0,
                certified: true,
                consentGranted: true,
                durationDays: 60
            );
            $this->fail('Expected exception for unqualified acting candidate');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Successor qualification breach: Candidate lacks required readiness score', $e->getMessage());
        }

        // 2. Duration exceeding 90 days is rejected (324.4)
        try {
            $this->service->appointActingSuccessor(
                appointmentCode: 'ACT-OVER-90-DAYS',
                roleCode: 'ROLE-MINE-SAFETY-CHIEF',
                candidateId: 'CAND_SENIOR',
                readinessScore: 88.0,
                certified: true,
                consentGranted: true,
                durationDays: 120 // > 90 days!
            );
            $this->fail('Expected exception for excessive duration');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Time-bounding breach: Acting appointment cannot exceed 90 days', $e->getMessage());
        }

        // 3. Fully qualified candidate within 90 days succeeds (324.2 & 324.4)
        $acting = $this->service->appointActingSuccessor(
            appointmentCode: 'ACT-SENIOR-OK',
            roleCode: 'ROLE-MINE-SAFETY-CHIEF',
            candidateId: 'CAND_SENIOR',
            readinessScore: 88.0,
            certified: true,
            consentGranted: true,
            durationDays: 60
        );
        $this->assertEquals(60, $acting->max_appointment_duration_days);
        $this->assertTrue((bool) $acting->board_committee_approved);
    }

    public function test_external_search_fallback(): void
    {
        $this->service->registerCriticalRole('ROLE-EXP-CHIEF', 'SITE-1', 'H1', true);

        // Edge case 324.5: Trigger external recruitment when internal pool is exhausted
        $updated = $this->service->triggerExternalSearch('ROLE-EXP-CHIEF', 'Zero internal deputies available in market segment');
        $this->assertEquals('EXTERNAL_RECRUITMENT_AUTHORIZED', $updated->external_hire_search_status);
    }

    public function test_hcm_coverage_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerCriticalRole('ROLE-AUD', 'SITE-AUD', 'H1', false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: single-person dependency not marked as organizational risk
        DB::table('critical_leadership_role_coverages')->insert([
            'role_code' => 'ROLE-DEFECT-UNFLAGGED',
            'site_code' => 'SITE-X',
            'current_holder_id' => 'H2',
            'has_single_person_dependency' => true,
            'marked_as_organizational_risk' => false, // Discrepancy!
            'external_hire_search_status' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
