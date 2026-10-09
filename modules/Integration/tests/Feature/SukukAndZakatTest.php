<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\SukukAndZakatService;
use Tests\TestCase;

/**
 * Fase 162 — Sukuk, Ijarah & Wealth Syariah Tests
 *
 * Covers:
 *  (a) sukuk distribution = expected coupon rate schedule
 *  (b) zakat = basis × rate 2.5% terverifikasi di atas nisab
 *  (c) zakat di bawah nisab = 0
 *  (d) screening syariah wajib sebelum pembelian (debt < 45%, non-halal < 5%)
 *  (e) ijarah ownership transfer saat cicilan selesai
 *  (f) syb:audit + rwa:audit = 0 selisih
 */
class SukukAndZakatTest extends TestCase
{
    use RefreshDatabase;

    protected SukukAndZakatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SukukAndZakatService::class);
    }

    /**
     * (a) Sukuk token issuance & quarterly coupon distribution.
     */
    public function test_sukuk_issuance_and_coupon_distribution(): void
    {
        // 10 Billion Sukuk, 8% coupon annual -> 2% quarterly (200m)
        $sukuk = $this->service->issueSukuk('ASSET-LOG-WAREHOUSE-01', 10000000000.0, 8.0, Carbon::now()->addYears(5));
        $this->assertSame('ACTIVE', $sukuk->status);

        $distributed = $this->service->distributeSukukCoupon($sukuk->sukuk_code);
        $this->assertEquals(200000000.00, $distributed);
    }

    /**
     * (e) Ijarah lease-to-own transfers ownership on last installment.
     */
    public function test_ijarah_lease_to_own_transfer(): void
    {
        // 3 months tenor
        $ijarah = $this->service->createIjarahContract('ASSET-EV-VAN-01', 801, 5000000.0, 3);
        $this->assertFalse((bool) $ijarah->ownership_transferred);

        // Month 1
        $m1 = $this->service->payIjarahInstallment($ijarah->ijarah_code);
        $this->assertFalse((bool) $m1->ownership_transferred);

        // Month 2
        $m2 = $this->service->payIjarahInstallment($ijarah->ijarah_code);
        $this->assertFalse((bool) $m2->ownership_transferred);

        // Month 3 -> Finalized, ownership transferred
        $m3 = $this->service->payIjarahInstallment($ijarah->ijarah_code);
        $this->assertTrue((bool) $m3->ownership_transferred);
        $this->assertSame(3, $m3->months_paid);
    }

    /**
     * (d) Shariah screening compliance.
     */
    public function test_shariah_screening_enforcement(): void
    {
        // Compliant security: Debt 25%, Non-halal 1% -> Compliant
        $halal = $this->service->screenSecurity('ICBP', 'Indofood CBP', 25.0, 1.0);
        $this->assertTrue((bool) $halal->is_shariah_compliant);

        // Non-compliant security: Debt 55% -> Non-compliant
        $haram = $this->service->screenSecurity('LEVR', 'High Leverage Corp', 55.0, 2.0);
        $this->assertFalse((bool) $haram->is_shariah_compliant);
    }

    /**
     * (b) & (c) Zakat calculation: 2.5% above Nisab, 0 below Nisab.
     */
    public function test_zakat_mal_calculation(): void
    {
        // Nisab = 85,000,000
        // Wealth: 200,000,000 -> Zakat 2.5% = 5,000,000
        $above = $this->service->calculateZakatMal(802, '2026', 200000000.0, 85000000.0);
        $this->assertEquals(5000000.00, (float) $above->zakat_amount_due);
        $this->assertTrue((bool) $above->is_settled);

        // Wealth: 50,000,000 (below nisab) -> Zakat = 0.00
        $below = $this->service->calculateZakatMal(803, '2026', 50000000.0, 85000000.0);
        $this->assertEquals(0.00, (float) $below->zakat_amount_due);
    }

    /**
     * (f) Audit status healthy.
     */
    public function test_sukuk_and_zakat_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
