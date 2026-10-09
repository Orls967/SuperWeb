<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mining\Application\Services\MiningSmelterAndMetalsTradingService;
use Modules\Mining\Domain\Models\MiningSite;
use RuntimeException;
use Tests\TestCase;

class MiningSmelterAndMetalsTradingTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSmelterAndMetalsTradingService $service;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningSmelterAndMetalsTradingService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SMELTER-01',
            'name' => 'Morowali High-Pressure Smelter Complex',
            'commodity' => 'NICKEL',
            'location' => 'Central Sulawesi',
        ]);

        $accounts = [
            'min:trade_receivable:IDR' => 'asset',
            'min:metal_sales_revenue:IDR' => 'revenue',
            'min:byproduct_receivable:IDR' => 'asset',
            'min:byproduct_sales_revenue:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Mining {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_121_1_and_121_6_a_mass_conservation_check(): void
    {
        // 1000 tons of 1.8% Ni ore has exactly 18 tons contained nickel.
        // If smelter claims 25 tons output -> must throw exception!
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mass conservation violation');

        $this->service->recordSmelterRun([
            'site_id' => $this->site->id,
            'run_code' => 'RUN-SMELT-ERR',
            'output_commodity' => 'NPI',
            'input_ore_tonnage' => 1000.0,
            'input_grade_pct' => 1.8,
            'output_metal_tonnage' => 25.0, // impossible!
        ]);
    }

    public function test_121_1_and_121_5_valid_smelter_run_with_byproduct_slag_revenue(): void
    {
        // 1000 tons ore at 1.8% -> 18t contained -> 16.2t produced = 90% recovery
        $run = $this->service->recordSmelterRun([
            'site_id' => $this->site->id,
            'run_code' => 'RUN-SMELT-001',
            'output_commodity' => 'NPI',
            'input_ore_tonnage' => 1000.0,
            'input_grade_pct' => 1.8,
            'output_metal_tonnage' => 16.2,
            'byproduct_slag_tonnage' => 850.0,
            'byproduct_revenue_idr' => 42500000,
            'energy_kwh_per_ton' => 380.0,
        ]);

        $this->assertEquals(90.0, $run->recovery_rate_pct);
        $this->assertEquals(42500000, $run->byproduct_revenue_idr);
    }

    public function test_121_2_and_121_6_b_offtaker_lme_pricing_and_balanced_ledger_invoice(): void
    {
        // 50 tons Nickel, base LME $16,500/ton, premium $200/ton -> $16,700/ton -> $835,000 * 16,000 = 13,360,000,000 IDR
        $contract = $this->service->invoiceOfftakerContract([
            'buyer_party_id' => 'PARTY-BATTERY-OEM-01',
            'commodity' => 'NICKEL',
            'contracted_tonnage' => 50.0,
            'base_lme_price_usd_per_ton' => 16500.0,
            'premium_discount_usd' => 200.0,
            'fx_rate_to_idr' => 16000,
        ]);

        $expectedIdr = (int) (50.0 * 16700.0 * 16000);
        $this->assertEquals($expectedIdr, $contract->invoiced_amount_minor);
        $this->assertEquals('INVOICED', $contract->settlement_status);

        // Verify zero-sum ledger
        $tx = LedgerTransaction::with('entries')->findOrFail($contract->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_121_3_and_121_6_c_metals_trading_desk_mark_to_market(): void
    {
        // Long 100 tons Copper at $9,000/ton
        $pos = $this->service->openTradingPosition([
            'desk_code' => 'DESK-SINGAPORE-METALS',
            'commodity' => 'COPPER',
            'position_type' => 'LONG',
            'tonnage' => 100.0,
            'entry_price_usd' => 9000.0,
            'current_mark_price_usd' => 9000.0,
        ]);

        $this->assertEquals(0, $pos->unrealized_pnl_minor);

        // Mark to market jumps to $9,250/ton (+ $250/ton * 100t = +$25,000 = 2,500,000 cents)
        $updatedPos = $this->service->markToMarketTradingPosition($pos->id, 9250.0);

        $this->assertEquals(2500000, $updatedPos->unrealized_pnl_minor);
        $this->assertEquals(9250.0, $updatedPos->current_mark_price_usd);
    }

    public function test_121_4_and_121_6_d_warehouse_receipt_issuance_and_collateral_pledge(): void
    {
        $receipt = $this->service->issueWarehouseReceipt('WMS-BONDED-SURABAYA', 'NICKEL', 250.0);

        $this->assertNotEmpty($receipt->receipt_hash);
        $this->assertFalse($receipt->is_collateralized);
        $this->assertEquals('ACTIVE', $receipt->status);

        // Pledge as collateral
        $pledged = $this->service->pledgeReceiptAsCollateral(
            $receipt->id,
            'SCF-FACILITY-BNI-99',
            50000000000 // 50 Miliar IDR
        );

        $this->assertTrue($pledged->is_collateralized);
        $this->assertEquals('PLEDGED', $pledged->status);
        $this->assertEquals(50000000000, $pledged->financing_amount_minor);

        // Release collateral
        $released = $this->service->releaseReceiptCollateral($pledged->id);
        $this->assertFalse($released->is_collateralized);
        $this->assertEquals('RELEASED', $released->status);
    }
}
