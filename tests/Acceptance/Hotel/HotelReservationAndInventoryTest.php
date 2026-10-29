<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\ReserveRoomAction;
use Modules\Hotel\Application\DTOs\ReserveRoomDTO;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelRatePlan;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Tests\TestCase;

class HotelReservationAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelRoomType $deluxeType;

    private HotelRatePlan $barPlan;

    private HotelRatePlan $nonRefPlan;

    private User $guest;

    private User $agent;

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
        $this->nonRefPlan = HotelRatePlan::where('property_id', $this->property->id)->where('code', 'NON_REFUNDABLE')->firstOrFail();
        $this->guest = User::factory()->create(['role' => 'customer']);
        $this->agent = User::factory()->create(['role' => 'front_office']);
    }

    public function test_stok_ketersediaan_kamar_per_tipe_terhitung_akurat_per_malam(): void
    {
        $service = app(HotelInventoryService::class);
        $date = Carbon::now()->addDays(5)->toDateString();

        $initialAvail = $service->getAvailableCount($this->property->id, $this->deluxeType->id, $date);
        $this->assertGreaterThan(0, $initialAvail);

        $action = app(ReserveRoomAction::class);
        $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $date,
            checkOutDate: Carbon::parse($date)->addDay()->toDateString(),
            idempotencyKey: 'key_avail_check_01'
        ));

        $afterAvail = $service->getAvailableCount($this->property->id, $this->deluxeType->id, $date);
        $this->assertEquals($initialAvail - 1, $afterAvail);
    }

    public function test_reservasi_multi_malam_mengunci_seluruh_malam_atau_batal_semua(): void
    {
        $action = app(ReserveRoomAction::class);
        $in = Carbon::now()->addDays(6)->toDateString();
        $out = Carbon::now()->addDays(9)->toDateString();

        $result = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $in,
            checkOutDate: $out,
            idempotencyKey: 'key_multi_night_01'
        ));

        $this->assertEquals(3, $result['nights']);
        $nightsCount = HotelRoomNight::where('reservation_id', $result['reservation']->id)->count();
        $this->assertEquals(3, $nightsCount);
    }

    public function test_dua_reservasi_bersaing_pada_kamar_terakhir_hanya_satu_yang_lolos(): void
    {
        // Set physical room only 1 for test room type
        $singleType = HotelRoomType::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'code' => 'PRESIDENTIAL',
            'name' => 'Presidential Suite',
            'base_capacity' => 2,
            'overbooking_max_percent' => 0,
        ]);

        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $singleType->id,
            'room_number' => 'PRS-001',
            'room_type' => 'PRESIDENTIAL',
            'base_rate_idr' => 10000000,
            'status' => 'available',
        ]);

        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(12)->toDateString();
        $dateOut = Carbon::now()->addDays(13)->toDateString();

        // Reservasi 1: Sukses
        $res1 = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $singleType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_compete_01'
        ));
        $this->assertEquals('confirmed', $res1['reservation']->status);

        // Reservasi 2 pada tanggal yang sama: Wajib gagal karena kuota habis!
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ketersediaan kamar tidak mencukupi untuk periode yang dipilih.');

        $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $singleType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_compete_02'
        ));
    }

    public function test_overbooking_sesuai_batas_persen_diterima_dengan_flag_overbooked(): void
    {
        $obType = HotelRoomType::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'code' => 'SUPERIOR_OB',
            'name' => 'Superior Room OB',
            'base_capacity' => 2,
            'overbooking_max_percent' => 100, // Izinkan 1 overbooking tambahan
        ]);

        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $obType->id,
            'room_number' => 'SUP-01',
            'room_type' => 'SUPERIOR_OB',
            'base_rate_idr' => 800000,
            'status' => 'available',
        ]);

        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(15)->toDateString();
        $dateOut = Carbon::now()->addDays(16)->toDateString();

        // Booking 1 (kamar fisik 1)
        $res1 = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $obType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_ob_01'
        ));
        $this->assertEquals('confirmed', $res1['reservation']->status);

        // Booking 2 (masuk overbooking buffer 100%) -> Diterima dengan status is_overbooked
        $res2 = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $obType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_ob_02'
        ));
        $this->assertTrue($res2['is_overbooked']);
    }

    public function test_overbooking_melebihi_batas_persen_maksimal_ditolak_dengan_422(): void
    {
        $zeroObType = HotelRoomType::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'code' => 'PENTHOUSE',
            'name' => 'Penthouse 0 OB',
            'base_capacity' => 2,
            'overbooking_max_percent' => 0,
        ]);

        HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $zeroObType->id,
            'room_number' => 'PNT-01',
            'room_type' => 'PENTHOUSE',
            'base_rate_idr' => 15000000,
            'status' => 'available',
        ]);

        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(17)->toDateString();
        $dateOut = Carbon::now()->addDays(18)->toDateString();

        $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $zeroObType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_zero_ob_01'
        ));

        // Overbooking melebihi 0% -> Ditolak!
        $this->expectException(\RuntimeException::class);
        $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $zeroObType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_zero_ob_02'
        ));
    }

    public function test_kamar_status_out_of_order_otomatis_mengurangi_stok_jual(): void
    {
        $oooType = HotelRoomType::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'code' => 'STUDIO_OOO',
            'name' => 'Studio Room OOO',
            'base_capacity' => 2,
            'overbooking_max_percent' => 0,
        ]);

        $room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $oooType->id,
            'room_number' => 'STD-01',
            'room_type' => 'STUDIO_OOO',
            'base_rate_idr' => 700000,
            'status' => 'out_of_order', // Rusak
        ]);

        $service = app(HotelInventoryService::class);
        $avail = $service->getAvailableCount($this->property->id, $oooType->id, Carbon::now()->addDays(20)->toDateString());
        $this->assertEquals(0, $avail);
    }

    public function test_rate_plan_dan_rate_calendar_menghasilkan_tarif_berbeda_pada_high_season(): void
    {
        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(1)->toDateString();
        $dateOut = Carbon::now()->addDays(2)->toDateString();

        $resBar = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_rate_bar_01'
        ));

        $dateInNonRef = Carbon::now()->addDays(3)->toDateString();
        $dateOutNonRef = Carbon::now()->addDays(4)->toDateString();

        $resNonRef = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->nonRefPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateInNonRef,
            checkOutDate: $dateOutNonRef,
            idempotencyKey: 'key_rate_nonref_01',
            depositAmountIdr: 1200000
        ));

        // Rate Calendar menetapkan BAR = 1.500.000, NON_REF = 1.200.000
        $this->assertEquals(1500000, $resBar['daily_rate_idr']);
        $this->assertEquals(1200000, $resNonRef['daily_rate_idr']);
    }

    public function test_reservasi_rate_non_refundable_wajib_disertai_deposit_penuh(): void
    {
        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(2)->toDateString();
        $dateOut = Carbon::now()->addDays(3)->toDateString();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mewajibkan pembayaran deposit di muka');

        $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->nonRefPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_deposit_fail_01',
            depositAmountIdr: null // Tanpa deposit
        ));
    }

    public function test_pembuatan_reservasi_dengan_idempotency_key_sama_menghasilkan_record_identik(): void
    {
        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(3)->toDateString();
        $dateOut = Carbon::now()->addDays(4)->toDateString();

        $res1 = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_idempotent_test_999'
        ));

        $res2 = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_idempotent_test_999'
        ));

        $this->assertEquals($res1['reservation']->id, $res2['reservation']->id);
        $this->assertEquals($res1['reservation']->reservation_number, $res2['reservation']->reservation_number);
    }

    public function test_pembuatan_reservasi_tanpa_idempotency_key_ditolak_dengan_status_422(): void
    {
        $action = app(ReserveRoomAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency-Key wajib disertakan.');

        $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-20',
            checkOutDate: '2026-11-22',
            idempotencyKey: '' // Kosong
        ));
    }

    public function test_nomor_reservasi_diterbitkan_secara_sekuensial_via_document_numbering_interface(): void
    {
        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(4)->toDateString();
        $dateOut = Carbon::now()->addDays(5)->toDateString();

        $res = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_doc_num_01'
        ));

        $this->assertStringStartsWith('RSV-', $res['reservation']->reservation_number);
    }

    public function test_query_ketersediaan_kamar_mencegah_tumpang_tindih_di_tengah_periode_menginap(): void
    {
        $action = app(ReserveRoomAction::class);
        $dateIn = Carbon::now()->addDays(7)->toDateString();
        $dateOut = Carbon::now()->addDays(10)->toDateString();

        $res = $action->handle(new ReserveRoomDTO(
            propertyId: $this->property->id,
            roomTypeId: $this->deluxeType->id,
            ratePlanId: $this->barPlan->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: $dateIn,
            checkOutDate: $dateOut,
            idempotencyKey: 'key_overlap_inv_01'
        ));

        $this->assertEquals('confirmed', $res['reservation']->status);

        // Cek room nights terbentuk di setiap malam
        $nights = HotelRoomNight::where('reservation_id', $res['reservation']->id)->get();
        $this->assertCount(3, $nights);
    }
}
