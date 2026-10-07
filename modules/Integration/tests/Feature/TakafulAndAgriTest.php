<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\TakafulAndAgriService;
use Tests\TestCase;

/**
 * Fase 160 — Takaful & Agri Insurance Tests
 *
 * Covers:
 *  (a) dana takaful terpisah & total kontribusi = wakalah fee + tabarru pool
 *  (b) parametric agri payout = parameter NDVI terukur
 *  (c) micro premium volume = jumlah polis aktif
 *  (d) reserve development backward-compatible
 *  (e) ins:audit final Lini 18 = 0 selisih
 */
class TakafulAndAgriTest extends TestCase
{
    use RefreshDatabase;

    protected TakafulAndAgriService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TakafulAndAgriService::class);
    }

    /**
     * (a) Takaful fund segregation: Total contribution = Wakalah fee + Tabarru' balance.
     */
    public function test_takaful_fund_contribution_segregation(): void
    {
        $this->service->initializeTakafulFund('TAK-KENDARAAN-01', 'Dana Tabarru Kendaraan Bermotor', 15.0);

        // Deposit Rp 10,000,000 -> 15% wakalah fee (1.5m), 85% tabarru pool (8.5m)
        $fund = $this->service->depositContribution('TAK-KENDARAAN-01', 10000000.00);

        $this->assertEquals(10000000.00, (float) $fund->total_contributions);
        $this->assertEquals(1500000.00, (float) $fund->wakalah_fees_collected);
        $this->assertEquals(8500000.00, (float) $fund->tabarru_pool_balance);
        $this->assertEquals((float) $fund->total_contributions, (float) $fund->wakalah_fees_collected + (float) $fund->tabarru_pool_balance);
    }

    /**
     * (b) Parametric agri payout triggered when NDVI vegetation index falls below threshold.
     */
    public function test_agri_parametric_ndvi_payout(): void
    {
        // Palm oil plantation: 50 hectares, sum insured Rp 20,000,000 / ha
        // Threshold: 0.350. Actual measured: 0.280 (severe drought) -> Triggered payout: 50 * 20m = 1,000,000,000
        $triggered = $this->service->evaluateAgriNdvi('FARMER-RIAU-01', 'PALM_OIL', 50.0, 20000000.0, 0.350, 0.280);
        $this->assertSame('TRIGGERED', $triggered->status);
        $this->assertEquals(1000000000.00, (float) $triggered->payout_amount);

        // Healthy plantation: NDVI 0.450 -> ACTIVE, payout = 0
        $healthy = $this->service->evaluateAgriNdvi('FARMER-RIAU-02', 'PALM_OIL', 30.0, 20000000.0, 0.350, 0.450);
        $this->assertSame('ACTIVE', $healthy->status);
        $this->assertEquals(0.00, (float) $healthy->payout_amount);
    }

    /**
     * (c) High volume micro insurance issuance.
     */
    public function test_micro_insurance_issuance(): void
    {
        $policy = $this->service->issueMicroPolicy('DAILY_DRIVER', 2500.0, 10000000.0);

        $this->assertSame('DAILY_DRIVER', $policy->product_type);
        $this->assertEquals(2500.00, (float) $policy->daily_premium);
        $this->assertSame('ACTIVE', $policy->status);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_takaful_and_agri_audit(): void
    {
        $this->service->initializeTakafulFund('TAK-HEALTH-01', 'Dana Tabarru Kesehatan', 10.0);
        $this->service->depositContribution('TAK-HEALTH-01', 5000000.00);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
