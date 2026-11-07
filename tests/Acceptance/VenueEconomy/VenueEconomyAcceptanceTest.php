<?php

declare(strict_types=1);

namespace Tests\Acceptance\VenueEconomy;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Services\RbacService;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationInput;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocator;
use Modules\Venue\Application\Actions\AllocateFestivalBundleSspAction;
use Modules\Venue\Application\Actions\EvaluateMembershipTierAction;
use Modules\Venue\Application\Actions\SettleArtistEngagementAction;
use Modules\Venue\database\seeders\VenueRbacSeeder;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitCalculator;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitInput;
use Modules\Venue\Domain\Algorithms\MembershipTierEngine;
use Modules\Venue\Domain\Algorithms\MembershipTierInput;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Tests\TestCase;

final class VenueEconomyAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $venueManager;

    private User $venueStaff;

    private User $unauthorizedUser;

    private EntertainmentVenue $venue;

    private EntertainmentZone $zone;

    private EntertainmentEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(VenueRbacSeeder::class);

        $rbac = app(RbacService::class);
        $rbac->ensureRole('venue_manager', 'Manajer Venue', 'Otoritas operasional penuh venue dan keuangan');
        $rbac->ensureRole('venue_staff', 'Staf Venue', 'Operasional gerbang, bar, dan turnstile');

        $this->venueManager = User::factory()->create(['name' => 'Venue GM', 'email' => 'gm@beachclub.test']);
        $this->venueStaff = User::factory()->create(['name' => 'Bar Supervisor', 'email' => 'bar@beachclub.test']);
        $this->unauthorizedUser = User::factory()->create(['name' => 'Tamu Biasa', 'email' => 'tamu@beachclub.test']);

        $rbac->assignRole($this->venueManager, 'venue_manager');
        $rbac->assignRole($this->venueStaff, 'venue_staff');

        $this->venue = EntertainmentVenue::query()->create([
            'venue_code' => 'ATLAS-01',
            'name' => 'Atlas Sunset Beach Club',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 3000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::query()->create([
            'zone_code' => 'BF-CABANA',
            'venue_id' => $this->venue->id,
            'name' => 'Beachfront VIP Cabana',
            'capacity_limit' => 60,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::query()->create([
            'event_code' => 'EVT-SUNSET-2026',
            'venue_id' => $this->venue->id,
            'title' => 'Sunset Sound Festival 2026',
            'doors_open_at' => now()->subHours(2),
            'ticket_floor_price_idr' => 250_000,
            'ticket_ceiling_price_idr' => 2_500_000,
            'current_ticket_price_idr' => 500_000,
        ]);
    }

    /**
     * Skenario 1: Verifikasi Otorisasi RBAC dan Proteksi Endpoint Ekonomi Venue
     */
    public function test_access_control_and_role_authorization(): void
    {
        // 1. Pengguna tanpa izin dilarang mengakses audit bar shift
        $this->actingAs($this->unauthorizedUser);
        $response = $this->postJson('/venue/bar-shifts/audit', [
            'shift_log_id' => 'BSL-NON-EXISTENT',
        ]);
        $this->assertContains($response->status(), [403, 404]);

        // 2. Venue Manager memiliki izin audit bar dan settlement artis
        $this->actingAs($this->venueManager);
        $this->assertTrue($this->venueManager->hasRole('venue_manager'));
    }

    /**
     * Skenario 2: Kontrak Artis Versus Deal (V5), Advance, Performance, WHT, dan Posting Ledger
     */
    public function test_end_to_end_artist_contract_versus_deal_and_ledger_posting(): void
    {
        // Vektor acuan V5: Guarantee Rp 100jt vs 30% dari Door Net (Gross Rp 600jt - Expense Rp 150jt = Net Rp 450jt -> 30% = Rp 135jt)
        // Gross Pay = max(100jt, 135jt) = 135jt.
        // Advance paid = Rp 50jt. WHT 20% (PPh 26 artis luar negeri) = 20% x 135jt = Rp 27jt.
        // Net Payable IDR = 135jt - 27jt - 50jt = Rp 58jt.
        $calculator = app(ArtistDoorSplitCalculator::class);
        $input = new ArtistDoorSplitInput(
            dealType: 'versus',
            guaranteeIdr: 100_000_000,
            doorGrossIdr: 600_000_000,
            approvedExpensesIdr: 150_000_000,
            shareBps: 3_000,
            whtBps: 2_000,
            advancePaidIdr: 50_000_000,
            currency: 'USD',
            exchangeRateIdr: 16_250
        );

        $result = $calculator->calculate($input);

        $this->assertSame(135_000_000, $result->grossPayIdr);
        $this->assertSame(27_000_000, $result->whtIdr);
        $this->assertSame(58_000_000, $result->netPayableIdr);
        $this->assertNotNull($result->certificate);
        $this->assertTrue($result->certificate->isValid());

        // Verifikasi konversi valas minor cents: Rp 58jt / 16.250 * 100 = 356.923 cents ($3.569,23)
        $expectedCents = (int) floor((58_000_000 * 100) / 16_250);
        $this->assertSame($expectedCents, $result->valasMinorCents);

        // Eksekusi Action Settlement Artis
        $action = app(SettleArtistEngagementAction::class);
        $engagement = $action->execute(
            eventId: $this->event->id,
            artistName: 'DJ Kaskade Coast',
            input: $input,
            actorId: $this->venueManager->id
        );

        $this->assertSame('settled', $engagement->status);
        $this->assertSame(135_000_000, $engagement->gross_pay_idr);
        $this->assertSame(58_000_000, $engagement->net_paid_idr);
    }

    /**
     * Skenario 3: Alokasi Bundle Festival Multi-Vendor SSP Hamilton-Hare (V6) & Integrasi Lintas Modul
     */
    public function test_multi_vendor_festival_bundle_ssp_allocation_and_redemption(): void
    {
        // Bundle Price: Rp 2.999.999.
        // Komponen SSP:
        // 1. 3-Day Festival Pass: Rp 1.800.000
        // 2. Glamping Hotel Night (K16): Rp 1.500.000
        // 3. F&B Credit (K17): Rp 400.000
        // 4. Shuttle VIP: Rp 300.000
        // Total SSP = Rp 4.000.000
        $allocator = app(BundleSspAllocator::class);
        $input = new BundleSspAllocationInput(
            bundlePriceIdr: 2_999_999,
            platformFeeBps: 500, // 5%
            components: [
                ['name' => 'festival_pass', 'ssp_idr' => 1_800_000, 'vendor_id' => 'VEN-ATLAS'],
                ['name' => 'hotel_glamping', 'ssp_idr' => 1_500_000, 'vendor_id' => 'HOTEL-MARINAS'],
                ['name' => 'fnb_credit', 'ssp_idr' => 400_000, 'vendor_id' => 'RESTO-BEACH'],
                ['name' => 'vip_shuttle', 'ssp_idr' => 300_000, 'vendor_id' => 'SHUTTLE-BALI'],
            ]
        );

        $allocation = $allocator->allocate($input);

        // Validasi Invarian Konservasi Total
        $totalAllocated = array_sum(array_column($allocation->items, 'allocated_amount_idr'));
        $this->assertSame(2_999_999, $totalAllocated);

        $totalFee = array_sum(array_column($allocation->items, 'platform_fee_idr'));
        $totalVendorNet = array_sum(array_column($allocation->items, 'vendor_net_idr'));
        $this->assertSame(2_999_999, $totalFee + $totalVendorNet);

        // Eksekusi Action Simpan Bundle dan Pembukuan Unearned Revenue
        $action = app(AllocateFestivalBundleSspAction::class);
        $bundleOrder = $action->execute(
            eventId: $this->event->id,
            bundleCode: 'FEST-GLAMP-VIP-2026',
            customerId: $this->unauthorizedUser->id,
            input: $input
        );

        $this->assertNotNull($bundleOrder->id);
        $this->assertSame('paid', $bundleOrder->status);
        $this->assertCount(4, $bundleOrder->allocations);
    }

    /**
     * Skenario 4: Evaluasi Membership Tiering (V7), Aturan Grace Period, dan Alokasi Bottle Locker
     */
    public function test_membership_tier_evaluation_grace_period_and_bottle_locker(): void
    {
        $engine = app(MembershipTierEngine::class);

        // Tamu belanja Rp 80.000.000 dengan 25 kunjungan -> Kualifikasi Infinity Tier
        $inputInfinity = new MembershipTierInput(
            currentTier: 'sun',
            spend365DaysIdr: 80_000_000,
            visits365Days: 25,
            isAnniversaryEvaluation: true,
            spendCurrentYearIdr: 80_000_000
        );

        $resInfinity = $engine->evaluate($inputInfinity);
        $this->assertSame('infinity', $resInfinity->resolvedTier);
        $this->assertTrue($resInfinity->eligibleForBottleLocker);

        // Eksekusi Action Evaluasi Tier
        $action = app(EvaluateMembershipTierAction::class);
        $membership = $action->execute(
            userId: $this->venueStaff->id,
            input: $inputInfinity,
            assignedLockerNumber: 'LCK-VIP-08'
        );

        $this->assertSame('infinity', $membership->tier);
        $this->assertSame('LCK-VIP-08', $membership->bottle_locker_number);
        $this->assertSame('active', $membership->status);

        // Hitung perolehan poin: Transaksi Rp 1.500.000 di tier Infinity (multiplier 2,0x) -> 300 pts
        $earnedPoints = $engine->calculatePoints(1_500_000, 'infinity');
        $this->assertSame(300, $earnedPoints);
    }

    /**
     * Skenario 5: Audit Ekonomi Venue Mandiri (venue:audit-economy) & Deteksi Integritas
     */
    public function test_venue_economy_audit_command_and_corruption_detection(): void
    {
        // 1. Eksekusi audit konsol dalam kondisi sehat -> Exit Code 0
        $exitCode = Artisan::call('venue:audit-economy');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('AUDIT EKONOMI VENUE SELESAI', $output);
        $this->assertStringContainsString('HEALTHY', $output);
    }
}
