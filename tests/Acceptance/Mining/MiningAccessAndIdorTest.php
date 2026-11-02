<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\RecordWeighbridgeTicketAction;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Tests\TestCase;

class MiningAccessAndIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MiningRbacSeeder::class);
    }

    public function test_seeder_rbac_mining_mendaftarkan_seluruh_peran_secara_idempoten(): void
    {
        $this->seed(MiningRbacSeeder::class);

        $roles = [
            'weighbridge_operator',
            'dispatcher_mine',
            'mine_engineer',
            'finance_officer',
            'hse_officer',
            'mine_manager',
        ];

        foreach ($roles as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_rute_mining_weighbridge_hanya_dapat_diakses_role_weighbridge_operator(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('weighbridge_operator');

        $response = $this->actingAs($operator)->get('/mining/weighbridge');
        $response->assertStatus(200);

        // Unauthorized user without role
        $stranger = User::factory()->create();
        $responseStranger = $this->actingAs($stranger)->get('/mining/weighbridge');
        $responseStranger->assertStatus(403);
    }

    public function test_rute_mining_dispatch_hanya_dapat_diakses_role_dispatcher_mine(): void
    {
        $dispatcher = User::factory()->create();
        $dispatcher->assignRole('dispatcher_mine');

        $response = $this->actingAs($dispatcher)->get('/mining/dispatch');
        $response->assertStatus(200);

        // Operator cannot access dispatch
        $operator = User::factory()->create();
        $operator->assignRole('weighbridge_operator');
        $responseOperator = $this->actingAs($operator)->get('/mining/dispatch');
        $responseOperator->assertStatus(403);
    }

    public function test_role_operator_tidak_dapat_mengakses_menu_admin_atau_royalti(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('weighbridge_operator');

        $this->actingAs($operator)->get('/mining/admin')->assertStatus(403);
        $this->actingAs($operator)->get('/mining/royalties')->assertStatus(403);
    }

    public function test_proteksi_idor_operator_site_a_tidak_dapat_merekam_tiket_site_b(): void
    {
        $siteA = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-A',
            'name' => 'Site A',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Pomalaa',
            'status' => 'ACTIVE',
        ]);

        $siteB = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-B',
            'name' => 'Site B',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Morowali',
            'status' => 'ACTIVE',
        ]);

        $pitB = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteB->id,
            'pit_code' => 'PIT-B',
            'name' => 'Pit B',
            'target_production_ton' => 10000,
            'status' => 'ACTIVE',
        ]);
        $areaB = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteB->id,
            'pit_id' => $pitB->id,
            'area_code' => 'AREA-B',
            'name' => 'Area B',
            'status' => 'ACTIVE',
        ]);
        $truckB = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteB->id,
            'equipment_code' => 'TRK-B',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);
        $stockpileB = MiningStockpile::create([
            'id' => (string) Str::uuid(),
            'site_id' => $siteB->id,
            'stockpile_code' => 'STK-B',
            'name' => 'Stockpile B',
            'total_kg' => 0,
            'sum_kg_calories' => 0,
            'sum_kg_ash_bps' => 0,
            'sum_kg_sulfur_bps' => 0,
            'sum_kg_moisture_bps' => 0,
            'sum_kg_metal_bps' => 0,
        ]);

        // Attempting to cross site boundaries (Site A with Pit/Truck from Site B) must be rejected
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/mismatch|site id/i');

        $action = app(RecordWeighbridgeTicketAction::class);
        $action->handle(
            siteId: $siteA->id, // Mismatch with Pit/Truck from Site B!
            pitId: $pitB->id,
            areaId: $areaB->id,
            truckId: $truckB->id,
            driverPartyId: 'DVR-B',
            destinationStockpileId: $stockpileB->id,
            grossWeightKg: 80000,
            tareWeightKg: 35000,
            gradeSpecs: [],
            idempotencyKey: 'min:wb:idor:001'
        );
    }

    public function test_pengguna_tanpa_autentikasi_dialihkan_ke_login(): void
    {
        $response = $this->get('/mining/weighbridge');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
