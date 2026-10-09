<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\MaintenanceReliabilityGovernanceService;
use Tests\TestCase;

class MaintenanceReliabilityGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected MaintenanceReliabilityGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaintenanceReliabilityGovernanceService::class);
    }

    public function test_maintenance_program_and_improvement_verification(): void
    {
        // 413.1 Register program
        $prog = $this->service->registerProgram(
            programCode: 'PM-CRANE-PORT-01',
            assetClass: 'CONTAINER_CRANE',
            criticalityRank: 'A_CRITICAL',
            strategy: 'predictive',
            engineer: 'Lead Mechanical Engineer'
        );

        $this->assertEquals('PM-CRANE-PORT-01', $prog->program_code);
        $this->assertEquals('A_CRITICAL', $prog->criticality_rank);

        // 413.3 Record reliability improvement
        $imp = $this->service->recordReliabilityImprovement(
            programCode: 'PM-CRANE-PORT-01',
            improvementCode: 'IMP-HYDRAULIC-SEAL-01',
            chronicFailureCause: 'Thermal degradation in tropical salt humidity',
            changeDetails: 'Upgraded to fluoroelastomer high-temp seals + automatic greasing',
            verified: true
        );

        $this->assertTrue((bool) $imp->effectiveness_verified);

        // 413.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_backlog_aging_escalation_edge_case(): void
    {
        $this->service->registerProgram(
            programCode: 'PM-TURBINE-002',
            assetClass: 'POWER_TURBINE',
            criticalityRank: 'A_CRITICAL',
            strategy: 'condition_based',
            engineer: 'Turbine Specialist'
        );

        // 413.5 Edge case & 413.6 Risk: Aging backlog (> 30 days) automatically flags escalation
        $updated = $this->service->updatePmCompliance(
            programCode: 'PM-TURBINE-002',
            complianceRate: 72.00, // < 80%
            backlogAgingDays: 45 // > 30 days
        );

        $this->assertTrue((bool) $updated->escalated_to_supervisor);

        // Audit remains healthy because it was properly escalated
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
    }
}
