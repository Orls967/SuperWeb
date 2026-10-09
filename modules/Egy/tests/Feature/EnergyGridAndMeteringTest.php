<?php

declare(strict_types=1);

namespace Modules\Egy\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use RuntimeException;
use Tests\TestCase;

class EnergyGridAndMeteringTest extends TestCase
{
    use RefreshDatabase;

    protected EnergyGridAndMeteringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnergyGridAndMeteringService::class);

        $accounts = [
            'egy:utility_receivable:IDR' => 'asset',
            'egy:grid_electricity_revenue:IDR' => 'revenue',
            'egy:power_purchase_expense:IDR' => 'expense',
            'egy:producer_payable:IDR' => 'liability',
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

    public function test_126_2_and_126_6_b_smart_metering_tou_billing_and_ledger(): void
    {
        $meter = $this->service->registerSmartMeter([
            'meter_serial_number' => 'MTR-MALL-GRAND-01',
            'consumer_property_type' => 'MALL',
            'consumer_property_id' => 'MALL-GRAND-INDONESIA',
            'tariff_category' => 'BUSINESS_TOU',
        ]);

        $start = Carbon::now()->subMinutes(15);
        $end = Carbon::now();

        // Peak hour reading: 250 kWh @ 2,100 IDR = 525,000 IDR
        $peakReading = $this->service->recordMeterReading($meter->id, 250.0, true, $start, $end);
        $this->assertEquals(525000, $peakReading->total_charge_minor);

        // Off-peak reading: 100 kWh @ 1,450 IDR = 145,000 IDR
        $offPeakReading = $this->service->recordMeterReading($meter->id, 100.0, false, $start, $end);
        $this->assertEquals(145000, $offPeakReading->total_charge_minor);

        $meter->refresh();
        $this->assertEquals(350.0, $meter->total_kwh_accumulated);

        // Verify ledger transactions balance to zero
        $tx = LedgerTransaction::where('type', 'ENERGY_METER_READING_BILL')->first();
        $this->assertNotNull($tx);
        $this->assertEquals(0, $tx->entries->sum('amount_minor'));
    }

    public function test_126_3_and_126_6_a_grid_dispatch_unit_commitment_cannot_exceed_capacity(): void
    {
        // Register solar (0 marginal cost, 50 MW)
        $this->service->registerGenerationAsset([
            'asset_code' => 'SOLAR-FARM-01',
            'name' => 'Cirata Floating Solar',
            'asset_type' => 'SOLAR_FARM',
            'installed_capacity_mw' => 50.0,
            'marginal_cost_per_mwh_minor' => 0,
        ]);

        // Register thermal PLTU (high marginal cost, 100 MW)
        $this->service->registerGenerationAsset([
            'asset_code' => 'PLTU-JAWA-07',
            'name' => 'Jawa-7 Supercritical Coal',
            'asset_type' => 'THERMAL_PLTU',
            'installed_capacity_mw' => 100.0,
            'marginal_cost_per_mwh_minor' => 850000,
        ]);

        // Dispatch 70 MW: solar takes 50 MW, thermal takes 20 MW
        $dispatched = $this->service->dispatchGridLoad(70.0);
        $this->assertCount(2, $dispatched);
        $this->assertEquals(50.0, $dispatched->firstWhere('asset_code', 'SOLAR-FARM-01')['dispatched_mw']);
        $this->assertEquals(20.0, $dispatched->firstWhere('asset_code', 'PLTU-JAWA-07')['dispatched_mw']);

        // Dispatch 200 MW (exceeds total 150 MW capacity) -> throws exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Grid deficit: required load 200MW exceeds total online generation capacity');
        $this->service->dispatchGridLoad(200.0);
    }

    public function test_126_4_and_126_6_c_d_ppa_net_metering_settlement(): void
    {
        // Rooftop solar exports 12,000 kWh surplus @ feed-in tariff 1,000 IDR/kWh = 12,000,000 IDR credit
        $ppa = $this->service->settleNetMeteringPpa([
            'producer_entity_id' => 'HOTEL-BALI-RESORT',
            'grid_offtaker_id' => 'PLN-BALI-DISTRIBUTION',
            'feed_in_tariff_per_kwh_minor' => 1000.0,
            'surplus_kwh_exported' => 12000.0,
        ]);

        $this->assertEquals(12000000, $ppa->total_settlement_minor);
        $this->assertEquals('ACTIVE', $ppa->status);

        $tx = LedgerTransaction::with('entries')->findOrFail($ppa->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }
}
