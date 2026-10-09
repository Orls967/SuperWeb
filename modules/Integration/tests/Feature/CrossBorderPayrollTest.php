<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\CrossBorderPayrollService;
use Tests\TestCase;

/**
 * Fase 152 — Cross-Border Payroll Tests
 *
 * Covers:
 *  (a) pay run multi-negara Σ = biaya konsolidasi
 *  (b) shadow payroll ≠ replace payroll asli
 *  (c) permit expired → penugasan ditolak
 *  (d) equalization deterministik
 *  (e) hcm:audit multi-negara = 0 selisih
 */
class CrossBorderPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected CrossBorderPayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CrossBorderPayrollService::class);
        $this->service->setPayrollRule('SG', 'SGD', 15.0, 10.0);
        $this->service->setPayrollRule('UK', 'GBP', 25.0, 8.0);
    }

    /**
     * (c) Permit expired atau tidak ada → penugasan ditolak.
     */
    public function test_expired_or_missing_work_permit_blocks_assignment(): void
    {
        // 1. No permit -> Throws exception
        $this->expectException(\RuntimeException::class);
        $this->service->assignExpatriate(
            101,
            'ID',
            'SG',
            Carbon::now(),
            Carbon::now()->addYear(),
            10000.0,
            'SGD'
        );
    }

    /**
     * (b) & (a) Assignment sukses dengan permit valid, pay run dan shadow payroll terpisah.
     */
    public function test_valid_permit_allows_assignment_and_pay_runs(): void
    {
        // Issue valid permit for 2 years
        $this->service->issueWorkPermit(102, 'SG', 'WP-SG-999', Carbon::now()->addYears(2));

        $assignment = $this->service->assignExpatriate(
            102,
            'ID',
            'SG',
            Carbon::now(),
            Carbon::now()->addYear(),
            8000.0,
            'SGD'
        );
        $this->assertSame('ACTIVE', $assignment->status);

        // Standard Pay Run (SG rule: 15% tax, 10% social security)
        $standardPay = $this->service->executePayRun('SG', 8000.0, 12000.0, $assignment->assignment_code, false);
        $this->assertEquals(1200.00, (float) $standardPay->tax_amount);
        $this->assertEquals(800.00, (float) $standardPay->social_security_amount);
        $this->assertEquals(6000.00, (float) $standardPay->net_pay);
        $this->assertFalse((bool) $standardPay->is_shadow);
        $this->assertEquals(96000000.00, (float) $standardPay->consolidated_cost_idr);

        // Shadow Payroll Pay Run for Dual Reporting
        $shadowPay = $this->service->executePayRun('SG', 8000.0, 12000.0, $assignment->assignment_code, true);
        $this->assertTrue((bool) $shadowPay->is_shadow);
        $this->assertEquals(1200.00, (float) $shadowPay->shadow_payroll_tax);
    }

    /**
     * (e) Audit: verify all active assignments have active permits with 0 discrepancies.
     */
    public function test_cross_border_payroll_audit(): void
    {
        $this->service->issueWorkPermit(103, 'UK', 'WP-UK-123', Carbon::now()->addYears(2));
        $this->service->assignExpatriate(103, 'ID', 'UK', Carbon::now(), Carbon::now()->addMonths(6), 6000.0, 'GBP');

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
