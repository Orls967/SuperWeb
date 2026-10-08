<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnergyDatacenterResilienceService;
use Tests\TestCase;

class EnergyDatacenterResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected EnergyDatacenterResilienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnergyDatacenterResilienceService::class);
    }

    public function test_data_residency_constraint_enforcement(): void
    {
        // 1. Placement breaching data residency fails (383.1 & 383.4)
        try {
            $this->service->placeWorkload(
                workloadCode: 'WKL-HEALTH-RECORDS-01',
                targetJurisdiction: 'US-EAST',
                dataResidencyJurisdiction: 'ID-JAKARTA', // Sovereign mismatch!
                isCriticalPriority: true
            );
            $this->fail('Expected exception for data residency breach');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('breaches statutory residency', $e->getMessage());
        }

        // 2. Sovereign compliant placement succeeds (383.1 & 383.4)
        $placed = $this->service->placeWorkload(
            workloadCode: 'WKL-HEALTH-RECORDS-02',
            targetJurisdiction: 'ID-JAKARTA',
            dataResidencyJurisdiction: 'ID-JAKARTA',
            isCriticalPriority: true
        );
        $this->assertEquals('PLACED', $placed->placement_status);
        $this->assertTrue((bool) $placed->residency_constraint_enforced);
        $this->assertTrue((bool) $placed->is_critical_priority);
    }

    public function test_grid_emergency_critical_shelter_and_throttling_notice_edge_case(): void
    {
        // 1. Emergency without sheltering critical workloads fails (383.2, 383.4, 383.5 Edge Case)
        try {
            $this->service->triggerGridResiliencePlan(
                eventCode: 'EVT-GRID-BLACKOUT-01',
                gridStatus: 'EMERGENCY',
                criticalSheltered: false, // Critical unsheltered!
                nonCriticalThrottledWithNotice: true
            );
            $this->fail('Expected exception for failure to shelter critical workloads during emergency');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires critical workloads to be sheltered', $e->getMessage());
        }

        // 2. Proper resilience plan sheltering critical & throttling non-critical with notice succeeds (383.5)
        $plan = $this->service->triggerGridResiliencePlan(
            eventCode: 'EVT-GRID-BLACKOUT-02',
            gridStatus: 'EMERGENCY',
            criticalSheltered: true,
            nonCriticalThrottledWithNotice: true
        );
        $this->assertTrue((bool) $plan->critical_workloads_sheltered);
        $this->assertTrue((bool) $plan->non_critical_throttled_with_notice);
    }

    public function test_egy_and_tlx_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->placeWorkload('W-AUD', 'ID', 'ID', true);
        $this->service->triggerGridResiliencePlan('E-AUD', 'NORMAL', true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: placed workload violating residency
        DB::table('global_data_center_workload_placements')->insert([
            'workload_code' => 'W-DEFECT-RESIDENCY',
            'target_jurisdiction' => 'US',
            'data_residency_jurisdiction' => 'ID',
            'residency_constraint_enforced' => false, // Discrepancy!
            'is_critical_priority' => false,
            'placement_status' => 'PLACED', // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
