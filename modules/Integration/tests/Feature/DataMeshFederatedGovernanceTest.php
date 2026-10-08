<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataMeshFederatedGovernanceService;
use Tests\TestCase;

class DataMeshFederatedGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected DataMeshFederatedGovernanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataMeshFederatedGovernanceService::class);
    }

    public function test_data_product_publishing_and_minimum_bar_enforcement(): void
    {
        // 1. Publishing product below minimum quality bar (< 80) is strictly blocked (267.1 & 267.5 Edge Case)
        try {
            $this->service->publishDataProduct(
                productCode: 'DP-SUBPAR-MINE',
                domainName: 'MINING',
                productTitle: 'Raw Ore Telemetry Feed',
                qualityScore: 65.0, // Subpar!
                hasGovernanceTemplate: true
            );
            $this->fail('Expected exception for subpar data product');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('blocked from publishing', $e->getMessage());
        }

        $blockedRow = DB::table('datamesh_domain_products')->where('product_code', 'DP-SUBPAR-MINE')->first();
        $this->assertTrue((bool) $blockedRow->publishing_blocked);
        $this->assertEquals('BLOCKED_POLICY_VIOLATION', $blockedRow->publishing_status);

        // 2. Publishing product meeting bar (quality >= 80 & governance template present) succeeds (267.2 & 267.7)
        $compliantProduct = $this->service->publishDataProduct(
            productCode: 'DP-PURIFIED-NICKEL-01',
            domainName: 'SMELTER',
            productTitle: 'Smelter Purity Spectrometry Product',
            qualityScore: 92.5,
            hasGovernanceTemplate: true
        );
        $this->assertTrue((bool) $compliantProduct->meets_minimum_bar);
        $this->assertEquals('PUBLISHED', $compliantProduct->publishing_status);
        $this->assertFalse((bool) $compliantProduct->publishing_blocked);
    }

    public function test_interoperability_contract_sla_breach_and_credit_compensation(): void
    {
        // 1. Setup contract with 60 min target freshness (267.3)
        $contract = $this->service->createInteroperabilityContract(
            contractCode: 'CTR-DATA-EV-TELEMETRY',
            productCode: 'DP-PURIFIED-NICKEL-01',
            consumerDomain: 'EV_BATTERY_FACTORY',
            targetSlaFreshnessMins: 60
        );
        $this->assertFalse((bool) $contract->is_sla_breached);
        $this->assertEquals(0.0, (float) $contract->compensation_credit_usd);

        // 2. SLA breach: actual 95 mins > 60 min target triggers automatic compensation credit (267.4 & 267.6 Edge Case)
        $breached = $this->service->recordSlaMeasurement('CTR-DATA-EV-TELEMETRY', 95);
        $this->assertTrue((bool) $breached->is_sla_breached);
        $this->assertEquals(250.00, (float) $breached->compensation_credit_usd);
    }

    public function test_data_product_usage_billing_formula_and_compensation_netting(): void
    {
        $this->service->createInteroperabilityContract('CTR-BILL-TEST', 'DP-PRODUCT-X', 'FINANCE', 60);
        $this->service->recordSlaMeasurement('CTR-BILL-TEST', 120); // Triggers $250 credit

        // 10,000 query units consumed @ $0.05/unit = $500.00 gross; minus $250 credit = $250.00 net (267.3 & 267.4)
        $bill = $this->service->billProductUsage('CTR-BILL-TEST', 10000, 0.0500);

        $this->assertEquals(500.0, (float) $bill->gross_amount_usd);
        $this->assertEquals(250.0, (float) $bill->net_billed_amount_usd);
    }

    public function test_data_mesh_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->publishDataProduct('DP-AUD-1', 'TREASURY', 'FX Data', 95.0, true);
        $this->service->createInteroperabilityContract('CTR-AUD-1', 'DP-AUD-1', 'RISK', 60);
        $this->service->recordSlaMeasurement('CTR-AUD-1', 20); // No breach
        $this->service->billProductUsage('CTR-AUD-1', 100, 0.05);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: breached contract without compensation credit
        DB::table('datamesh_interoperability_contracts')->insert([
            'contract_code' => 'CTR-BREACH-UNCOMPENSATED',
            'product_code' => 'DP-AUD-1',
            'consumer_domain' => 'LOGISTICS',
            'target_sla_freshness_mins' => 60,
            'actual_freshness_mins' => 180,
            'is_sla_breached' => true,
            'compensation_credit_usd' => 0.0, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
