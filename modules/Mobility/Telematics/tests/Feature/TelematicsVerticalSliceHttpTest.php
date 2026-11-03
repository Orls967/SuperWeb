<?php

declare(strict_types=1);

namespace Modules\Mobility\Telematics\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Vehicle;
use Modules\Mobility\Telematics\Domain\Models\TelematicsDevice;
use Tests\TestCase;

class TelematicsVerticalSliceHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_telematics_dashboard(): void
    {
        $response = $this->get('/telematics');
        $response->assertRedirect('/login');
    }

    public function test_customer_role_is_forbidden_from_telematics_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get('/telematics');
        $response->assertStatus(403);
    }

    public function test_fleet_manager_can_access_telematics_dashboard(): void
    {
        $fleetManager = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->actingAs($fleetManager)->get('/telematics');
        $response->assertStatus(200);
        $response->assertSee('Telematics & Fleet IoT Connected Car');
        $response->assertSee('MAPE Prediksi Kerusakan');
        $response->assertSee('Agregasi 5-Menitan');
    }

    public function test_admin_can_access_telematics_dashboard_and_see_kpi_cards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/telematics');
        $response->assertStatus(200);
        $response->assertSee('Antrean Draf Booking');
        $response->assertSee('Pendapatan Preventif');
        $response->assertSee('Unit Grounded');
    }

    public function test_admin_can_ingest_telematics_tick_via_post_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate_number' => 'B 9911 TLM',
            'vin' => 'VIN-HTTP-TLM-9911',
            'status' => 'active',
        ]);

        $device = TelematicsDevice::create([
            'device_id' => 'DEV-HTTP-001',
            'vehicle_id' => $vehicle->id,
            'serial_number' => 'SN-HTTP-9911',
            'protocol' => 'OBD2_CAN',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post("/telematics/devices/{$device->id}/ingest", [
            'oil_temp_c' => 93.5,
            'battery_voltage' => 12.4,
            'fuel_level_pct' => 82.0,
            'speed_kmh' => 70.0,
            'rpm' => 2600,
        ]);

        $response->assertRedirect('/telematics');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('oto_telematics_ticks', [
            'device_id' => 'DEV-HTTP-001',
            'oil_temp_c' => 93.5,
        ]);

        $this->assertDatabaseHas('oto_telematics_aggregates', [
            'device_id' => 'DEV-HTTP-001',
            'tick_count' => 1,
        ]);
    }
}
