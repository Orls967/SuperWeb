<?php

declare(strict_types=1);

namespace Modules\Egy\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Tests\TestCase;

class MicrogridAndResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected MicrogridAndResilienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MicrogridAndResilienceService::class);

        $accounts = [
            'egy:utility_receivable:IDR' => 'asset',
            'egy:bess_arbitrage_revenue:IDR' => 'revenue',
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

    public function test_129_1_and_129_5_a_microgrid_islanding_priority_order(): void
    {
        $grid = $this->service->createMicrogrid('Kawasan Industri Morowali Hub', 5000.0);

        // Add 4 loads with decreasing priority
        $hosp = $this->service->addLoadPriority($grid->id, 'RS Siloam Emergency Wing', 'TIER_1_HOSPITAL', 1, 800.0);
        $plant = $this->service->addLoadPriority($grid->id, 'Smelter Furnace Blast 01', 'TIER_2_CRITICAL_FACTORY', 2, 1200.0);
        $mall = $this->service->addLoadPriority($grid->id, 'Plaza Retail Center', 'TIER_3_MALL', 3, 1000.0);
        $res = $this->service->addLoadPriority($grid->id, 'Residential Complex', 'TIER_4_GENERAL', 4, 1500.0);

        // Grid failure: Islanded mode with only 2500 kW emergency generation
        // 800 (RS) + 1200 (Smelter) = 2000 kW <= 2500 kW -> Mall and Residential must be shed!
        $result = $this->service->triggerIslandingMode($grid->id, 2500.0);

        $this->assertEquals('ISLANDED', $result['mode']);
        $this->assertContains('RS Siloam Emergency Wing', $result['active_loads']);
        $this->assertContains('Smelter Furnace Blast 01', $result['active_loads']);
        $this->assertContains('Plaza Retail Center', $result['shed_loads']);
        $this->assertContains('Residential Complex', $result['shed_loads']);
        $this->assertEquals(500.0, $result['surplus_kw']);

        $hosp->refresh();
        $this->assertFalse($hosp->is_shed);
        $mall->refresh();
        $this->assertTrue($mall->is_shed);
    }

    public function test_129_2_and_129_5_b_c_bess_arbitrage_profit_and_battery_cycles(): void
    {
        // Charge at off-peak cost 14M IDR, discharge at peak rev 21M IDR -> net profit 7M IDR
        $arbitrage = $this->service->recordBessArbitrageRun([
            'bess_asset_id' => 'BESS-TESLA-MEGA-01',
            'energy_discharged_kwh' => 10000.0,
            'charging_cost_minor' => 14000000,
            'discharging_revenue_minor' => 21000000,
            'state_of_health_pct' => 99.4,
        ]);

        $this->assertEquals(7000000, $arbitrage->net_arbitrage_profit_minor);
        $this->assertEquals(1, $arbitrage->battery_cycle_count_increment);
        $this->assertEquals(99.4, $arbitrage->state_of_health_pct);

        $tx = LedgerTransaction::with('entries')->findOrFail($arbitrage->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_129_3_and_129_5_d_backup_power_test_compliance(): void
    {
        // 85% load for 45 mins -> PASS
        $passTest = $this->service->recordBackupGeneratorTest([
            'property_id' => 'HOSPITAL-HARAPAN-KITA',
            'generator_asset_id' => 'GENSET-CAT-1500KVA',
            'scheduled_test_date' => Carbon::now()->toDateTimeString(),
            'load_test_pct' => 85.0,
            'runtime_minutes' => 45,
        ]);
        $this->assertTrue($passTest->is_passed);

        // 60% load for 20 mins -> FAIL
        $failTest = $this->service->recordBackupGeneratorTest([
            'property_id' => 'VENUE-GELORA-BUNG-KARNO',
            'generator_asset_id' => 'GENSET-CUMMINS-2000KVA',
            'scheduled_test_date' => Carbon::now()->toDateTimeString(),
            'load_test_pct' => 60.0,
            'runtime_minutes' => 20,
        ]);
        $this->assertFalse($failTest->is_passed);
    }
}
