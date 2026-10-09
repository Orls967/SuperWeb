<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\SkillsEconomyInternalMobilityService;
use Tests\TestCase;

class SkillsEconomyInternalMobilityTest extends TestCase
{
    use RefreshDatabase;

    protected SkillsEconomyInternalMobilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SkillsEconomyInternalMobilityService::class);
    }

    public function test_gig_creation_and_capacity_guardrails(): void
    {
        // 1. Create gig project: 10 hrs/wk * $80/hr * 4 wks = $3,200 (317.1 & 317.3)
        $gig = $this->service->createGigProject(
            gigCode: 'GIG-EV-TELEMATICS-01',
            projectName: 'EV Telematics Sensor Integration',
            businessUnit: 'FLEET_TECH',
            hoursPerWeek: 10,
            hourlyRateUsd: 80.0,
            durationWeeks: 4
        );
        $this->assertEquals(3200.0, (float) $gig->total_project_fee_usd);

        // 2. Assign employee with normal workload (30 hrs existing + 10 hrs gig = 40 hrs cap) (317.1 & 317.4)
        $assignment = $this->service->assignEmployeeToGig(
            assignmentCode: 'ASN-GIG-01',
            gigCode: 'GIG-EV-TELEMATICS-01',
            employeeId: 'EMP_ENG_FARHAN',
            allocatedHoursPerWeek: 10,
            existingWorkloadHoursPerWeek: 30,
            managerApprovalGranted: true,
            continuityHandoverFiled: true
        );
        $this->assertTrue((bool) $assignment->manager_approval_granted);

        // 3. Excessive workload (35 hrs existing + 10 hrs gig = 45 hrs > 40 hrs cap) is rejected (317.6 Risk)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Capacity overload breach: Combined weekly workload (45 hrs) exceeds 40 hours/week ceiling');
        $this->service->assignEmployeeToGig('ASN-OVERLOAD', 'GIG-EV-TELEMATICS-01', 'EMP_ENG_FARHAN', 10, 35, true, true);
    }

    public function test_gig_to_permanent_conversion_headcount_governance(): void
    {
        $this->service->createGigProject('GIG-EPC-02', 'EPC Plant Audit', 'ENERGY', 5, 100.0, 2);
        $this->service->assignEmployeeToGig('ASN-EPC-02', 'GIG-EPC-02', 'EMP_MAYA', 5, 20, true, true);

        // 1. Permanent conversion without headcount approval is rejected (317.4)
        try {
            $this->service->convertGigToPermanent('ASN-EPC-02', false);
            $this->fail('Expected exception for unapproved headcount conversion');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Permanent talent conversion strictly requires authorized headcount budget approval', $e->getMessage());
        }

        // 2. Permanent conversion with headcount approval succeeds (317.2 & 317.4)
        $converted = $this->service->convertGigToPermanent('ASN-EPC-02', true);
        $this->assertTrue((bool) $converted->is_converted_to_permanent);
        $this->assertTrue((bool) $converted->headcount_approval_for_permanent);
    }

    public function test_critical_talent_reassignment_handover_plan_requirement(): void
    {
        $this->service->createGigProject('GIG-CRITICAL', 'Core Overhaul', 'IT', 10, 50.0, 1);

        // Edge case 317.5: Handover plan mandatory for critical talent reassignment
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reassigning critical talent requires mandatory handover & continuity documentation');
        $this->service->assignEmployeeToGig('ASN-NOHANDOVER', 'GIG-CRITICAL', 'EMP_LEAD', 10, 20, true, false);
    }

    public function test_hcm_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createGigProject('GIG-AUD', 'Audit Gig', 'FIN', 5, 50.0, 1);
        $this->service->assignEmployeeToGig('ASN-AUD', 'GIG-AUD', 'E1', 5, 20, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: permanent conversion without headcount approval in DB
        DB::table('internal_talent_assignments')->insert([
            'assignment_code' => 'ASN-DEFECT-CONVERSION',
            'gig_code' => 'GIG-AUD',
            'employee_id' => 'EMP_ROGUE',
            'employee_allocated_hours_per_week' => 5,
            'max_weekly_capacity_cap' => 40,
            'manager_approval_granted' => true,
            'continuity_handover_plan_filed' => true,
            'is_converted_to_permanent' => true,
            'headcount_approval_for_permanent' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
