<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\EthicalSourcingService;
use Tests\TestCase;

/**
 * Fase 154 — Global Ethical Sourcing Tests
 *
 * Covers:
 *  (a) skor sourcing memengaruhi eligibility tender (pelanggaran -> blacklist/ineligible)
 *  (b) living wage gap terhitung & dilaporkan
 *  (c) CoC wajib sebelum PO dikeluarkan
 *  (d) remediation ter-track untuk supplier di bawah standar
 *  (e) esg:audit + supplier:audit = 0 selisih
 */
class EthicalSourcingTest extends TestCase
{
    use RefreshDatabase;

    protected EthicalSourcingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EthicalSourcingService::class);
    }

    /**
     * (a) & (d) Ethical score & zero-tolerance violations dictate tender eligibility.
     */
    public function test_ethical_audit_and_tender_eligibility(): void
    {
        // 1. High ethical supplier -> PASSED, eligible
        $passed = $this->service->auditSupplier('SUP-CLEAN-01', 'MINING', 95.0, false, false);
        $this->assertSame('PASSED', $passed->status);
        $this->assertTrue((bool) $passed->is_tender_eligible);

        // 2. Low score -> REMEDIATION, ineligible until remediated
        $remed = $this->service->auditSupplier('SUP-MEDIOCRE-02', 'GARMENT', 65.0, false, false);
        $this->assertSame('REMEDIATION', $remed->status);
        $this->assertFalse((bool) $remed->is_tender_eligible);
        $this->assertNotNull($remed->remediation_plan);

        // 3. Child labor violation -> BLACKLISTED immediately
        $blacklisted = $this->service->auditSupplier('SUP-ILLEGAL-03', 'PLANTATION', 40.0, true, false);
        $this->assertSame('BLACKLISTED', $blacklisted->status);
        $this->assertFalse((bool) $blacklisted->is_tender_eligible);
    }

    /**
     * (b) Living wage benchmark & gap evaluation.
     */
    public function test_living_wage_benchmark_and_gap_evaluation(): void
    {
        // Set benchmark: Indonesia Rp 4,500,000 / month
        $this->service->setLivingWageBenchmark('ID', 4500000.00, 'IDR');

        // Actual paid: Rp 3,800,000 -> Gap Rp 700,000
        $gapResult = $this->service->evaluateWageGap('ID', 3800000.00);
        $this->assertFalse($gapResult['meets_living_wage']);
        $this->assertEquals(700000.00, $gapResult['monthly_wage_gap']);
        $this->assertEquals(84.44, $gapResult['compliance_pct']);

        // Actual paid: Rp 5,000,000 -> No gap
        $compliantResult = $this->service->evaluateWageGap('ID', 5000000.00);
        $this->assertTrue($compliantResult['meets_living_wage']);
        $this->assertEquals(0.00, $compliantResult['monthly_wage_gap']);
    }

    /**
     * (c) Code of Conduct mandatory before issuing PO.
     */
    public function test_vendor_coc_mandatory_before_purchase_order(): void
    {
        $supplier = 'SUP-TECH-CORP';

        // 1. Unsigned CoC -> cannot issue PO
        $this->assertFalse($this->service->canIssuePurchaseOrder($supplier));

        // 2. Sign CoC -> can issue PO
        $this->service->signVendorCoc($supplier);
        $this->assertTrue($this->service->canIssuePurchaseOrder($supplier));

        // 3. If later blacklisted -> cannot issue PO
        $this->service->auditSupplier($supplier, 'ELECTRONICS', 30.0, false, true);
        $this->assertFalse($this->service->canIssuePurchaseOrder($supplier));
    }

    /**
     * (e) Audit: verify healthy state.
     */
    public function test_ethical_sourcing_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
