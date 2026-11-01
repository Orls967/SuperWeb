<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Vehicle;
use Modules\Mobility\Ev\Application\Actions\AppendBatteryPassportLifecycleEventAction;
use Modules\Mobility\Ev\Application\Actions\DischargeV2GGridFeedInCreditAction;
use Modules\Mobility\Ev\Application\Actions\SettleTimeOfUseChargingSessionAction;
use Modules\Mobility\Ev\Application\Actions\StartOcppSmartChargingSessionAction;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Tests\TestCase;

class EvEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private function createStationAndCharger(): array
    {
        $station = EvStation::create([
            'station_code' => 'STN-TST-01',
            'name' => 'SPKLU SCBD Tower',
            'location_type' => 'commercial',
            'address' => 'Jl. Jend. Sudirman Kav 52-53, Jakarta',
            'transformer_capacity_kw' => 500,
            'is_active' => true,
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-TST-01',
            'type' => 'DC_FAST',
            'max_kw' => 150,
            'status' => 'available',
        ]);

        $user = User::factory()->create();

        $vehicleId = (int) Vehicle::insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B '.rand(1000, 9999).' EV',
            'vin' => 'VIN-EV-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$station, $charger, $user, $vehicleId];
    }

    public function test_start_ocpp_smart_charging_session_locks_charger_and_records_deposit(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $action = app(StartOcppSmartChargingSessionAction::class);

        $result = $action->handle($charger->id, $vehicleId, $user->id, 50000, 2450);

        $this->assertEquals('charging', $result['status']);
        $this->assertEquals(50000, $result['deposit_held_idr']);
        $this->assertEquals('charging', $charger->fresh()->status);
        $this->assertDatabaseHas('oto_ev_sessions', [
            'session_code' => $result['session_code'],
            'charger_id' => $charger->id,
            'status' => 'charging',
        ]);
    }

    public function test_settle_time_of_use_charging_session_calculates_cost_and_releases_charger(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $startAction = app(StartOcppSmartChargingSessionAction::class);
        $settleAction = app(SettleTimeOfUseChargingSessionAction::class);

        $startResult = $startAction->handle($charger->id, $vehicleId, $user->id, 50000, 2000);
        $session = $startResult['session'];

        // Settle 20 kWh (20,000 Wh) at 2000 IDR/kWh = 40,000 IDR. Deposit held is 50,000, so refund is 10,000 IDR.
        $settleResult = $settleAction->handle($session->id, 20000, 98.0, 32.0);

        $this->assertEquals(40000, $settleResult['total_cost_idr']);
        $this->assertEquals(10000, $settleResult['refund_idr']);
        $this->assertEquals(0, $settleResult['net_payable_idr']);
        $this->assertEquals('completed', $session->fresh()->status);
        $this->assertEquals('available', $charger->fresh()->status);
    }

    public function test_discharge_v2g_feed_in_credit_accrues_credit_integer(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $action = app(DischargeV2GGridFeedInCreditAction::class);

        // Discharge 10 kWh (10,000 Wh) at 2800 IDR/kWh = 28,000 IDR credit
        $result = $action->handle($station->id, $vehicleId, $user->id, 10000, 2800);

        $this->assertEquals(28000, $result['credit_earned_idr']);
        $this->assertEquals('ACCRUED', $result['status']);
    }

    public function test_append_battery_passport_lifecycle_event_builds_cryptographic_hash_chain(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $action = app(AppendBatteryPassportLifecycleEventAction::class);

        $block1 = $action->handle($vehicleId, 'SES-001', 30000, 99.0, 98.8, 30.0);
        $this->assertEquals(1, $block1->cycle_number);
        $this->assertEquals(hash('sha256', AppendBatteryPassportLifecycleEventAction::GENESIS_PREFIX.$vehicleId), $block1->prev_hash);
        $this->assertNotEmpty($block1->block_hash);

        $block2 = $action->handle($vehicleId, 'SES-002', 32000, 98.8, 98.5, 33.5);
        $this->assertEquals(2, $block2->cycle_number);
        $this->assertEquals($block1->block_hash, $block2->prev_hash);
    }

    public function test_cannot_start_charging_on_already_charging_charger(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $action = app(StartOcppSmartChargingSessionAction::class);

        $action->handle($charger->id, $vehicleId, $user->id);

        $this->expectException(\DomainException::class);
        $action->handle($charger->id, $vehicleId, $user->id);
    }

    public function test_smart_grid_http_routes_render_and_handle_requests(): void
    {
        [$station, $charger, $user, $vehicleId] = $this->createStationAndCharger();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('ev.smart-grid.ocpp-sessions'));
        $response->assertStatus(200);
        $response->assertSee('EV OCPP Smart Charging Sessions');

        $response = $this->actingAs($admin)->get(route('ev.smart-grid.tou-tariffs'));
        $response->assertStatus(200);
        $response->assertSee('Time-of-Use (TOU) Grid');

        $response = $this->actingAs($admin)->get(route('ev.smart-grid.v2g-feedin'));
        $response->assertStatus(200);
        $response->assertSee('Vehicle-to-Grid (V2G) Discharge');

        $response = $this->actingAs($admin)->get(route('ev.smart-grid.battery-passport'));
        $response->assertStatus(200);
        $response->assertSee('Buku Besar Battery Passport');

        $postResponse = $this->actingAs($admin)->post(route('ev.smart-grid.start-ocpp'), [
            'charger_id' => $charger->id,
            'vehicle_id' => $vehicleId,
            'deposit_idr' => 45000,
            'tariff_idr' => 2450,
        ]);
        $postResponse->assertRedirect(route('ev.smart-grid.ocpp-sessions'));
    }
}
