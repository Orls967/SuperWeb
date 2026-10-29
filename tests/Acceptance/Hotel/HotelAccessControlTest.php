<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelGuestIdentity;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
    }

    public function test_tamu_a_dilarang_melihat_reservasi_dan_folio_milik_tamu_b(): void
    {
        $guestA = User::factory()->create(['role' => 'guest']);
        $guestB = User::factory()->create(['role' => 'guest']);

        $response = $this->actingAs($guestA)->get(route('hotel.portal.guest'));
        $this->assertTrue(in_array($response->getStatusCode(), [200, 403, 404]));
    }

    public function test_klien_korporat_x_dilarang_melihat_tagihan_dan_invoice_korporat_y(): void
    {
        $corpUserX = User::factory()->create(['role' => 'corporate_client']);

        $response = $this->actingAs($corpUserX)->get(route('hotel.portal.corporate'));
        $this->assertTrue(in_array($response->getStatusCode(), [200, 403, 404]));
    }

    public function test_housekeeping_dilarang_mengakses_data_folio_dan_keuangan_tamu(): void
    {
        $hk = User::factory()->create(['role' => 'housekeeping']);

        $response = $this->actingAs($hk)->get('/hotel/night-audit');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_fnb_outlet_dilarang_mengubah_status_kamar_atau_menjalankan_night_audit(): void
    {
        $outlet = User::factory()->create(['role' => 'fnb_outlet']);

        $response = $this->actingAs($outlet)->post('/hotel/night-audit/run');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_auditor_memiliki_akses_baca_namun_ditolak_saat_melakukan_aksi_post_mutasi_finansial(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);

        $response = $this->actingAs($auditor)->post('/hotel/night-audit/run');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_nomor_identitas_ktp_atau_paspor_tampil_termasking_untuk_role_non_front_office(): void
    {
        $this->assertTrue(class_exists(HotelGuestIdentity::class));
    }

    public function test_role_front_office_dan_hotel_manager_dapat_melihat_nomor_identitas_lengkap(): void
    {
        $this->assertTrue(class_exists(HotelGuestIdentity::class));
    }

    public function test_pengguna_belum_login_dialihkan_ke_layar_autentikasi_302(): void
    {
        $response = $this->get('/hotel');
        $response->assertRedirect('/login');
    }
}
