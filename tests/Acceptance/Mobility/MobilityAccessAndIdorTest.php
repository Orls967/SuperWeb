<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mobility;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;
use Modules\Fleet\database\seeders\MobilityRbacSeeder;
use Tests\TestCase;

class MobilityAccessAndIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MobilityRbacSeeder::class);
    }

    private function createEvSessionForUser(User $user): EvSession
    {
        $station = EvStation::create([
            'station_code' => 'SPKLU-ACC-01',
            'name' => 'SPKLU Grand Mall',
            'timezone' => 'Asia/Jakarta',
            'max_grid_power_kw' => 150,
            'location_type' => 'mall',
        ]);

        $charger = EvCharger::create([
            'station_id' => $station->id,
            'charger_code' => 'CHG-ACC-01',
            'charger_type' => 'DC_FAST',
            'max_power_kw' => 50,
            'status' => 'AVAILABLE',
        ]);

        $vehicleId = (int) DB::table('core_vehicles')->insertGetId([
            'user_id' => $user->id,
            'plate_number' => 'B 5555 IDR',
            'vin' => 'VIN-IDR-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return EvSession::create([
            'session_code' => 'SES-ACC-'.uniqid(),
            'charger_id' => $charger->id,
            'vehicle_id' => $vehicleId,
            'user_id' => $user->id,
            'status' => 'CHARGING',
            'deposit_held_idr' => 50000,
            'started_at' => now(),
        ]);
    }

    public function test_driver_hanya_dapat_melihat_sesi_ev_miliknya_sendiri(): void
    {
        $driver1 = User::factory()->create();
        $driver1->assignRole('driver');
        $session1 = $this->createEvSessionForUser($driver1);

        $response = $this->actingAs($driver1)->getJson("/api/oto/sessions/{$session1->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('session.id', $session1->id);
    }

    public function test_driver_dilarang_mengakses_sesi_ev_pengemudi_lain_idor_protection(): void
    {
        $driver1 = User::factory()->create();
        $driver1->assignRole('driver');
        $session1 = $this->createEvSessionForUser($driver1);

        $driver2 = User::factory()->create();
        $driver2->assignRole('driver');

        // Driver 2 mencoba mengakses sesi driver 1
        $response = $this->actingAs($driver2)->getJson("/api/oto/sessions/{$session1->id}");
        $response->assertStatus(403);
    }

    public function test_operator_ev_dapat_melihat_seluruh_sesi_stasiun(): void
    {
        $driver1 = User::factory()->create();
        $driver1->assignRole('driver');
        $session1 = $this->createEvSessionForUser($driver1);

        $operator = User::factory()->create();
        $operator->assignRole('ev_operator');

        $response = $this->actingAs($operator)->getJson("/api/oto/sessions/{$session1->id}");
        $response->assertStatus(200);
    }

    public function test_fleet_manager_dapat_mengakses_halaman_manajemen_armada(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('fleet_manager');

        $response = $this->actingAs($manager)->get('/oto/fleet');
        $response->assertStatus(200);
    }

    public function test_pengguna_tanpa_role_ditolak_mengakses_halaman_fleet(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/oto/fleet');
        $response->assertStatus(403);
    }

    public function test_bukan_mekanik_dilarang_membuka_status_grounding(): void
    {
        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->assignRole('driver');

        $response = $this->actingAs($unauthorizedUser)->postJson('/api/oto/telematics/clear-grounding', [
            'vehicle_id' => 101,
            'reason_notes' => 'Mencoba bypass grounding',
        ]);

        $response->assertStatus(403);
    }
}
