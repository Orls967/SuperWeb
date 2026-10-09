<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataValueCostTransparencyService;
use Tests\TestCase;

class DataValueCostTransparencyTest extends TestCase
{
    use RefreshDatabase;

    protected DataValueCostTransparencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataValueCostTransparencyService::class);
    }

    public function test_query_cost_tracking_and_budget_surge_alert_edge_case(): void
    {
        // 1. Query within budget ($50 <= $500) doesn't alert (342.2 & 342.4)
        $q1 = $this->service->trackQueryCost(
            trackingCode: 'TRACK-QUERY-001',
            domainName: 'MINING',
            consumerId: 'ANALYST-MINE-01',
            queryCostUsd: 50.0,
            domainBudgetUsd: 500.0
        );
        $this->assertEquals(50.0, (float) $q1->month_to_date_cost_usd);
        $this->assertFalse((bool) $q1->budget_alert_triggered);

        // 2. Query pushing domain cost over budget triggers budget alert (342.5 Edge Case)
        $q2 = $this->service->trackQueryCost(
            trackingCode: 'TRACK-QUERY-002',
            domainName: 'MINING',
            consumerId: 'ANALYST-MINE-02',
            queryCostUsd: 480.0, // 50 + 480 = 530 > 500
            domainBudgetUsd: 500.0
        );
        $this->assertEquals(530.0, (float) $q2->month_to_date_cost_usd);
        $this->assertTrue((bool) $q2->budget_alert_triggered);
    }

    public function test_business_value_attribution_finance_review_guard(): void
    {
        // 1. Value attribution without Finance approval throws exception (342.3, 342.4, 342.6 Risk)
        try {
            $this->service->attributeBusinessValue(
                attributionCode: 'ATTR-UNAPPROVED-01',
                datasetName: 'DATASET-PREDICTIVE-MAINTENANCE-HAUL',
                useCase: 'Haul truck unprogrammed downtime reduction',
                method: 'CONSERVATIVE_COST_AVOIDANCE',
                attributedValueUsd: 1200000.0,
                financeApproved: false // Not approved!
            );
            $this->fail('Expected exception for unapproved value attribution');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Data value attribution cannot be used for investment decisions without Finance methodology sign-off', $e->getMessage());
        }

        // 2. Approved attribution succeeds (342.3 & 342.4)
        $attr = $this->service->attributeBusinessValue(
            attributionCode: 'ATTR-APPROVED-02',
            datasetName: 'DATASET-PREDICTIVE-MAINTENANCE-HAUL',
            useCase: 'Haul truck downtime reduction',
            method: 'CONSERVATIVE_COST_AVOIDANCE',
            attributedValueUsd: 850000.0,
            financeApproved: true
        );
        $this->assertEquals(850000.0, (float) $attr->attributed_value_usd);
        $this->assertTrue((bool) $attr->finance_reviewed_and_approved);
    }

    public function test_data_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->trackQueryCost('T-AUD', 'HR', 'EMP-1', 10.0, 100.0);
        $this->service->attributeBusinessValue('A-AUD', 'DATASET-1', 'Use Case', 'METHOD', 1000.0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved attribution
        DB::table('data_asset_business_value_attributions')->insert([
            'attribution_code' => 'ATTR-DEFECT-UNAPPROVED',
            'dataset_name' => 'DATASET-X',
            'use_case_title' => 'Title',
            'attribution_method' => 'METHOD',
            'attributed_value_usd' => 50000.0,
            'finance_reviewed_and_approved' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
