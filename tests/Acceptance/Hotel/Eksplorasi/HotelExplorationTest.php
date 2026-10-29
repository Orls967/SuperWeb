<?php

declare(strict_types=1);

namespace Tests\Acceptance\Hotel\Eksplorasi;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Hotel\Application\Actions\ExecuteOverbookingWalkCompensationAction;
use Modules\Hotel\Application\Actions\ReserveRoomWithInventoryNightLockAction;
use Modules\Hotel\Application\Actions\RunIdempotentHotelNightAuditAction;
use Modules\Hotel\Application\Actions\SettleSplitGuestFolioCorporatePersonalAction;
use Modules\Hotel\database\seeders\HotelDemoSeeder;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Tests\TestCase;

class HotelExplorationTest extends TestCase
{
    use RefreshDatabase;

    private HotelProperty $property;

    private HotelRoom $room;

    private User $guest;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            LedgerAccountsSeeder::class,
            HotelDemoSeeder::class,
        ]);

        $this->property = HotelProperty::firstOrFail();
        $this->room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_number' => 'TEST-999',
            'room_type' => 'deluxe',
            'base_rate_idr' => 1000000,
            'status' => 'available',
        ]);
        $this->guest = User::factory()->create(['role' => 'customer']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * T1.b 1) ReserveRoomWithInventoryNightLockAction:
     * Buktikan kelemahan anti-overlap & tidak adanya inventaris per malam.
     */
    public function test_eksplorasi_reserve_action_flaws(): void
    {
        $action = app(ReserveRoomWithInventoryNightLockAction::class);

        // Booking 1: 2026-11-01 s/d 2026-11-05 (4 malam)
        $res1 = $action->handle(
            propertyId: $this->property->id,
            roomId: $this->room->id,
            guestUserId: (string) $this->guest->id,
            checkInDate: '2026-11-01',
            checkOutDate: '2026-11-05',
            dailyRateIdr: 1000000
        );

        $this->assertEquals('confirmed', $res1['reservation']->status);

        // Uji Overlap Terjepit: Tamu lain ingin 2026-11-02 s/d 2026-11-03 (di tengah-tengah booking 1)
        // Logika lama di ReserveRoomWithInventoryNightLockAction:
        //   where(function ($q) use ($in, $out) {
        //       $q->whereBetween('check_in_date', [$in, $out])
        //         ->orWhereBetween('check_out_date', [$in, $out]);
        //   })
        // Pada booking 1: check_in_date = 2026-11-01, check_out_date = 2026-11-05
        // Keduanya TIDAK between 2026-11-02 dan 2026-11-03!
        // Akibatnya: Action lama MENGIZINKAN double booking!
        $doubleBooked = false;
        try {
            $res2 = $action->handle(
                propertyId: $this->property->id,
                roomId: $this->room->id,
                guestUserId: (string) $this->guest->id,
                checkInDate: '2026-11-02',
                checkOutDate: '2026-11-03',
                dailyRateIdr: 1000000
            );
            $doubleBooked = true; // Terbukti cacat: berhasil double book!
        } catch (\RuntimeException $e) {
            $doubleBooked = false;
        }

        // Simpan temuan: Action lama membiarkan double-booking karena query overlap cacat
        $this->assertTrue($doubleBooked, 'BUKTI CACAT: ReserveRoomWithInventoryNightLockAction mengizinkan booking di tengah masa inap karena query overlap cacat');
    }

    /**
     * T1.b 2) RunIdempotentHotelNightAuditAction:
     * Buktikan TIDAK idempoten, dobel posting pendapatan, tidak ada posting ledger, tidak memajukan business date.
     */
    public function test_eksplorasi_night_audit_flaws(): void
    {
        $action = app(RunIdempotentHotelNightAuditAction::class);
        $reservation = HotelReservation::where('status', 'checked_in')->firstOrFail();
        $folio = HotelFolio::where('reservation_id', $reservation->id)->firstOrFail();

        $initialCharges = $folio->total_room_charges;
        $initialBalance = $folio->outstanding_balance;
        $initialLedgerTxCount = LedgerTransaction::count();

        $auditDateStr = Carbon::parse($reservation->check_in_date)->toDateString();

        // Jalankan Night Audit pertama untuk tanggal check_in
        $res1 = $action->handle(
            propertyId: $this->property->id,
            auditDate: $auditDateStr,
            totalAvailableRooms: 10
        );

        $folio->refresh();
        $chargesAfterFirst = $folio->total_room_charges;
        $this->assertEquals($initialCharges + $reservation->daily_rate_idr, $chargesAfterFirst);

        // Jalankan Night Audit KEDUA KALI pada audit_date yang SAMA
        $res2 = $action->handle(
            propertyId: $this->property->id,
            auditDate: $auditDateStr,
            totalAvailableRooms: 10
        );

        $folio->refresh();
        $chargesAfterSecond = $folio->total_room_charges;

        // BUKTI TIDAK IDEMPOTEN: Saldo bertambah lagi!
        $this->assertEquals($chargesAfterFirst + $reservation->daily_rate_idr, $chargesAfterSecond, 'BUKTI CACAT: Night audit lama menambah tagihan dua kali untuk tanggal yang sama (TIDAK idempoten)');

        // BUKTI TIDAK POSTING KE LEDGER: Ledger transactions tidak bertambah
        $this->assertEquals($initialLedgerTxCount, LedgerTransaction::count(), 'BUKTI CACAT: Night audit lama sama sekali tidak membuat LedgerTransaction riil');
    }

    /**
     * T1.b 3) SettleSplitGuestFolioCorporatePersonalAction:
     * Buktikan tidak ada pemeriksaan batas kredit perusahaan, tidak ada posting ledger, key non-standar.
     */
    public function test_eksplorasi_settle_split_flaws(): void
    {
        $action = app(SettleSplitGuestFolioCorporatePersonalAction::class);
        $reservation = HotelReservation::where('status', 'checked_in')->firstOrFail();
        $folio = HotelFolio::where('reservation_id', $reservation->id)->firstOrFail();

        $folioBalance = (int) $folio->outstanding_balance;
        $initialLedgerTxCount = LedgerTransaction::count();

        // Split sembarangan tanpa company_id / batas kredit
        $result = $action->handle(
            folioId: $folio->id,
            corporateRoomShareIdr: $folioBalance,
            guestPersonalAddonIdr: 0
        );

        $this->assertEquals('settled', $result['folio']->status);
        $this->assertEquals(0, $result['folio']->outstanding_balance);

        // BUKTI: Tidak ada verifikasi kredit perusahaan dan tidak ada posting ledger
        $this->assertEquals($initialLedgerTxCount, LedgerTransaction::count(), 'BUKTI CACAT: Settle split tidak memposting transaksi ke Ledger perbankan');
    }

    /**
     * T1.b 4) ExecuteOverbookingWalkCompensationAction:
     * Buktikan kompensasi walk tidak memposting ke ledger (hanya mutasi status & outbox).
     */
    public function test_eksplorasi_walk_flaws(): void
    {
        $action = app(ExecuteOverbookingWalkCompensationAction::class);
        $reservation = HotelReservation::where('status', 'confirmed')->first();
        if (! $reservation) {
            $reservation = HotelReservation::create([
                'id' => (string) Str::uuid(),
                'property_id' => $this->property->id,
                'room_id' => $this->room->id,
                'reservation_number' => 'HTL-RES-WALK01',
                'guest_user_id' => (string) $this->guest->id,
                'check_in_date' => Carbon::now()->toDateString(),
                'check_out_date' => Carbon::now()->addDays(2)->toDateString(),
                'daily_rate_idr' => 1000000,
                'total_room_charge_idr' => 2000000,
                'status' => 'confirmed',
            ]);
        }

        $initialLedgerTxCount = LedgerTransaction::count();

        $result = $action->handle(
            reservationId: $reservation->id,
            alternatePropertyName: 'Partner Grand Resort'
        );

        $this->assertEquals('RELOCATED_WALK', $result['reservation']->status);
        $this->assertGreaterThan(0, $result['total_compensation_idr']);

        // BUKTI: Tidak ada posting ledger beban kompensasi
        $this->assertEquals($initialLedgerTxCount, LedgerTransaction::count(), 'BUKTI CACAT: Walk compensation tidak mencatat beban & kewajiban di Ledger');
    }

    /**
     * T1.d Rute GET Hotel: Uji akses setiap rute GET sebagai role berhak.
     */
    public function test_eksplorasi_get_routes_status(): void
    {
        $routes = [
            'hotel.index' => [],
            'hotel.pms.banquet' => [],
            'hotel.pms.destination-packages' => [],
            'hotel.pms.night-audit' => [],
            'hotel.pms.overbooking' => [],
            'hotel.pms.reservations' => [],
            'hotel.pms.split-folios' => [],
            'hotel.pms.timeshare' => [],
            'hotel.pms.yield-rates' => [],
        ];

        foreach ($routes as $routeName => $params) {
            $response = $this->actingAs($this->admin)->get(route($routeName, $params));
            $this->assertEquals(200, $response->getStatusCode(), "Rute {$routeName} gagal dengan status {$response->getStatusCode()}");
        }
    }
}
