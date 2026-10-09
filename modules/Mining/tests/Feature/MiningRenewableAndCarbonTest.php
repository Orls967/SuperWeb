<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mining\Application\Services\MiningRenewableAndCarbonService;
use Modules\Mining\Domain\Models\MiningSite;
use RuntimeException;
use Tests\TestCase;

class MiningRenewableAndCarbonTest extends TestCase
{
    use RefreshDatabase;

    protected MiningRenewableAndCarbonService $service;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningRenewableAndCarbonService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SOLAR-01',
            'name' => 'Pomalaa Solar Array & Nickel Processing',
            'commodity' => 'NICKEL',
            'location' => 'Southeast Sulawesi',
        ]);

        $accounts = [
            'min:intercompany_receivable:IDR' => 'asset',
            'min:renewable_power_revenue:IDR' => 'revenue',
            'min:trade_receivable:IDR' => 'asset',
            'min:base_metal_sales_revenue:IDR' => 'revenue',
            'min:green_premium_revenue:IDR' => 'revenue',
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

    public function test_123_1_and_123_6_a_intercompany_renewable_energy_billing(): void
    {
        // 500,000 kWh delivered @ 1,200 IDR/kWh = 600,000,000 IDR
        $billing = $this->service->billIntercompanyRenewableEnergy([
            'producer_site_id' => $this->site->id,
            'consumer_entity_id' => 'ENTITY-MANUFACTURING-PLANT-01',
            'energy_kwh_delivered' => 500000.0,
            'rate_per_kwh_minor' => 1200,
        ]);

        $this->assertEquals(600000000, $billing->total_amount_minor);
        $this->assertEquals(410.0, $billing->scope1_avoided_tons_co2); // (500k * 0.82) / 1000 = 410 tons CO2
        $this->assertNotNull($billing->ledger_transaction_id);

        $tx = LedgerTransaction::with('entries')->findOrFail($billing->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_123_3_and_123_6_b_carbon_credit_issuance_bounded_by_verified(): void
    {
        // Verified 12,500 tons. Attempt to issue 15,000 tons -> throws over-allocation exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Carbon issuance over-allocation');

        $this->service->issueCarbonCredits([
            'site_id' => $this->site->id,
            'project_type' => 'ARR_REFORESTATION',
            'verified_ndvi_score' => 0.65,
            'verified_carbon_tons' => 12500.0,
            'requested_credits_tons' => 15000.0,
        ]);
    }

    public function test_123_3_and_123_6_b_carbon_credit_issuance_valid(): void
    {
        $credit = $this->service->issueCarbonCredits([
            'site_id' => $this->site->id,
            'project_type' => 'ARR_REFORESTATION',
            'verified_ndvi_score' => 0.72,
            'verified_carbon_tons' => 10000.0,
            'requested_credits_tons' => 10000.0,
        ]);

        $this->assertEquals(10000.0, $credit->issued_credits_tons);
        $this->assertStringStartsWith('REG-IDX-', $credit->registry_serial_number);
        $this->assertEquals('ISSUED', $credit->status);
    }

    public function test_123_5_and_123_6_c_green_mineral_premium_and_dpp_traceability(): void
    {
        // 100 tons battery grade Nickel: Base price 20 Miliar IDR + 2 Miliar green premium adder = 22 Miliar IDR total
        $sale = $this->service->executeGreenMineralSale([
            'buyer_party_id' => 'PARTY-GLOBAL-EV-MAKER',
            'commodity' => 'NICKEL_CLASS_1',
            'tonnage' => 100.0,
            'base_price_minor' => 20000000000,
            'green_premium_adder_minor' => 2000000000,
        ]);

        $this->assertEquals(22000000000, $sale->total_settled_minor);
        $this->assertNotEmpty($sale->dpp_passport_hash);

        // Verify ledger entries balance to zero (debit 22B, credit base 20B, credit green 2B)
        $tx = LedgerTransaction::with('entries')->findOrFail($sale->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(3, $tx->entries->count());
    }
}
