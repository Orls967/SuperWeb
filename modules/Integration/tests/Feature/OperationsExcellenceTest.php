<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\OperationsExcellenceService;
use Tests\TestCase;

class OperationsExcellenceTest extends TestCase
{
    use RefreshDatabase;

    protected OperationsExcellenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OperationsExcellenceService::class);
    }

    public function test_dmaic_project_creation_and_finance_savings_verification(): void
    {
        // 1. Create Lean Six Sigma DMAIC project (276.1)
        $proj = $this->service->createDmaicProject(
            projectCode: 'DMAIC-SMELTER-SLAG-01',
            title: 'Nickel Smelter Slag Recovery Optimization',
            ownerLeadId: 'BLACK_BELT_IRFAN',
            baselineMetric: 18.5,
            targetMetric: 9.2,
            claimedSavingsUsd: 1200000.0
        );
        $this->assertEquals('DEFINE', $proj->dmaic_stage);
        $this->assertFalse((bool) $proj->is_finance_verified);

        // 2. Unverified or 0 savings rejected by Finance (276.6)
        try {
            $this->service->verifyCiSavings('DMAIC-SMELTER-SLAG-01', 'FIN_CONTROLLER_DEWI', 0.0);
            $this->fail('Expected exception for unverified savings');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('without audited ledger proof cannot be recognized', $e->getMessage());
        }

        // 3. Finance verifies audited ledger savings -> reaches CONTROL stage (276.4 & 276.6)
        $verified = $this->service->verifyCiSavings('DMAIC-SMELTER-SLAG-01', 'FIN_CONTROLLER_DEWI', 1150000.0);
        $this->assertEquals('CONTROL', $verified->dmaic_stage);
        $this->assertTrue((bool) $verified->is_finance_verified);
        $this->assertEquals(1150000.0, (float) $verified->verified_financial_savings_usd);
    }

    public function test_kpi_tree_objective_colors_and_counter_metric_anti_gaming(): void
    {
        // 1. Normal green KPI with low complaint rate (276.2)
        $normalKpi = $this->service->recordOperationalKpi(
            kpiCode: 'KPI-PLANT-UPTIME',
            domainLine: 'SMELTER',
            kpiName: 'Smelter Furnace Uptime Pct',
            currentValue: 98.5,
            targetValue: 95.0,
            customerComplaintRatePct: 0.5
        );
        $this->assertEquals('GREEN', $normalKpi->status_color);
        $this->assertFalse((bool) $normalKpi->is_gaming_flagged);

        // 2. Gaming detected: KPI appears green but customer complaints spike > 5% (276.7 Counter-metric)
        $gamedKpi = $this->service->recordOperationalKpi(
            kpiCode: 'KPI-CALLCENTER-SPEED',
            domainLine: 'CUSTOMER_SERVICE',
            kpiName: 'Call Handle Time Under 60s',
            currentValue: 100.0,
            targetValue: 90.0,
            customerComplaintRatePct: 14.5 // High complaints!
        );
        $this->assertEquals('GREEN', $gamedKpi->status_color);
        $this->assertTrue((bool) $gamedKpi->is_gaming_flagged);
    }

    public function test_standard_work_library_and_site_variance_workflow(): void
    {
        // 1. Register standard work (276.3)
        $std = $this->service->registerStandardWork('STD-HAUL-TRUCK-INSPECT', 'Pre-shift Haul Truck Inspection', 15);
        $this->assertEquals(15, (int) $std->adopting_sites_count);

        // 2. Request site variance with formal justification and Ops Head approval (276.5 Edge Case)
        $variance = $this->service->requestSiteVariance(
            standardCode: 'STD-HAUL-TRUCK-INSPECT',
            siteCode: 'SITE-HALMAHERA-EAST',
            justification: 'Extreme mud terrain requires specialized additional tire pressure calibration',
            approvedByOpsHead: 'VP_OPS_PRATAMA'
        );
        $this->assertTrue((bool) $variance->is_variance_approved);
        $this->assertEquals('VP_OPS_PRATAMA', $variance->approved_by_ops_head);
    }

    public function test_operations_excellence_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createDmaicProject('DMAIC-AUD', 'Audited CI', 'LEAD_1', 10.0, 5.0, 100.0);
        $this->service->verifyCiSavings('DMAIC-AUD', 'FIN_1', 100.0);
        $this->service->recordOperationalKpi('KPI-AUD', 'MINING', 'Yield', 10.0, 10.0, 0.0);
        $this->service->registerStandardWork('STD-AUD', 'Std');
        $this->service->requestSiteVariance('STD-AUD', 'SITE-1', 'Justification', 'VP_1');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved site variance
        DB::table('operations_site_standard_variances')->insert([
            'variance_code' => 'VAR-SECRET-BREACH',
            'standard_code' => 'STD-AUD',
            'site_code' => 'SITE-UNAUTHORIZED',
            'variance_justification' => 'Secret deviation',
            'is_variance_approved' => false, // Discrepancy!
            'approved_by_ops_head' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
