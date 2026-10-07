<?php

declare(strict_types=1);

namespace Modules\Egy\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Application\Services\DistrictUtilityAndEscoService;
use RuntimeException;
use Tests\TestCase;

class DistrictUtilityAndEscoTest extends TestCase
{
    use RefreshDatabase;

    protected DistrictUtilityAndEscoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DistrictUtilityAndEscoService::class);

        $accounts = [
            'egy:utility_receivable:IDR' => 'asset',
            'egy:utility_consolidated_revenue:IDR' => 'revenue',
            'egy:esco_fee_receivable:IDR' => 'asset',
            'egy:esco_service_revenue:IDR' => 'revenue',
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

    public function test_127_1_and_127_6_a_water_balance_conservation(): void
    {
        // 10,000m3 input - 500m3 loss = 9,500m3 delivered
        $distribution = $this->service->recordWaterDistribution([
            'consumer_property_id' => 'MALL-CENTRAL-PARK',
            'input_volume_m3' => 10000.0,
            'leakage_loss_m3' => 500.0,
            'delivered_volume_m3' => 9500.0,
            'water_tariff_minor' => 12000, // 12k IDR/m3 -> 114M IDR
        ]);

        $this->assertEquals(114000000, $distribution->water_charge_minor);

        // Imbalance should fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Water balance imbalance');
        $this->service->recordWaterDistribution([
            'consumer_property_id' => 'MALL-CENTRAL-PARK',
            'input_volume_m3' => 10000.0,
            'leakage_loss_m3' => 500.0,
            'delivered_volume_m3' => 8000.0, // discrepancy!
            'water_tariff_minor' => 12000,
        ]);
    }

    public function test_127_3_and_127_6_b_district_cooling_tariff_calculation(): void
    {
        // 40,000 thermal kWh consumed @ 1,800 IDR = 72,000,000 IDR
        $meter = $this->service->recordDistrictCoolingConsumption(
            'HOTEL-MULIA-SENAYAN',
            40000.0,
            1800,
            4.8
        );

        $this->assertEquals(72000000, $meter->total_charge_minor);
        $this->assertEquals(4.8, $meter->cop_efficiency_factor);
    }

    public function test_127_4_and_127_6_d_consolidated_utility_invoice_sum(): void
    {
        // Elec 250M + Water 40M + Cooling 60M + Gas 15M = 365M IDR
        $invoice = $this->service->issueConsolidatedInvoice([
            'property_id' => 'MALL-PACIFIC-PLACE',
            'billing_period' => '2026-10',
            'electricity_charge_minor' => 250000000,
            'water_charge_minor' => 40000000,
            'district_cooling_charge_minor' => 60000000,
            'gas_charge_minor' => 15000000,
        ]);

        $this->assertEquals(365000000, $invoice->total_consolidated_minor);
        $this->assertEquals(
            $invoice->electricity_charge_minor + $invoice->water_charge_minor + $invoice->district_cooling_charge_minor + $invoice->gas_charge_minor,
            $invoice->total_consolidated_minor
        );

        $tx = LedgerTransaction::with('entries')->findOrFail($invoice->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_127_5_and_127_6_c_esco_split_savings_settlement(): void
    {
        // Baseline 500M IDR, actual 380M IDR -> savings 120M IDR. ESCO gets 60% = 72M IDR, Client retains 48M IDR
        $contract = $this->service->settleEscoPerformanceContract([
            'client_property_id' => 'FACTORY-CEMENT-CIBINONG',
            'baseline_energy_cost_minor' => 500000000,
            'actual_energy_cost_minor' => 380000000,
            'esco_share_pct' => 60.0,
        ]);

        $this->assertEquals(120000000, $contract->verified_savings_minor);
        $this->assertEquals(72000000, $contract->esco_remuneration_minor);
        $this->assertEquals(48000000, $contract->client_retained_saving_minor);

        $tx = LedgerTransaction::with('entries')->findOrFail($contract->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }
}
