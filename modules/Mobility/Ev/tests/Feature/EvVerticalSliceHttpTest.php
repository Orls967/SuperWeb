<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\tests\Feature;

use App\Models\User;
use App\Quality\Audit\Fixtures\EvAuditCorruptionFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Contracts\Vehicle;
use Modules\Mobility\Ev\database\seeders\EvDemoSeeder;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSession;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Tests\TestCase;

class EvVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EvDemoSeeder)->run();
    }

    public function test_a_authorized_user_can_access_ev_index_and_reserve(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $charger = EvCharger::firstOrFail();
        $vehicle = Vehicle::firstOrFail();

        // 1. Access index
        $response = $this->actingAs($driver)->get(route('ev.index'));
        $response->assertOk();
        $response->assertSeeText('Ekosistem EV & Jaringan Charging SPKLU');
        $response->assertSee($charger->charger_code);

        // 2. Reserve slot
        $reserveResponse = $this->actingAs($driver)->post(route('ev.chargers.reserve', $charger), [
            'vehicle_id' => $vehicle->id,
            'duration_minutes' => 45,
        ]);

        $reserveResponse->assertRedirect(route('ev.index'));
        $reserveResponse->assertSessionHas('success');

        $this->assertDatabaseHas('oto_ev_sessions', [
            'charger_id' => $charger->id,
            'user_id' => $driver->id,
            'status' => 'reserved',
        ]);
    }

    public function test_b_unauthorized_role_is_forbidden_403(): void
    {
        $guest = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($guest)->get(route('ev.index'));
        $response->assertForbidden();
    }

    public function test_c_unauthenticated_user_is_redirected_to_login_302(): void
    {
        $response = $this->get(route('ev.index'));
        $response->assertRedirect('/login');
    }

    public function test_d_two_source_ev_audit_passes_cleanly(): void
    {
        $code = Artisan::call('ev:audit');
        $this->assertSame(0, $code);
    }

    public function test_e_ev_audit_fails_when_data_is_corrupted(): void
    {
        $fixture = new EvAuditCorruptionFixture;
        $fixture->corrupt();

        $code = Artisan::call('ev:audit');
        $this->assertSame(1, $code);
    }

    public function test_f_check_in_and_complete_session_role_restrictions(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $wallet = $driver->walletAccount('IDR');
        $wallet->forceFill(['cached_balance' => '1000000'])->save();
        $operator = User::factory()->create(['role' => 'station_operator']);
        $charger = EvCharger::firstOrFail();
        $vehicle = Vehicle::firstOrFail();

        // Create reserved session
        $session = EvSession::create([
            'session_code' => 'EV-HTTP-TEST-01',
            'charger_id' => $charger->id,
            'vehicle_id' => $vehicle->id,
            'user_id' => $driver->id,
            'reserved_from' => now(),
            'reserved_to' => now()->addMinutes(30),
            'tariff_per_kwh_idr' => 2500,
            'status' => 'reserved',
        ]);

        // Driver can check-in via QR
        $checkInResponse = $this->actingAs($driver)->post(route('ev.chargers.checkin', $charger), [
            'session_id' => $session->id,
            'qr_code' => $charger->charger_code,
        ]);
        $checkInResponse->assertRedirect(route('ev.index'));
        $checkInResponse->assertSessionHas('success');

        $session->refresh();
        $this->assertEquals('plugged_in', $session->status);

        // Driver cannot complete session (restricted to admin/operator) -> 403
        $driverCompleteResponse = $this->actingAs($driver)->post(route('ev.sessions.complete', $session), [
            'energy_wh' => 25000,
        ]);
        $driverCompleteResponse->assertForbidden();

        // Operator can complete session -> 302 redirect
        $operatorCompleteResponse = $this->actingAs($operator)->post(route('ev.sessions.complete', $session), [
            'energy_wh' => 25000,
            'peak_cell_temp_c' => 36.5,
        ]);
        $operatorCompleteResponse->assertRedirect(route('ev.index'));
        $operatorCompleteResponse->assertSessionHas('success');

        $session->refresh();
        $this->assertEquals('completed', $session->status);
    }

    public function test_g_grid_load_balance_endpoint_role_restrictions(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $operator = User::factory()->create(['role' => 'station_operator']);
        $station = EvStation::firstOrFail();

        // Driver cannot trigger load balancing -> 403
        $driverResponse = $this->actingAs($driver)->post(route('ev.stations.loadbalance', $station));
        $driverResponse->assertForbidden();

        // Operator can trigger load balancing -> 302
        $operatorResponse = $this->actingAs($operator)->post(route('ev.stations.loadbalance', $station));
        $operatorResponse->assertRedirect(route('ev.index'));
        $operatorResponse->assertSessionHas('success');
    }
}
