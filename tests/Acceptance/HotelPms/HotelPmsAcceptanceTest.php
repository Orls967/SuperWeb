<?php

declare(strict_types=1);

namespace Tests\Acceptance\HotelPms;

use App\Models\User;
use App\Quality\Audit\Fixtures\HtlAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Core\Application\Services\RbacService;
use Modules\Hotel\Application\Actions\CheckInWithDigitalKeyAndApoaAction;
use Modules\Hotel\Application\Actions\ReserveRoomCentralizedCrsAction;
use Modules\Hotel\Application\Actions\ResolveServiceRecoveryAction;
use Modules\Hotel\Application\Actions\RunNightAuditFase91Action;
use Modules\Hotel\Domain\Algorithms\BestAvailableRateCalculator;
use Modules\Hotel\Domain\Algorithms\HvacSetbackController;
use Modules\Hotel\Domain\Algorithms\OverbookingLimitOptimizer;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;
use Modules\Hotel\Domain\StateMachines\ReservationStateMachine;
use Tests\TestCase;

final class HotelPmsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $frontOffice;

    private User $frontDesk;

    private User $revenueMgr;

    private User $housekeepingStaff;

    private User $engineeringStaff;

    private User $hotelGm;

    private User $guestUser;

    private User $otherGuestUser;

    private User $unauthorizedUser;

    private HotelProperty $property;

    private HotelRoomType $roomType;

    private HotelRoom $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);

        $rbac = app(RbacService::class);
        $rbac->ensureRole('front_office', 'Front Office', 'Front Office Desk');
        $rbac->ensureRole('front_desk', 'Front Desk', 'Front Desk Staff');
        $rbac->ensureRole('revenue_mgr', 'Revenue Manager', 'Revenue and Yield Manager');
        $rbac->ensureRole('housekeeping', 'Housekeeping', 'Housekeeping Staff');
        $rbac->ensureRole('engineering', 'Engineering', 'Engineering and Maintenance');
        $rbac->ensureRole('hotel_gm', 'Hotel General Manager', 'General Manager Hotel');
        $rbac->ensureRole('guest', 'Hotel Guest', 'Registered Hotel Guest');
        $rbac->ensureRole('unauthorized', 'Unauthorized User', 'Regular unauthorized user');

        $this->frontOffice = User::factory()->create(['name' => 'Front Office Staff']);
        $rbac->assignRole($this->frontOffice, 'front_office');

        $this->frontDesk = User::factory()->create(['name' => 'Front Desk Staff']);
        $rbac->assignRole($this->frontDesk, 'front_desk');

        $this->revenueMgr = User::factory()->create(['name' => 'Revenue Manager']);
        $rbac->assignRole($this->revenueMgr, 'revenue_mgr');

        $this->housekeepingStaff = User::factory()->create(['name' => 'Housekeeping Worker']);
        $rbac->assignRole($this->housekeepingStaff, 'housekeeping');

        $this->engineeringStaff = User::factory()->create(['name' => 'Engineer On Duty']);
        $rbac->assignRole($this->engineeringStaff, 'engineering');

        $this->hotelGm = User::factory()->create(['name' => 'General Manager']);
        $rbac->assignRole($this->hotelGm, 'hotel_gm');

        $this->guestUser = User::factory()->create(['name' => 'Valued Guest', 'email' => 'valued_guest@example.com']);
        $rbac->assignRole($this->guestUser, 'guest');

        $this->otherGuestUser = User::factory()->create(['name' => 'Other Guest', 'email' => 'other_guest@example.com']);
        $rbac->assignRole($this->otherGuestUser, 'guest');

        $this->unauthorizedUser = User::factory()->create(['name' => 'Plain User']);
        $rbac->assignRole($this->unauthorizedUser, 'unauthorized');

        // Setup Properti & Kamar dengan UUID
        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-01',
            'name' => 'The Grand Luxury Resort Bali',
            'property_type' => 'RESORT',
            'city' => 'Badung',
            'total_rooms' => 100,
        ]);

        $this->roomType = HotelRoomType::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'name' => 'Deluxe Ocean View',
            'code' => 'DLX-OCN',
            'base_capacity' => 2,
            'overbooking_max_percent' => 3,
        ]);

        $this->room = HotelRoom::create([
            'id' => (string) Str::uuid(),
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => '101',
            'room_type' => 'deluxe',
            'base_rate_idr' => 1_200_000,
            'status' => 'available',
        ]);
    }

    /**
     * Test 1: Kontrol Akses dan Otorisasi Rute PMS Fase 91.
     */
    public function test_01_access_control_and_route_authorization_pms(): void
    {
        // Front desk dashboard
        $this->actingAs($this->frontOffice)->get('/hotel/front-desk')->assertOk();
        $this->actingAs($this->frontDesk)->get('/hotel/front-desk')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/hotel/front-desk')->assertForbidden();

        // Inventory & Rates grid
        $this->actingAs($this->revenueMgr)->get('/hotel/inventory')->assertOk();
        $this->actingAs($this->revenueMgr)->get('/hotel/rates')->assertOk();
        $this->actingAs($this->frontDesk)->get('/hotel/inventory')->assertForbidden();

        // Housekeeping Mobile (375px)
        $this->actingAs($this->housekeepingStaff)->get('/hotel/housekeeping')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/hotel/housekeeping')->assertForbidden();

        // Engineering Maintenance
        $this->actingAs($this->engineeringStaff)->get('/hotel/engineering')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/hotel/engineering')->assertForbidden();

        // Compliance APOA
        $this->actingAs($this->hotelGm)->get('/hotel/compliance/apoa')->assertOk();
        $this->actingAs($this->frontOffice)->get('/hotel/compliance/apoa')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/hotel/compliance/apoa')->assertForbidden();

        // Energy Twin Dashboard
        $this->actingAs($this->hotelGm)->get('/hotel/energy')->assertOk();
        $this->actingAs($this->housekeepingStaff)->get('/hotel/energy')->assertForbidden();

        // Guest Stay Portal
        $this->actingAs($this->guestUser)->get('/guest/stay')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/guest/stay')->assertForbidden();
    }

    /**
     * Test 2: Alur End-to-End Reservasi CRS, Kunci Kamar Pessimistic Lock, Check-In Kunci Digital, Night Audit & Idempotensi.
     */
    public function test_02_crs_reservation_night_lock_digital_key_and_night_audit_posting(): void
    {
        $crsAction = app(ReserveRoomCentralizedCrsAction::class);
        $checkInAction = app(CheckInWithDigitalKeyAndApoaAction::class);
        $nightAuditAction = app(RunNightAuditFase91Action::class);

        $idempotencyKey = 'IDEMP-RES-TEST-001';
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        // 1. Reservasi via CRS
        $resResult = $crsAction->execute(
            propertyId: (string) $this->property->id,
            roomTypeId: (string) $this->roomType->id,
            guestUserId: (string) $this->guestUser->id,
            checkInDate: $today,
            checkOutDate: $tomorrow,
            channel: 'web',
            idempotencyKey: $idempotencyKey
        );

        $reservation = $resResult['reservation'];
        $this->assertSame(ReservationStateMachine::STATUS_CONFIRMED, $reservation->status);
        $this->assertFalse($resResult['is_overbooked']);

        // Idempotensi: Panggilan ulang dengan key yang sama tidak membuat reservasi baru
        $duplicate = $crsAction->execute(
            propertyId: (string) $this->property->id,
            roomTypeId: (string) $this->roomType->id,
            guestUserId: (string) $this->guestUser->id,
            checkInDate: $today,
            checkOutDate: $tomorrow,
            channel: 'web',
            idempotencyKey: $idempotencyKey
        );
        $this->assertSame($reservation->id, $duplicate['reservation']->id);

        // 2. Check-In & Penerbitan Kunci Digital
        $checkInRes = $checkInAction->execute(
            reservationId: (string) $reservation->id,
            roomId: (string) $this->room->id,
            guestNationality: 'US',
            passportNumber: 'PASS-US-98765432',
            isBaliPwaPaid: true
        );

        $this->assertNotNull($checkInRes['digital_key']);
        $this->assertSame('active', $checkInRes['digital_key']->status);
        $this->assertNotNull($checkInRes['apoa_report']);
        $this->assertSame('pending', $checkInRes['apoa_report']->status);

        // 3. Night Audit Posting
        $auditRes = $nightAuditAction->execute(
            propertyId: (string) $this->property->id,
            overrideBusinessDate: $today,
            idempotencyKey: "NA-KEY-{$today}"
        );

        $this->assertSame(1, $auditRes['processed_rooms']);
        $this->assertGreaterThan(0, $auditRes['total_revenue_idr']);

        // Idempotensi Night Audit: dijalankan dua kali pada hari yang sama tidak menduplikasi posting
        $auditRes2 = $nightAuditAction->execute(
            propertyId: (string) $this->property->id,
            overrideBusinessDate: $today,
            idempotencyKey: "NA-KEY-{$today}"
        );
        $this->assertSame(1, $auditRes2['processed_rooms']);
    }

    /**
     * Test 3: Aturan Bisnis Kunci: Oversell 3%, Contract Rate Immunity H2, HVAC Setback H4, Service Recovery H5.
     */
    public function test_03_business_rules_oversell_bar_rate_setback_and_service_recovery(): void
    {
        // 1. Contract Rate Immunity H2
        $barRes = BestAvailableRateCalculator::calculate(
            baseRateIdr: 1_200_000,
            occupancyRate: 0.95,
            leadDays: 1,
            isEventActive: true,
            contractRateIdr: 950_000
        );
        $this->assertSame(950_000, $barRes['rate_idr']);
        $this->assertTrue($barRes['is_contract_override']);

        // 2. Overbooking Limit Optimizer H1
        $opt = OverbookingLimitOptimizer::optimize(
            capacity: 100,
            noShowDistributionBps: [500, 1000, 2000, 2500, 2000, 1500, 500],
            revenuePerRoomIdr: 1_000_000,
            walkCostIdr: 2_500_000,
            policyMaxPercentBps: 300
        );
        $this->assertSame(2, $opt['optimal_k']);
        $this->assertSame(98_200_000, $opt['expected_net_value_idr']);

        // 3. HVAC Setback H4
        $hvac = HvacSetbackController::evaluate(
            isOccupiedRoom: true,
            minutesSinceLastMotion: 180,
            durationMinutes: 180,
            acPowerWatt: 1200,
            savingFactorBps: 3000
        );
        $this->assertTrue($hvac['setback_active']);
        $this->assertSame(990, $hvac['energy_saved_wh']);

        // 4. Service Recovery H5 Kompensasi ke Folio
        $reservation = HotelReservation::create([
            'id' => (string) Str::uuid(),
            'reservation_number' => 'RSV-COMP-01',
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'guest_user_id' => (string) $this->guestUser->id,
            'check_in_date' => date('Y-m-d'),
            'check_out_date' => date('Y-m-d', strtotime('+1 day')),
            'daily_rate_idr' => 1_500_000,
            'total_room_charge_idr' => 1_500_000,
            'status' => 'checked_in',
        ]);

        $recoveryAction = app(ResolveServiceRecoveryAction::class);
        $recRes = $recoveryAction->execute(
            reservationId: (string) $reservation->id,
            excessHours: 10.0, // lebih dari 6 jam -> 50% + Free Night
            issueDescription: 'AC rusak semalaman',
            idempotencyKey: 'REC-IDEMP-01'
        );

        $this->assertSame(750_000, $recRes['compensation_amount_idr']);
        $this->assertTrue($recRes['grants_free_night']);
    }

    /**
     * Test 4: Proteksi Anti-IDOR pada Portal Tamu dan Kunci Digital Kamar.
     */
    public function test_04_anti_idor_guest_stay_portal_and_digital_key(): void
    {
        $today = date('Y-m-d');
        $reservation = HotelReservation::create([
            'id' => (string) Str::uuid(),
            'reservation_number' => 'RSV-GUEST-A',
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'guest_user_id' => (string) $this->guestUser->id,
            'check_in_date' => $today,
            'check_out_date' => date('Y-m-d', strtotime('+2 days')),
            'daily_rate_idr' => 1_200_000,
            'total_room_charge_idr' => 2_400_000,
            'status' => 'confirmed',
        ]);

        // Tamu sah dapat melihat reservasi miliknya di portal
        $this->actingAs($this->guestUser)
            ->get('/guest/stay')
            ->assertOk()
            ->assertSee('RSV-GUEST-A');

        // Tamu lain tidak dapat melihat nomor reservasi milik tamu pertama (Anti-IDOR)
        $this->actingAs($this->otherGuestUser)
            ->get('/guest/stay')
            ->assertOk()
            ->assertDontSee('RSV-GUEST-A');
    }

    /**
     * Test 5: Audit Dua Sumber hotel:audit (Data Bersih vs Fixture Korupsi).
     */
    public function test_05_two_source_audit_clean_data_and_corruption_fixture(): void
    {
        // 1. Kondisi bersih -> exit 0
        $exitClean = Artisan::call('hotel:audit');
        $this->assertSame(0, $exitClean);

        // 2. Terapkan sabotase data korupsi lewat HtlAuditCorruptionFixture
        $fixture = new HtlAuditCorruptionFixture;
        $fixture->corrupt();

        // Audit harus mendeteksi korupsi data kamar tanpa reservasi sah -> exit non-zero
        $exitCorrupt = Artisan::call('hotel:audit');
        $this->assertNotSame(0, $exitCorrupt);
    }
}
