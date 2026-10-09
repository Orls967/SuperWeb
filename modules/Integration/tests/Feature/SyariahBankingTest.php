<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\SyariahBankingService;
use Tests\TestCase;

/**
 * Fase 161 — Keuangan Syariah Tests
 *
 * Covers:
 *  (a) rekening syariah terpisah & terisolasi
 *  (b) margin murabahah diakui gradual sesuai jadwal
 *  (c) denda keterlambatan dialihkan ke dana amil / kebajikan (bukan pendapatan bank)
 *  (d) bagi hasil mudharabah = laba pool × rasio nisbah
 *  (e) syb:audit = 0 selisih
 */
class SyariahBankingTest extends TestCase
{
    use RefreshDatabase;

    protected SyariahBankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SyariahBankingService::class);
    }

    /**
     * (a) Islamic account opening & segregation.
     */
    public function test_islamic_account_opening(): void
    {
        $account = $this->service->openAccount(701, 'MUDHARABAH_DEPOSIT', 65.0);

        $this->assertSame('MUDHARABAH_DEPOSIT', $account->account_type);
        $this->assertEquals(65.00, (float) $account->nisbah_customer_pct);
        $this->assertTrue((bool) $account->is_active);
    }

    /**
     * (b) & (c) Murabahah contract: direct vendor payment, gradual margin & amil charity late fee.
     */
    public function test_murabahah_financing_workflow(): void
    {
        $account = $this->service->openAccount(702, 'WADIAH');

        // Cost: 120m, Margin: 24m (total 144m). Tenor: 12 months -> Installment 12m/month (margin 2m/month)
        $contract = $this->service->createMurabahahContract($account->account_number, 'VND-TOYOTA-ID', 120000000.0, 24000000.0, 12);

        $this->assertSame('VND-TOYOTA-ID', $contract->vendor_code);
        $this->assertEquals(144000000.00, (float) $contract->total_selling_price);
        $this->assertEquals(12000000.00, (float) $contract->monthly_installment);
        $this->assertNotNull($contract->contract_hash);

        // Pay installment with late fee Rp 50,000
        $updated = $this->service->payMurabahahInstallment($contract->contract_code, 12000000.00, 50000.00);
        $this->assertEquals(2000000.00, (float) $updated->margin_recognized);
        $this->assertEquals(5000000.00, (float) $updated->late_penalties_to_amil + 4950000.00); // Verify 50,000 late fee to amil
        $this->assertEquals(50000.00, (float) $updated->late_penalties_to_amil);
    }

    /**
     * (d) Mudharabah profit sharing calculation.
     */
    public function test_mudharabah_pool_profit_sharing(): void
    {
        // Pool profit: 50,000,000. Customer nisbah: 60% (30m). Bank mudharib: 40% (20m).
        $pool = $this->service->distributeMudharabahPool('M-10-2026', 50000000.00, 60.0);

        $this->assertEquals(30000000.00, (float) $pool->customer_share_distributed);
        $this->assertEquals(20000000.00, (float) $pool->bank_mudharib_share);
        $this->assertEquals((float) $pool->total_pool_profit, (float) $pool->customer_share_distributed + (float) $pool->bank_mudharib_share);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_syariah_banking_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
