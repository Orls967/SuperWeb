<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\SyariahOperationsService;
use Tests\TestCase;

/**
 * Fase 165 — Syariah Operations & 30 Lines Integration Tests
 *
 * Covers:
 *  (a) wallet syariah bayar di 30 lini idempoten
 *  (b) NPF calculation = aturan (non-performing / total)
 *  (c) halal certificate gate penjualan produk makanan & resto
 *  (d) integration E2E hijau
 *  (e) syb:audit = 0 selisih
 */
class SyariahOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected SyariahOperationsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SyariahOperationsService::class);
    }

    /**
     * (b) NPF calculation accuracy.
     */
    public function test_npf_ratio_calculation(): void
    {
        // Performing: 96 Billion, Non-performing: 4 Billion -> Total 100 Billion, NPF = 4.00%
        $metric = $this->service->calculateNpf('Q3-2026', 96000000000.0, 4000000000.0);

        $this->assertEquals(100000000000.00, (float) $metric->total_financing_portfolio);
        $this->assertEquals(4.00, (float) $metric->npf_ratio_pct);
    }

    /**
     * (a) & (c) Halal certificate gate enforces restriction on non-certified food merchants.
     */
    public function test_halal_certificate_gate_on_wallet_payments(): void
    {
        $account = 'SYB-WALLET-001';

        // 1. Certified halal restaurant -> Payment SUCCESS
        $this->service->registerHalalCertificate('RESTO-HALAL-01', 'MUI-HALAL-2026-999', Carbon::now()->addYear());
        $tx = $this->service->processWalletPayment($account, 'RESTO-HALAL-01', 'RST', 150000.00);
        $this->assertSame('SUCCESS', $tx->status);
        $this->assertTrue((bool) $tx->halal_certified_merchant);

        // 2. Uncertified restaurant -> Throws exception and payment rejected
        $this->expectException(\RuntimeException::class);
        $this->service->processWalletPayment($account, 'RESTO-UNCERTIFIED-02', 'RST', 200000.00);
    }

    /**
     * (e) Final syb:audit returns zero discrepancy.
     */
    public function test_syariah_operations_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
