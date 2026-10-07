<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\IslamicTradeFinanceService;
use Tests\TestCase;

/**
 * Fase 164 — Islamic Trade Finance Tests
 *
 * Covers:
 *  (a) istisna' + wakalah LC fee syariah terpisah
 *  (b) salam + parallel posisi seimbang
 *  (c) tawarruq commodity FX flow tercatat penuh
 *  (d) fee syariah != riba pattern
 *  (e) tf:audit + syb:audit = 0 selisih
 */
class IslamicTradeFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected IslamicTradeFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(IslamicTradeFinanceService::class);
    }

    /**
     * (a) Islamic LC issuance with separated Wakalah fee.
     */
    public function test_islamic_lc_issuance(): void
    {
        // Import goods worth Rp 500,000,000. 1.5% wakalah service fee = Rp 7,500,000
        $lc = $this->service->issueIslamicLc('ACC-IMP-001', 'EXP-JAPAN-01', 500000000.0, 1.5);

        $this->assertSame('ISTISNA_WAKALAH', $lc->akad_type);
        $this->assertEquals(500000000.00, (float) $lc->goods_value);
        $this->assertEquals(7500000.00, (float) $lc->wakalah_fee);
        $this->assertSame('ISSUED', $lc->status);
    }

    /**
     * (b) Salam contract with balanced parallel hedge contract.
     */
    public function test_salam_contract_balanced_hedging(): void
    {
        // 100 tons corn, payment in advance Rp 400,000,000, delivery in 3 months
        $salam = $this->service->createSalamWithHedge('FARMER-JATIM-01', 'CORN', 400000000.0, 100.0, Carbon::now()->addMonths(3));

        $this->assertEquals(400000000.00, (float) $salam->advance_payment_paid);
        $this->assertEquals(100.00, (float) $salam->quantity_tons);
        $this->assertNotNull($salam->parallel_hedge_contract_code);
        $this->assertTrue((bool) $salam->positions_balanced);
    }

    /**
     * (c) Tawarruq commodity murabahah for currency conversion without interest.
     */
    public function test_tawarruq_commodity_fx(): void
    {
        // Convert $10,000 USD to IDR at 15,500 with flat broker service fee Rp 150,000
        $fx = $this->service->executeTawarruqFx('USD', 'IDR', 10000.0, 15500.0, 150000.0);

        $this->assertSame('USD', $fx->source_currency);
        $this->assertSame('IDR', $fx->target_currency);
        $this->assertEquals(155000000.00, (float) $fx->target_amount);
        $this->assertEquals(150000.00, (float) $fx->broker_service_fee);
        $this->assertTrue((bool) $fx->shariah_board_cleared);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_islamic_trade_finance_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
