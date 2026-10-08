<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\CostCapacityValueGovernanceService;
use Tests\TestCase;

class CostCapacityValueGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected CostCapacityValueGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CostCapacityValueGovernanceService::class);
    }

    public function test_readiness_review_failure_holds_release_edge_case(): void
    {
        // 1. Missing runbook or rollback holds release and throws exception (365.4 & 365.5 Edge Case)
        try {
            $this->service->conductReadinessReview(
                reviewCode: 'REV-HOTEL-BOOKING-V3',
                serviceCode: 'SVC-HOTEL-BOOKING',
                hasRunbook: true,
                hasDashboard: true,
                hasRollbackPlan: false // Missing rollback plan!
            );
            $this->fail('Expected exception for missing rollback plan in readiness review');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Release held until runbook, dashboard, and rollback plan are verified', $e->getMessage());
        }

        // Verify held review recorded
        $held = DB::table('platform_service_readiness_reviews')->where('review_code', 'REV-HOTEL-BOOKING-V3')->first();
        $this->assertNotNull($held);
        $this->assertFalse((bool) $held->readiness_passed);
        $this->assertTrue((bool) $held->release_held);

        // 2. Fully compliant readiness review passes and permits release (365.4 & 365.5)
        $passed = $this->service->conductReadinessReview(
            reviewCode: 'REV-HOTEL-BOOKING-V4',
            serviceCode: 'SVC-HOTEL-BOOKING',
            hasRunbook: true,
            hasDashboard: true,
            hasRollbackPlan: true
        );
        $this->assertTrue((bool) $passed->readiness_passed);
        $this->assertFalse((bool) $passed->release_held);
    }

    public function test_unit_economics_cost_allocation_reconciliation(): void
    {
        // Allocate unit economics: 5,000 model inferences @ $0.0025 each (365.1 & 365.4)
        $alloc = $this->service->allocateUnitEconomics(
            allocationCode: 'ALLOC-MODEL-INFER-MARCH',
            capabilityCode: 'CAP-VISION-OCR',
            usageUnits: 5000.0,
            costPerUnitUsd: 0.0025
        );

        $this->assertEquals(12.50, $alloc->total_allocated_cost_usd);
        $this->assertTrue((bool) $alloc->usage_reconciled);
    }

    public function test_platform_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->allocateUnitEconomics('ALLOC-AUD', 'CAP-1', 100.0, 1.0);
        $this->service->conductReadinessReview('REV-AUD', 'SVC-1', true, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unready service marked as passed
        DB::table('platform_service_readiness_reviews')->insert([
            'review_code' => 'REV-DEFECT-UNREADY',
            'service_code' => 'SVC-DEFECT',
            'has_runbook' => false, // Discrepancy!
            'has_dashboard' => true,
            'has_rollback_plan' => false,
            'readiness_passed' => true, // Discrepancy!
            'release_held' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
