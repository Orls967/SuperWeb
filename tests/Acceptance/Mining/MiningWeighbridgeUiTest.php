<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Tests\TestCase;

class MiningWeighbridgeUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(MiningRbacSeeder::class);
    }

    public function test_rute_mining_weighbridge_menampilkan_formulir_timbangan(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('weighbridge_operator');

        $response = $this->actingAs($operator)->get('/mining/weighbridge');
        $response->assertStatus(200);
        $response->assertSee('Jembatan Timbang');
        $response->assertSee('Nomor Plat Truk');
        $response->assertSee('Berat Bruto (kg)');
        $response->assertSee('Berat Tara (kg)');
    }

    public function test_antarmuka_responsif_dioptimasi_untuk_viewport_mobile_375px(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('weighbridge_operator');

        $response = $this->actingAs($operator)->get('/mining/weighbridge');
        $response->assertStatus(200);

        // Mobile viewport meta tag and responsive container
        $response->assertSee('width=device-width', false);
        $response->assertSee('max-w-sm', false); // 384px mobile width container
    }

    public function test_formulir_menampilkan_indikator_status_timbangan_dan_tombol_simpan(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('weighbridge_operator');

        $response = $this->actingAs($operator)->get('/mining/weighbridge');
        $response->assertStatus(200);
        $response->assertSee('Simpan Tiket Timbang');
        $response->assertSee('Overload Warning');
    }

    public function test_role_non_weighbridge_operator_ditolak_403_saat_membuka_ui(): void
    {
        $engineer = User::factory()->create();
        $engineer->assignRole('mine_engineer');

        $response = $this->actingAs($engineer)->get('/mining/weighbridge');
        $response->assertStatus(403);
    }
}
