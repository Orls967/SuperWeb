<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\BmtMicrofinanceService;
use Tests\TestCase;

/**
 * Fase 163 — Microfinance, BMT & Economic Empowerment Tests
 *
 * Covers:
 *  (a) waterfall angsuran deterministik
 *  (b) group liability tercatat benar
 *  (c) NDVI gate pencairan dihormati
 *  (d) impact metrics = agregasi data nyata
 *  (e) syb:audit = 0 selisih
 */
class BmtMicrofinanceTest extends TestCase
{
    use RefreshDatabase;

    protected BmtMicrofinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BmtMicrofinanceService::class);
    }

    /**
     * (b) Group liability (tanggung renteng).
     */
    public function test_group_facility_and_liability(): void
    {
        $group = $this->service->createGroupFacility('Kelompok Tani Berkah', 5, 25000000.00);

        $this->assertSame(5, $group->total_members);
        $this->assertEquals(25000000.00, (float) $group->total_facility_amount);
        $this->assertTrue((bool) $group->group_liability_active);
    }

    /**
     * (a) Waterfall auto-deduction from gig worker payout.
     */
    public function test_gig_worker_payout_waterfall(): void
    {
        $loan = $this->service->issueGigMicrocredit(901, 1000000.00, 20.0); // Rp 1,000,000 loan, 20% deduct

        // Worker earns Rp 2,000,000 payout
        // 20% deduct = Rp 400,000. Net to worker = Rp 1,600,000. Remaining loan = Rp 600,000.
        $res = $this->service->processPayoutWaterfall($loan->loan_code, 2000000.00);

        $this->assertEquals(400000.00, $res['auto_deducted']);
        $this->assertEquals(1600000.00, $res['net_payout']);
        $this->assertEquals(600000.00, $res['new_remaining_loan']);
    }

    /**
     * (c) NDVI satellite gate enforcement for farmer milestone disbursement.
     */
    public function test_farmer_ndvi_gate_enforcement(): void
    {
        // Seeding milestone requires NDVI >= 0.200. Measured: 0.150 -> NOT disbursed
        $blocked = $this->service->disburseFarmerMilestone('FARMER-SOLO-01', 'SEEDING', 5000000.00, 0.200, 0.150);
        $this->assertFalse((bool) $blocked->disbursed);

        // Vegetative milestone requires NDVI >= 0.400. Measured: 0.450 -> Disbursed!
        $passed = $this->service->disburseFarmerMilestone('FARMER-SOLO-01', 'VEGETATIVE', 7000000.00, 0.400, 0.450);
        $this->assertTrue((bool) $passed->disbursed);
    }

    /**
     * (d) Social impact metrics reporting.
     */
    public function test_social_impact_metrics(): void
    {
        $impact = $this->service->recordSocialImpact('2026', 1500, 420, 85);

        $this->assertSame(1500, $impact->beneficiaries_count);
        $this->assertSame(420, $impact->jobs_created_count);
        $this->assertSame(85, $impact->msme_graduated_count);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_bmt_microfinance_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
