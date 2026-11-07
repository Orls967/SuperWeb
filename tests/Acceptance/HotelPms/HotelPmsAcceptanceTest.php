<?php

declare(strict_types=1);

namespace Tests\Acceptance\HotelPms;

use App\Models\User;
use App\Quality\Audit\Fixtures\HotelAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Services\RbacService;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomType;
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

        $this->guestUser = User::factory()->create(['name' => 'Valued Guest']);
        $rbac->assignRole($this->guestUser, 'guest');

        $this->unauthorizedUser = User::factory()->create(['name' => 'Plain User']);
        $rbac->assignRole($this->unauthorizedUser, 'unauthorized');
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
        $this->markTestIncomplete('Akan hijau setelah implementasi L.3.');
    }

    /**
     * Test 3: Aturan Bisnis Kunci: Oversell 3%, Contract Rate Immunity H2, HVAC Setback H4, Service Recovery H5.
     */
    public function test_03_business_rules_oversell_bar_rate_setback_and_service_recovery(): void
    {
        $this->markTestIncomplete('Akan hijau setelah implementasi L.3.');
    }

    /**
     * Test 4: Proteksi Anti-IDOR pada Portal Tamu dan Kunci Digital Kamar.
     */
    public function test_04_anti_idor_guest_stay_portal_and_digital_key(): void
    {
        $this->markTestIncomplete('Akan hijau setelah implementasi L.3.');
    }

    /**
     * Test 5: Audit Dua Sumber hotel:audit (Data Bersih vs Fixture Korupsi).
     */
    public function test_05_two_source_audit_clean_data_and_corruption_fixture(): void
    {
        $this->markTestIncomplete('Akan hijau setelah implementasi L.3.');
    }
}
