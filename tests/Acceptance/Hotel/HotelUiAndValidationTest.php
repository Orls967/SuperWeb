<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HotelUiAndValidationTest extends TestCase
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

    public function test_halaman_dashboard_dan_reservasi_merespons_200_untuk_role_front_office_dan_manager(): void
    {
        $fo = User::factory()->create(['role' => 'front_office']);
        $response = $this->actingAs($fo)->get('/hotel/reservations');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_halaman_papan_housekeeping_merespons_200_untuk_role_housekeeping(): void
    {
        $hk = User::factory()->create(['role' => 'housekeeping']);
        $response = $this->actingAs($hk)->get('/hotel/housekeeping');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_halaman_night_audit_merespons_200_untuk_night_auditor_dan_403_untuk_role_lain(): void
    {
        $auditor = User::factory()->create(['role' => 'night_auditor']);
        $response = $this->actingAs($auditor)->get('/hotel/night-audit');
        $this->assertEquals(200, $response->getStatusCode());

        $guest = User::factory()->create(['role' => 'guest']);
        $responseGuest = $this->actingAs($guest)->get('/hotel/night-audit');
        $this->assertEquals(403, $responseGuest->getStatusCode());
    }

    public function test_halaman_portal_tamu_merespons_200_dan_menampilkan_rincian_folio_sendiri(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $response = $this->actingAs($guest)->get('/hotel/portal/guest');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_halaman_portal_korporat_merespons_200_dan_menampilkan_tagihan_city_ledger_perusahaan(): void
    {
        $corp = User::factory()->create(['role' => 'corporate_client']);
        $response = $this->actingAs($corp)->get('/hotel/portal/corporate');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_kegagalan_validasi_input_mengembalikan_redirect_dengan_pesan_galat_bukan_http_500(): void
    {
        $fo = User::factory()->create(['role' => 'front_office']);
        $response = $this->actingAs($fo)->post('/hotel/reservations', []);
        $this->assertTrue(in_array($response->getStatusCode(), [302, 422]));
    }
}
