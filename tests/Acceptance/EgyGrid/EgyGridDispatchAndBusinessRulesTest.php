<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyGrid;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\PpaContract;
use Tests\TestCase;

class EgyGridDispatchAndBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_merit_order_dispatch_ppa_settlement_and_net_metering_rules(): void
    {
        $this->seed();
        $service = app(EnergyGridAndMeteringService::class);

        // 1. Generation units setup
        $u1 = GenerationAsset::create([
            'asset_code' => 'GEN-U1',
            'name' => 'PLTU Batubara U1',
            'fuel_type' => 'coal',
            'capacity_mw' => 400,
            'available_capacity_mw' => 400,
            'min_stable_mw' => 200,
            'marginal_cost_idr' => 600,
            'is_must_run' => true,
            'status' => 'online',
        ]);

        $u2 = GenerationAsset::create([
            'asset_code' => 'GEN-U2',
            'name' => 'PLTGU Gas U2',
            'fuel_type' => 'gas',
            'capacity_mw' => 300,
            'available_capacity_mw' => 300,
            'min_stable_mw' => 100,
            'marginal_cost_idr' => 900,
            'is_must_run' => false,
            'status' => 'online',
        ]);

        $u3 = GenerationAsset::create([
            'asset_code' => 'GEN-U3',
            'name' => 'PLTS Surya U3',
            'fuel_type' => 'solar',
            'capacity_mw' => 150,
            'available_capacity_mw' => 150,
            'min_stable_mw' => 0,
            'marginal_cost_idr' => 0,
            'is_must_run' => false,
            'status' => 'online',
        ]);

        $u4 = GenerationAsset::create([
            'asset_code' => 'GEN-U4',
            'name' => 'PLTD Peaker U4',
            'fuel_type' => 'diesel',
            'capacity_mw' => 250,
            'available_capacity_mw' => 250,
            'min_stable_mw' => 50,
            'marginal_cost_idr' => 1800,
            'is_must_run' => false,
            'status' => 'online',
        ]);

        // 2. Dispatch under load 950 MW
        $dispatch950 = $service->runDispatch(
            hourTimestamp: '2026-11-06 12:00:00',
            loadMw: 950,
            idempotencyKey: 'DISP-20261106-1200'
        );

        $this->assertEquals(950, $dispatch950->total_dispatched_mw);
        $this->assertEquals(690000, $dispatch950->total_cost_thousand_idr);
        $this->assertEquals(0, $dispatch950->total_curtailed_mw);

        // 3. Dispatch under light load 300 MW (Must-run min 200 + Solar 100, Curtail 50)
        $dispatch300 = $service->runDispatch(
            hourTimestamp: '2026-11-06 13:00:00',
            loadMw: 300,
            idempotencyKey: 'DISP-20261106-1300'
        );

        $this->assertEquals(300, $dispatch300->total_dispatched_mw);
        $this->assertEquals(50, $dispatch300->total_curtailed_mw);

        // 4. PPA Settlement
        $ppa = PpaContract::create([
            'contract_number' => 'PPA-SOLAR-001',
            'generator_party_id' => 'GEN-IPPO-01',
            'offtaker_party_id' => 'OFF-PLN-01',
            'capacity_kw' => 5000,
            'rate_per_kwh_idr' => 900,
            'valid_from' => '2026-01-01',
            'valid_to' => '2030-12-31',
            'status' => 'active',
        ]);

        $settlement = $service->settlePpa(
            ppaContractId: $ppa->id,
            period: '2026-11',
            deliveredKwh: 100000,
            idempotencyKey: 'SETTLE-PPA-001-202611'
        );

        $this->assertEquals(90000000, $settlement->total_payout_idr);
        $this->assertDatabaseHas('postings', [
            'type' => 'egy:power_purchase_expense',
        ]);

        $apGen = LedgerAccount::where('code', 'ap:egy:generator:GEN-IPPO-01:IDR')->first();
        $this->assertNotNull($apGen);
    }
}
