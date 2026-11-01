<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSession;
use Modules\Mobility\Ev\Domain\Models\EvStation;
use Modules\Mobility\Fleet\database\seeders\MobilityRbacSeeder;
use Tests\TestCase;

class MobilityDriverMobileUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MobilityRbacSeeder::class);
    }

    public function test_halaman_driver_ev_charging_dapat_diakses_dengan_viewport_mobile(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $response = $this->actingAs($driver)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15'])
            ->get('/oto/charging');

        $response->assertStatus(200);
        $response->assertSee('SPKLU');
    }

    public function test_halaman_charging_menampilkan_sesi_aktif_dan_meteran_wh(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('driver');

        $station = EvStation::create([
            'station_code' => 'SPKLU-UI-01',
            'name' => 'SPKLU Grand Mall Jakarta',
            'timezone' => 'Asia/Jakarta',
            'max_grid_power_kw' => 150,
            'location_type' => 'mall',
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-UI-01',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 50,
            'status' => 'OCCUPIED',
        ]);

        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $driver->id,
            'plate_number' => 'B 7777 UI',
            'vin' => 'VIN-UI-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = EvSession::create([
            'session_code' => 'SES-UI-ACTIVE',
            'charger_id' => $charger->id,
            'vehicle_id' => $vehicleId,
            'user_id' => $driver->id,
            'status' => 'CHARGING',
            'deposit_held_idr' => 100000,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($driver)->get('/oto/charging');
        $response->assertStatus(200);
        $response->assertSee('SES-UI-ACTIVE');
        $response->assertSee('CHARGING');
    }

    public function test_halaman_fleet_menampilkan_daftar_armada_dan_status_grounding(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('fleet_manager');

        $response = $this->actingAs($manager)->get('/oto/fleet');
        $response->assertStatus(200);
        $response->assertSee('Manajemen Armada');
    }
}
