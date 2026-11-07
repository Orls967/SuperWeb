<?php

declare(strict_types=1);

namespace Tests\Acceptance\VenueOps;

use App\Models\User;
use App\Quality\Audit\Fixtures\VenAuditCorruptionFixture;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Application\Services\RbacService;
use Modules\Venue\Application\Actions\CloseEntertainmentEventAction;
use Modules\Venue\Application\Actions\IssueTicketWithGaplessHashAction;
use Modules\Venue\Application\Actions\ScanTicketGateWithAntiPassbackAndAgeCheckAction;
use Modules\Venue\Application\Services\VenueOperationsService;
use Modules\Venue\database\seeders\VenueRbacSeeder;
use Modules\Venue\Domain\Algorithms\MinimumSpendSettlement;
use Modules\Venue\Domain\Algorithms\TierCountdownPricer;
use Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Domain\Models\VenuePermit;
use Modules\Venue\Ledger\VenueAccounts;
use Tests\TestCase;

final class VenueOpsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $venueManager;
    private User $venueStaff;
    private User $crowdSafety;
    private User $complianceOfficer;
    private User $customerA;
    private User $customerB;
    private User $unauthorizedUser;

    private EntertainmentVenue $venue;
    private EntertainmentZone $zone;
    private EntertainmentEvent $event;
    private TicketTier $tierGA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountsSeeder::class);
        $this->seed(VenueRbacSeeder::class);

        $rbac = app(RbacService::class);
        $rbac->ensureRole('crowd_safety', 'Petugas Crowd Safety', 'Pengawasan kepadatan dan keselamatan');
        $rbac->ensureRole('compliance_officer', 'Petugas Kepatuhan', 'Pengawasan izin dan regulasi');
        $rbac->ensureRole('customer', 'Pelanggan', 'Tamu tempat hiburan');

        $this->venueManager = User::factory()->create(['name' => 'Manager Atlas']);
        $this->venueManager->assignRole('venue_manager');

        $this->venueStaff = User::factory()->create(['name' => 'Staff Gate']);
        $this->venueStaff->assignRole('venue_staff');

        $this->crowdSafety = User::factory()->create(['name' => 'Safety Officer']);
        $this->crowdSafety->assignRole('crowd_safety');

        $this->complianceOfficer = User::factory()->create(['name' => 'Compliance Legal']);
        $this->complianceOfficer->assignRole('compliance_officer');

        $this->customerA = User::factory()->create(['name' => 'Customer A']);
        $this->customerA->assignRole('customer');

        $this->customerB = User::factory()->create(['name' => 'Customer B']);
        $this->customerB->assignRole('customer');

        $this->unauthorizedUser = User::factory()->create(['name' => 'Unauthorized User']);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-ATLAS-CANGGU',
            'name' => 'Atlas Beach Club Canggu',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 2000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZN-DANCE-FLOOR',
            'venue_id' => $this->venue->id,
            'name' => 'Main Dance Floor',
            'capacity_limit' => 500,
            'current_occupancy' => 0,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-SUNSET-FEST-89',
            'venue_id' => $this->venue->id,
            'title' => 'Bali Sunset Electronic Fest 2026',
            'doors_open_at' => now()->addDays(2),
            'ticket_floor_price_idr' => 280_000,
            'ticket_ceiling_price_idr' => 500_000,
            'current_ticket_price_idr' => 350_000,
            'status' => 'on_sale',
        ]);

        $this->tierGA = TicketTier::create([
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'name' => 'General Admission',
            'tier_code' => 'TIER-GA',
            'tier_type' => 'GENERAL_ADMISSION',
            'price_idr' => 350_000,
            'quota' => 500,
        ]);
    }

    /**
     * Test 1: Akses RBAC Terpadu untuk seluruh rute Fase 89
     */
    public function test_access_control_for_all_venue_ops_routes_with_authorized_and_unauthorized_roles(): void
    {
        // 1. /venue/events (berhak: venue_manager -> 200; tak berhak -> 403)
        $this->actingAs($this->venueManager)->get('/venue/events')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/venue/events')->assertForbidden();

        // 2. /venue/gate (berhak: venue_staff -> 200; tak berhak -> 403)
        $this->actingAs($this->venueStaff)->get('/venue/gate')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/venue/gate')->assertForbidden();

        // 3. /venue/zones (berhak: crowd_safety -> 200; tak berhak -> 403)
        $this->actingAs($this->crowdSafety)->get('/venue/zones')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/venue/zones')->assertForbidden();

        // 4. /venue/tables (berhak: venue_manager -> 200; tak berhak -> 403)
        $this->actingAs($this->venueManager)->get('/venue/tables')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/venue/tables')->assertForbidden();

        // 5. /venue/permits (berhak: compliance_officer -> 200; tak berhak -> 403)
        $this->actingAs($this->complianceOfficer)->get('/venue/permits')->assertOk();
        $this->actingAs($this->unauthorizedUser)->get('/venue/permits')->assertForbidden();

        // 6. /guest/tickets (berhak: customer -> 200)
        $this->actingAs($this->customerA)->get('/guest/tickets')->assertOk();
    }

    /**
     * Test 2: Alur utama end-to-end + posting ledger akrual + idempotensi
     */
    public function test_end_to_end_ticket_lifecycle_tier_pricing_gapless_hash_scan_and_unearned_revenue_closing(): void
    {
        $issueAction = app(IssueTicketWithGaplessHashAction::class);
        $scanAction = app(ScanTicketGateWithAntiPassbackAndAgeCheckAction::class);
        $closeAction = app(CloseEntertainmentEventAction::class);

        // 1. Pembelian tiket dengan harga dinamis V1
        $idempotencyKey = 'ven:ticket_purchase:EVT-SUNSET-FEST-89:CUST-A-01';
        $ticket = $issueAction->execute(
            event: $this->event,
            tier: $this->tierGA,
            buyerUserId: $this->customerA->id,
            idempotencyKey: $idempotencyKey,
            demandBps: 8500, // +20% -> 420.000
            rainProbabilityBps: 2000
        );

        $this->assertEquals(420_000, $ticket->price_paid_idr);
        $this->assertEquals('issued', $ticket->status);
        $this->assertNotEmpty($ticket->ticket_number);
        $this->assertNotEmpty($ticket->ticket_hash);

        // Jurnal saat pembelian: Debit Clearing, Kredit Unearned + Tax Payable
        $unearnedAcc = LedgerAccount::where('code', 'ven:ticket_unearned:IDR')->firstOrFail();
        $this->assertEquals(420_000, (int) $unearnedAcc->cached_balance);

        // Idempotensi: eksekusi ulang dengan key sama mengembalikan tiket yang sama tanpa jurnal ganda
        $ticketDupe = $issueAction->execute(
            event: $this->event,
            tier: $this->tierGA,
            buyerUserId: $this->customerA->id,
            idempotencyKey: $idempotencyKey,
            demandBps: 8500,
            rainProbabilityBps: 2000
        );
        $this->assertEquals($ticket->id, $ticketDupe->id);
        $this->assertEquals(420_000, (int) $unearnedAcc->refresh()->cached_balance);

        // 2. Pemindaian gerbang arah masuk (IN)
        $scanResult = $scanAction->execute(
            ticket: $ticket,
            zone: $this->zone,
            direction: 'IN',
            nonce: 'nonce-e2e-001',
            patronAge: 23,
            scannedByUserId: $this->venueStaff->id
        );
        $this->assertEquals('ALLOWED', $scanResult['status']);
        $this->assertEquals('scanned_in', $ticket->refresh()->status);
        $this->assertEquals(1, $this->zone->refresh()->current_occupancy);

        // 3. Penutupan acara (Event Closed) -> Mengakui unearned revenue ke revenue final
        $closeAction->execute($this->event);
        $this->assertEquals('closed', $this->event->refresh()->status);

        $revAcc = LedgerAccount::where('code', 'ven:ticket_revenue:IDR')->firstOrFail();
        $this->assertEquals(420_000, (int) $revAcc->cached_balance);
        $this->assertEquals(0, (int) $unearnedAcc->refresh()->cached_balance);
    }

    /**
     * Test 3: Jalur galat & aturan bisnis kunci (Passback, Underage, Zone Governor, No-Show Forfeiture)
     */
    public function test_business_rules_anti_passback_underage_rejection_zone_capacity_governor_and_noshow_forfeiture(): void
    {
        $issueAction = app(IssueTicketWithGaplessHashAction::class);
        $scanAction = app(ScanTicketGateWithAntiPassbackAndAgeCheckAction::class);

        // Buat tiket uji coba
        $ticket = $issueAction->execute(
            event: $this->event,
            tier: $this->tierGA,
            buyerUserId: $this->customerA->id,
            idempotencyKey: 'ven:tck:rule_test_01',
            demandBps: 2000,
            rainProbabilityBps: 1000
        );

        // a) Penolakan usia di bawah 21 tahun
        $underageResult = $scanAction->execute(
            ticket: $ticket,
            zone: $this->zone,
            direction: 'IN',
            nonce: 'nonce-underage-01',
            patronAge: 19,
            scannedByUserId: $this->venueStaff->id
        );
        $this->assertEquals('DENIED', $underageResult['status']);
        $this->assertStringContainsString('bawah batas minimal', $underageResult['reason']);

        // b) Pindaian masuk sah usia 22 tahun
        $validScan = $scanAction->execute(
            ticket: $ticket,
            zone: $this->zone,
            direction: 'IN',
            nonce: 'nonce-valid-01',
            patronAge: 22,
            scannedByUserId: $this->venueStaff->id
        );
        $this->assertEquals('ALLOWED', $validScan['status']);

        // c) Pelanggaran Anti-Passback (Pindaian IN kedua kali tanpa OUT)
        $passbackResult = $scanAction->execute(
            ticket: $ticket,
            zone: $this->zone,
            direction: 'IN',
            nonce: 'nonce-passback-01',
            patronAge: 25,
            scannedByUserId: $this->venueStaff->id
        );
        $this->assertEquals('DENIED', $passbackResult['status']);
        $this->assertStringContainsString('anti-passback', strtolower($passbackResult['reason']));

        // d) Penolakan Replay Attack (Nonce identik)
        $ticket->update(['last_direction' => 'OUT', 'status' => 'scanned_out']);
        $replayResult = $scanAction->execute(
            ticket: $ticket,
            zone: $this->zone,
            direction: 'IN',
            nonce: 'nonce-valid-01', // Nonce lama dipakai lagi
            patronAge: 22,
            scannedByUserId: $this->venueStaff->id
        );
        $this->assertEquals('DENIED', $replayResult['status']);
        $this->assertStringContainsString('replay', strtolower($replayResult['reason']));

        // e) Zone Capacity Governor V3: tolak saat zona penuh kapasitas efektif (475 dari 500)
        $this->zone->update(['current_occupancy' => 475]);
        $overcapResult = $scanAction->execute(
            ticket: $ticket,
            zone: $this->zone,
            direction: 'IN',
            nonce: 'nonce-cap-01',
            patronAge: 25,
            scannedByUserId: $this->venueStaff->id
        );
        $this->assertEquals('DENIED', $overcapResult['status']);
        $this->assertStringContainsString('kapasitas', strtolower($overcapResult['reason']));

        // f) Minimum Spend Settlement V4: Skenario No-Show Forfeiture 50%
        $settlement = MinimumSpendSettlement::calculate(
            minimumSpendIdr: 5_000_000,
            depositHeldIdr: 2_000_000,
            actualSpendIdr: 0,
            isNoShow: true,
            noShowForfeitureBps: 5000
        );
        $this->assertEquals(1_000_000, $settlement->forfeitedDepositIdr);
        $this->assertEquals(1_000_000, $settlement->refundedDepositIdr);
    }

    /**
     * Test 4: Anti-IDOR pada portal tiket tamu (/guest/tickets)
     */
    public function test_guest_portal_ticket_isolation_anti_idor(): void
    {
        $issueAction = app(IssueTicketWithGaplessHashAction::class);

        // Customer A membeli tiket
        $ticketA = $issueAction->execute(
            event: $this->event,
            tier: $this->tierGA,
            buyerUserId: $this->customerA->id,
            idempotencyKey: 'ven:tck:cust_a_idor',
            demandBps: 2000,
            rainProbabilityBps: 1000
        );

        // Customer B tidak boleh melihat detail atau kode QR tiket milik Customer A
        $this->actingAs($this->customerB)
            ->get("/guest/tickets/{$ticketA->ticket_uuid}")
            ->assertForbidden();

        // Customer A dapat melihat tiket miliknya sendiri
        $this->actingAs($this->customerA)
            ->get("/guest/tickets/{$ticketA->ticket_uuid}")
            ->assertOk();
    }

    /**
     * Test 5: Audit dua sumber ven:audit (data bersih exit 0 + fixture exit != 0)
     */
    public function test_two_source_audit_command_and_corruption_fixture(): void
    {
        // 1. Data bersih -> ven:audit exit 0
        $cleanExit = Artisan::call('ven:audit');
        $this->assertEquals(0, $cleanExit, 'Audit data bersih harus mengembalikan exit code 0');

        // 2. Terapkan fixture anomali korupsi
        $fixture = new VenAuditCorruptionFixture();
        $fixture->corrupt();

        // 3. ven:audit wajib mendeteksi diskrepansi dan keluar dengan exit code != 0
        $corruptExit = Artisan::call('ven:audit');
        $output = Artisan::output();

        $this->assertNotEquals(0, $corruptExit, 'Audit setelah korupsi harus mengembalikan exit code non-zero');
        $this->assertStringContainsString('discrepancies detected', $output);
    }
}

