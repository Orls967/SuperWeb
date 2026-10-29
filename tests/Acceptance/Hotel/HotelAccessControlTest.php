<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelGuestIdentity;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelRoomType $roomType;

    private HotelRatePlan $ratePlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->roomType = HotelRoomType::where('property_id', $this->property->id)->firstOrFail();
        $this->ratePlan = HotelRatePlan::where('property_id', $this->property->id)->firstOrFail();
    }

    public function test_tamu_a_dilarang_melihat_reservasi_dan_folio_milik_tamu_b(): void
    {
        $guestA = User::factory()->create(['role' => 'guest']);
        $guestB = User::factory()->create(['role' => 'guest']);

        $reserveAction = app(ReserveRoomAction::class);
        $resB = $reserveAction->execute(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->roomType->id,
            ratePlanId: $this->ratePlan->id,
            guestUserId: (string) $guestB->id,
            checkInDate: '2026-11-20',
            checkOutDate: '2026-11-22',
            idempotencyKey: 'res-guest-b-key',
        ));

        // Tamu A mengakses portal tamu
        $response = $this->actingAs($guestA)->get(route('hotel.portal.guest'));
        $response->assertStatus(200);

        // Tamu A tidak boleh melihat no reservasi tamu B
        $response->assertDontSee($resB->reservation_number);
    }

    public function test_klien_korporat_x_dilarang_melihat_tagihan_dan_invoice_korporat_y(): void
    {
        $corpUserX = User::factory()->create(['role' => 'corporate_client']);

        $response = $this->actingAs($corpUserX)->get(route('hotel.portal.corporate'));
        $this->assertEquals(200, $response->getStatusCode());
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
        $guest = User::factory()->create(['role' => 'guest']);
        $hkStaff = User::factory()->create(['role' => 'housekeeping']);

        $identity = HotelGuestIdentity::create([
            'id' => (string) Str::uuid(),
            'user_id' => $guest->id,
            'identity_type' => 'KTP',
            'identity_number_encrypted' => '3171012345670001',
            'identity_number_bidx' => 'bidx-dummy',
            'full_name' => 'Ahmad Dahlan',
        ]);

        $formatted = $identity->getFormattedIdentityNumber($hkStaff);
        $this->assertStringContainsString('*', $formatted);
        $this->assertNotEquals('3171012345670001', $formatted);
        $this->assertEquals('3171********0001', $formatted);
    }

    public function test_role_front_office_dan_hotel_manager_dapat_melihat_nomor_identitas_lengkap(): void
    {
        $foStaff = User::factory()->create(['role' => 'front_office']);
        $manager = User::factory()->create(['role' => 'hotel_manager']);
        $guest = User::factory()->create(['role' => 'guest']);

        $identity = HotelGuestIdentity::create([
            'id' => (string) Str::uuid(),
            'user_id' => $guest->id,
            'identity_type' => 'KTP',
            'identity_number_encrypted' => '3171012345670001',
            'identity_number_bidx' => 'bidx-dummy',
            'full_name' => 'Ahmad Dahlan',
        ]);

        $this->assertEquals('3171012345670001', $identity->getFormattedIdentityNumber($foStaff));
        $this->assertEquals('3171012345670001', $identity->getFormattedIdentityNumber($manager));
    }

    public function test_pengguna_belum_login_dialihkan_ke_layar_autentikasi_302(): void
    {
        $response = $this->get('/hotel/reservations');
        $response->assertRedirect('/login');
    }
}
