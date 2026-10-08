<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\WorkingCapitalScfService;
use Tests\TestCase;

class WorkingCapitalScfTest extends TestCase
{
    use RefreshDatabase;

    protected WorkingCapitalScfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkingCapitalScfService::class);
    }

    public function test_dynamic_discounting_yield_and_liquidity_pool_exhaustion(): void
    {
        // 1. Discount issued with sufficient liquidity pool is allocated (310.2 & 310.4)
        // $100,000 at 12% annualized yield for 30 days = $1,000 discount savings
        $discount = $this->service->issueDynamicDiscount(
            offerCode: 'DD-SUP-01',
            supplierId: 'SUPPLIER_NICKEL_ORE',
            invoiceAmountUsd: 100000.0,
            annualizedYieldPct: 12.0,
            availableScfPoolLiquidityUsd: 500000.0
        );
        $this->assertEquals(1000.0, (float) $discount->discount_savings_usd);
        $this->assertEquals('ALLOCATED', $discount->allocation_status);

        // 2. Edge case 310.5: When pool is exhausted, explicitly queue pro-rata (not silently rejected)
        $queuedDiscount = $this->service->issueDynamicDiscount(
            offerCode: 'DD-SUP-02',
            supplierId: 'SUPPLIER_BATTERY_PARTS',
            invoiceAmountUsd: 300000.0,
            annualizedYieldPct: 10.0,
            availableScfPoolLiquidityUsd: 50000.0 // Insufficient liquidity pool!
        );
        $this->assertEquals('QUEUED_PRO_RATA', $queuedDiscount->allocation_status);
        $this->assertEquals(2500.0, (float) $queuedDiscount->discount_savings_usd);
    }

    public function test_ar_risk_scoring_and_short_history_conservative_limits(): void
    {
        // 1. Long history customer (24 months, score 85) gets normal limit & low provision (310.3 & 310.4)
        $established = $this->service->evaluateArCreditRisk('CUST-ESTABLISHED-01', 24, 85.0);
        $this->assertEquals('HIGH', $established->confidence_label);
        $this->assertEquals(170000.0, (float) $established->assigned_credit_limit_usd);
        $this->assertEquals(1.00, (float) $established->bad_debt_provision_pct);

        // 2. Short history customer (< 6 months) gets conservative limit ($10k) and higher provision (310.6 Risk)
        $newCustomer = $this->service->evaluateArCreditRisk('CUST-NEW-02', 3, 90.0);
        $this->assertEquals('LOW_CONSERVATIVE', $newCustomer->confidence_label);
        $this->assertEquals(10000.0, (float) $newCustomer->assigned_credit_limit_usd);
        $this->assertEquals(5.00, (float) $newCustomer->bad_debt_provision_pct);
    }

    public function test_working_capital_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->issueDynamicDiscount('DD-AUD', 'SUP', 1000.0, 10.0, 5000.0);
        $this->service->evaluateArCreditRisk('CUST-AUD', 12, 80.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: short history customer with inflated limit
        DB::table('working_capital_ar_risk_scores')->insert([
            'customer_code' => 'CUST-OVEREXPOSED',
            'payment_history_months' => 2,
            'ar_credit_score' => 95.0,
            'confidence_label' => 'LOW_CONSERVATIVE',
            'assigned_credit_limit_usd' => 500000.0, // Discrepancy!
            'bad_debt_provision_pct' => 1.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
