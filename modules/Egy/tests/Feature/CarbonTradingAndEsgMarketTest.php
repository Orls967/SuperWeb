<?php

declare(strict_types=1);

namespace Modules\Egy\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Application\Services\CarbonTradingAndEsgMarketService;
use RuntimeException;
use Tests\TestCase;

class CarbonTradingAndEsgMarketTest extends TestCase
{
    use RefreshDatabase;

    protected CarbonTradingAndEsgMarketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CarbonTradingAndEsgMarketService::class);

        $accounts = [
            'egy:carbon_credit_inventory:IDR' => 'asset',
            'egy:carbon_credit_sales:IDR' => 'revenue',
            'egy:cbam_receivable:IDR' => 'asset',
            'egy:cbam_revenue:IDR' => 'revenue',
            'egy:green_incentive_contra_revenue:IDR' => 'expense',
            'egy:utility_receivable:IDR' => 'asset',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Energy {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_128_1_and_128_6_a_internal_carbon_exchange_trade(): void
    {
        // 500 tons carbon credits @ 150,000 IDR/ton = 75,000,000 IDR
        $order = $this->service->tradeCarbonCredits([
            'seller_entity_id' => 'ENTITY-MINING-REFORESTATION-01',
            'buyer_entity_id' => 'ENTITY-MANUFACTURING-PLANT-02',
            'vintage_year' => '2026',
            'carbon_credits_tons' => 500.0,
            'price_per_ton_minor' => 150000,
        ]);

        $this->assertEquals(75000000, $order->total_value_minor);
        $this->assertEquals('SETTLED', $order->status);

        $tx = LedgerTransaction::with('entries')->findOrFail($order->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_128_2_and_128_6_b_rec_issuance_and_no_double_count_retirement(): void
    {
        $rec = $this->service->issueRenewableEnergyCertificate(
            'GEN-SOLAR-FARM-01',
            'ENTITY-ENERGY-CORP',
            100.0 // 100 MWh
        );

        $this->assertFalse($rec->is_retired);

        // Retire by Hotel for 100% green claim
        $retired = $this->service->retireRenewableEnergyCertificate($rec->id, 'HOTEL-BALI-SANUR');
        $this->assertTrue($retired->is_retired);
        $this->assertEquals('HOTEL-BALI-SANUR', $retired->retired_by_property_id);

        // Second attempt to retire same REC must fail (no double count)
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Double-counting prevented');
        $this->service->retireRenewableEnergyCertificate($rec->id, 'VENUE-JAKARTA-STADIUM');
    }

    public function test_128_3_and_128_6_c_cbam_tariff_calculation_and_ledger(): void
    {
        // 12.5 tons embedded CO2 @ 1,200,000 IDR/ton = 15,000,000 IDR CBAM fee
        $cbam = $this->service->issueCbamCertificate([
            'exporter_entity_id' => 'ENTITY-STEEL-EXPORT-01',
            'container_id' => 'CONT-EU-992144',
            'embedded_emissions_tons_co2' => 12.5,
            'cbam_price_per_ton_minor' => 1200000,
            'billed_to_party_id' => 'BUYER-ROTTERDAM-METALS',
        ]);

        $this->assertEquals(15000000, $cbam->total_cbam_fee_minor);

        $tx = LedgerTransaction::with('entries')->findOrFail($cbam->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_128_5_and_128_6_d_green_lease_esg_discount_ledger_contra_revenue(): void
    {
        // Gross charge 100M IDR, tenant ESG score 85 -> 10% discount = 10M IDR contra-revenue, net 90M IDR
        $discount = $this->service->applyGreenLeaseDiscount(
            'TENANT-RETAIL-ZARA-MALL',
            85,
            100000000
        );

        $this->assertEquals(10.0, $discount->discount_rate_pct);
        $this->assertEquals(10000000, $discount->green_discount_amount_minor);
        $this->assertEquals(90000000, $discount->net_utility_charge_minor);

        $tx = LedgerTransaction::with('entries')->findOrFail($discount->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }
}
