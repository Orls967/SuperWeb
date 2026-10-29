<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\CheckInGuestAction;
use Modules\Hotel\Application\Actions\CheckOutAndSettleFolioAction;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelGuestIdentity;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelCheckInOutAndHousekeepingTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelRoomType $deluxeType;

    private HotelRatePlan $barPlan;

    private HotelRoom $availableRoom;

    private HotelRoom $dirtyRoom;

    private User $foStaff;

    private User $hkStaff;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->deluxeType = HotelRoomType::where('property_id', $this->property->id)->where('code', 'DELUXE')->firstOrFail();
        $this->barPlan = HotelRatePlan::where('property_id', $this->property->id)->where('code', 'BAR')->firstOrFail();

        $this->availableRoom = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->deluxeType->id,
            'room_number' => 'DLX-101',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'inspected',
        ]);

        $this->dirtyRoom = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->deluxeType->id,
            'room_number' => 'DLX-102',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'dirty',
        ]);

        $this->foStaff = User::factory()->create(['role' => 'front_office']);
        $this->hkStaff = User::factory()->create(['role' => 'housekeeping']);
        $this->guest = User::factory()->create(['role' => 'customer']);
    }

    public function test_check_in_hanya_dapat_dilakukan_pada_kamar_berstatus_inspected_atau_available(): void
    {
        $resAction = app(ReserveRoomAction::class);
        $res = $resAction->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: Carbon::now()->toDateString(),
            checkOutDate: Carbon::now()->addDays(2)->toDateString(),
            idempotencyKey: 'key_checkin_ok_01'
        ));

        $checkInAction = app(CheckInGuestAction::class);
        $result = $checkInAction->handle(
            reservationId: $res['reservation']->id,
            roomId: $this->availableRoom->id,
            identityType: 'KTP',
            identityNumber: '3171029384720001',
            fullName: 'Budi Santoso'
        );

        $this->assertEquals('checked_in', $result['reservation']->status);
        $this->assertEquals('occupied', $result['room']->status);
    }

    public function test_check_in_ke_kamar_dirty_atau_cleaning_ditolak_dengan_galat_validasi(): void
    {
        $resAction = app(ReserveRoomAction::class);
        $res = $resAction->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: Carbon::now()->toDateString(),
            checkOutDate: Carbon::now()->addDays(2)->toDateString(),
            idempotencyKey: 'key_checkin_dirty_fail'
        ));

        $checkInAction = app(CheckInGuestAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('belum siap untuk check-in');

        $checkInAction->handle(
            reservationId: $res['reservation']->id,
            roomId: $this->dirtyRoom->id,
            identityType: 'KTP',
            identityNumber: '3171029384720001',
            fullName: 'Budi Santoso'
        );
    }

    public function test_nomor_ktp_atau_paspor_tamu_tersimpan_terenkripsi_aes_256_di_database(): void
    {
        $resAction = app(ReserveRoomAction::class);
        $res = $resAction->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: Carbon::now()->toDateString(),
            checkOutDate: Carbon::now()->addDays(2)->toDateString(),
            idempotencyKey: 'key_enc_check_01'
        ));

        $checkInAction = app(CheckInGuestAction::class);
        $result = $checkInAction->handle(
            reservationId: $res['reservation']->id,
            roomId: $this->availableRoom->id,
            identityType: 'KTP',
            identityNumber: '3171029384720001',
            fullName: 'Budi Santoso'
        );

        // Ambil nilai mentah langsung dari basis data tanpa Eloquent cast
        $rawRow = DB::table('htl_guest_identities')->where('id', $result['identity']->id)->first();
        $this->assertNotNull($rawRow);
        $this->assertNotEquals('3171029384720001', $rawRow->identity_number_encrypted);
        $this->assertNotEmpty($rawRow->identity_number_bidx);
    }

    public function test_pencarian_tamu_berdasarkan_identitas_berfungsi_menggunakan_blind_index(): void
    {
        $resAction = app(ReserveRoomAction::class);
        $res = $resAction->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: Carbon::now()->toDateString(),
            checkOutDate: Carbon::now()->addDays(2)->toDateString(),
            idempotencyKey: 'key_bidx_search_01'
        ));

        $checkInAction = app(CheckInGuestAction::class);
        $checkInAction->handle(
            reservationId: $res['reservation']->id,
            roomId: $this->availableRoom->id,
            identityType: 'KTP',
            identityNumber: '3171029384720001',
            fullName: 'Budi Santoso'
        );

        $found = HotelGuestIdentity::findByIdentityNumber('3171029384720001');
        $this->assertNotNull($found);
        $this->assertEquals('Budi Santoso', $found->full_name);
    }

    public function test_pindah_kamar_tamu_in_house_memindahkan_sisa_malam_dan_mengubah_status_kamar_lama_menjadi_dirty(): void
    {
        $resAction = app(ReserveRoomAction::class);
        $res = $resAction->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: Carbon::now()->toDateString(),
            checkOutDate: Carbon::now()->addDays(2)->toDateString(),
            idempotencyKey: 'key_move_room_01'
        ));

        $checkInAction = app(CheckInGuestAction::class);
        $checkInAction->handle(
            reservationId: $res['reservation']->id,
            roomId: $this->availableRoom->id,
            identityType: 'KTP',
            identityNumber: '3171029384720001',
            fullName: 'Budi Santoso'
        );

        $newRoom = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->deluxeType->id,
            'room_number' => 'DLX-103',
            'room_type' => 'DELUXE',
            'base_rate_idr' => 1000000,
            'status' => 'inspected',
        ]);

        $moveRes = $checkInAction->moveRoom($res['reservation']->id, $newRoom->id, (int) $this->foStaff->id);

        $this->assertEquals('dirty', $moveRes['old_room']->fresh()->status);
        $this->assertEquals('occupied', $moveRes['new_room']->fresh()->status);
    }

    public function test_perpanjangan_menginap_memverifikasi_ketersediaan_kamar_pada_malam_tambahan(): void
    {
        $resAction = app(ReserveRoomAction::class);
        $res = $resAction->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: Carbon::now()->toDateString(),
            checkOutDate: Carbon::now()->addDays(2)->toDateString(),
            idempotencyKey: 'key_extend_01'
        ));

        $checkInAction = app(CheckInGuestAction::class);
        $checkInAction->handle(
            reservationId: $res['reservation']->id,
            roomId: $this->availableRoom->id,
            identityType: 'KTP',
            identityNumber: '3171029384720001',
            fullName: 'Budi Santoso'
        );

        $extendRes = $checkInAction->extendStay($res['reservation']->id, 2);
        $this->assertEquals(Carbon::now()->addDays(4)->toDateString(), $extendRes['new_check_out_date']);
    }

    public function test_proses_check_out_otomatis_mengubah_status_kamar_menjadi_dirty(): void
    {
        $reservation = HotelReservation::where('status', 'checked_in')->firstOrFail();
        $folio = HotelFolio::where('reservation_id', $reservation->id)->firstOrFail();
        $room = HotelRoom::findOrFail($reservation->room_id);

        $action = app(CheckOutAndSettleFolioAction::class);
        $result = $action->handle(
            folioId: $folio->id,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_checkout_hk_dirty',
            paymentAmountIdr: (int) $folio->outstanding_balance
        );

        $this->assertEquals('dirty', $result['room']->fresh()->status);
        $this->assertEquals('settled', $result['folio']->fresh()->status);
    }

    public function test_check_out_wajib_memastikan_saldo_akhir_folio_adalah_nol(): void
    {
        $reservation = HotelReservation::where('status', 'checked_in')->firstOrFail();
        $folio = HotelFolio::where('reservation_id', $reservation->id)->firstOrFail();

        $action = app(CheckOutAndSettleFolioAction::class);

        // Pelunasan kurang dari saldo outstanding
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Check-out ditolak: folio masih memiliki saldo terutang');

        $action->handle(
            folioId: $folio->id,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_checkout_balance_fail',
            paymentAmountIdr: 0 // Tidak bayar apa-apa
        );
    }

    public function test_early_check_out_melepas_sisa_malam_yang_tidak_digunakan_kembali_ke_inventaris(): void
    {
        $resAction = app(ReserveRoomAction::class);
        $res = $resAction->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: Carbon::now()->toDateString(),
            checkOutDate: Carbon::now()->addDays(5)->toDateString(),
            idempotencyKey: 'key_early_out_01'
        ));

        $checkInAction = app(CheckInGuestAction::class);
        $checkInAction->handle(
            reservationId: $res['reservation']->id,
            roomId: $this->availableRoom->id,
            identityType: 'KTP',
            identityNumber: '3171029384720001',
            fullName: 'Budi Santoso'
        );

        $initialNightCount = HotelRoomNight::where('reservation_id', $res['reservation']->id)->count();
        $this->assertEquals(5, $initialNightCount);

        $checkoutAction = app(CheckOutAndSettleFolioAction::class);
        $checkoutAction->handle(
            folioId: $res['folio']->id,
            paymentMethod: 'CASH',
            idempotencyKey: 'key_early_out_checkout',
            paymentAmountIdr: 0
        );

        // Malam di masa depan (> today) sudah dilepas
        $afterCount = HotelRoomNight::where('reservation_id', $res['reservation']->id)->count();
        $this->assertLessThan($initialNightCount, $afterCount);
    }

    public function test_transisi_housekeeping_mengikuti_alur_dirty_cleaning_inspected_available(): void
    {
        $action = app(CheckInGuestAction::class);
        $room = $this->dirtyRoom;

        // Dirty -> Cleaning
        $room = $action->updateHousekeepingStatus($room->id, 'cleaning', (int) $this->hkStaff->id);
        $this->assertEquals('cleaning', $room->status);

        // Cleaning -> Inspected
        $room = $action->updateHousekeepingStatus($room->id, 'inspected', (int) $this->hkStaff->id);
        $this->assertEquals('inspected', $room->status);

        // Inspected -> Available
        $room = $action->updateHousekeepingStatus($room->id, 'available', (int) $this->hkStaff->id);
        $this->assertEquals('available', $room->status);
    }
}
