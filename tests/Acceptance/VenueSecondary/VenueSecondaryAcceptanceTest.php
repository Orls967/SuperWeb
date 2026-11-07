<?php

declare(strict_types=1);

namespace Tests\Acceptance\VenueSecondary;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Application\Services\RbacService;
use Modules\Venue\Application\Actions\AllocateDynamicBundleAction;
use Modules\Venue\Application\Actions\ClaimWaitlistSeatOfferAction;
use Modules\Venue\Application\Actions\ExecuteSecondaryTicketResaleAction;
use Modules\Venue\Application\Actions\ListTicketForResaleAction;
use Modules\Venue\Application\Actions\OfferSeatToNextWaitlistAction;
use Modules\Venue\database\seeders\VenueRbacSeeder;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingCapEngine;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundlePricer;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallEngine;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallInput;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistGovernorInput;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistPriorityGovernor;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\TicketWaitlist;
use RuntimeException;
use Tests\TestCase;

class VenueSecondaryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $staffUser;

    protected User $customerUser;

    protected EntertainmentVenue $venue;

    protected EntertainmentZone $zone;

    protected EntertainmentEvent $event;

    protected EntertainmentTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VenueRbacSeeder::class);

        $rbac = app(RbacService::class);
        $rbac->ensureRole('admin', 'Admin', 'Admin Sistem');
        $rbac->ensureRole('venue_manager', 'Manajer Venue', 'Otoritas manajerial');
        $rbac->ensureRole('venue_staff', 'Staf Venue', 'Operasional gerbang & bursa');
        $rbac->ensureRole('customer', 'Pelanggan', 'Tamu umum');

        $this->adminUser = User::factory()->create(['name' => 'Admin User']);
        $rbac->assignRole($this->adminUser, 'admin');

        $this->staffUser = User::factory()->create(['name' => 'Venue Staff']);
        $rbac->assignRole($this->staffUser, 'venue_staff');

        $this->customerUser = User::factory()->create(['name' => 'General Customer']);
        $rbac->assignRole($this->customerUser, 'customer');

        $this->venue = EntertainmentVenue::query()->create([
            'venue_code' => 'VEN-BALI-VIP',
            'name' => 'Finns VIP Beach Club',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Canggu, Bali',
            'min_age_requirement' => 21,
            'max_legal_capacity' => 2000,
        ]);

        $this->zone = EntertainmentZone::query()->create([
            'venue_id' => $this->venue->id,
            'zone_code' => 'ZN-VIP-SUNSET',
            'name' => 'VIP Sunset Deck',
            'capacity_limit' => 300,
            'current_occupancy' => 300, // Sold out
        ]);

        $this->event = EntertainmentEvent::query()->create([
            'venue_id' => $this->venue->id,
            'event_code' => 'EVT-SUNSET-FEST-2026',
            'title' => 'Bali International Sunset Sound 2026',
            'doors_open_at' => Carbon::now()->addDays(14),
            'ticket_floor_price_idr' => 500000,
            'ticket_ceiling_price_idr' => 2500000,
            'current_ticket_price_idr' => 1000000,
            'status' => 'ON_SALE',
        ]);

        $this->ticket = EntertainmentTicket::query()->create([
            'event_id' => $this->event->id,
            'ticket_number' => 'TCK-ORIG-88001',
            'buyer_user_id' => $this->customerUser->id,
            'price_paid_idr' => 1000000,
            'ticket_hash' => hash('sha256', 'TCK-ORIG-88001-SEED'),
            'status' => 'VALID',
        ]);
    }

    /**
     * Skenario 1: Kontrol Akses & Otorisasi RBAC Pasar Sekunder, Bundling & Waitlist
     */
    public function test_access_control_and_role_authorization_for_secondary_market(): void
    {
        // 1. Tamu belum login diarahkan ke login
        $this->get(route('venue.secondary.marketplace'))
            ->assertRedirect(route('login'));

        // 2. Customer dapat melihat marketplace publik sekunder dan form jual tiketnya
        $this->actingAs($this->customerUser)
            ->get(route('venue.secondary.marketplace'))
            ->assertOk()
            ->assertSee('Marketplace Tiket Resmi');

        // 3. Venue Manager/Staff dapat melihat ringkasan analitik sekunder
        $this->actingAs($this->staffUser)
            ->get(route('venue.secondary.reports'))
            ->assertOk()
            ->assertSee('Analitik Pasar Sekunder');
    }

    /**
     * Skenario 2: Anti-Scalping Price Cap 120%, Single Transfer Limit & Deteksi Bot
     */
    public function test_anti_scalping_enforcement_and_single_transfer_limit(): void
    {
        $capEngine = app(AntiScalpingCapEngine::class);

        // a. Uji batas harga jual: 120% dari Rp 1.000.000 = Rp 1.200.000 (Lolos)
        $validInput = new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1200000,
            transferCount: 0,
            userActiveTicketCount: 1,
            velocitySeconds: 2.5
        );
        $result = $capEngine->evaluate($validInput);
        $this->assertTrue($result->isAllowed);
        $this->assertEquals(1200000, $result->effectivePriceIdr);

        // b. Uji penolakan harga spekulatif di atas 120% (Rp 1.300.000 -> DITOLAK)
        $scalperInput = new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1300000,
            transferCount: 0,
            userActiveTicketCount: 1,
            velocitySeconds: 2.5
        );
        $scalperResult = $capEngine->evaluate($scalperInput);
        $this->assertFalse($scalperResult->isAllowed);
        $this->assertStringContainsString('melebihi batas anti-scalping', $scalperResult->rejectionReason);

        // c. Uji Single Transfer Limit: Eksekusi transfer ke-1 sukses, transfer ke-2 DITOLAK
        $listAction = app(ListTicketForResaleAction::class);
        $resaleListing = $listAction->execute(
            ticket: $this->ticket,
            sellerUserId: $this->customerUser->id,
            askingPriceIdr: 1150000
        );

        $buyer = User::factory()->create(['name' => 'Verified Buyer']);
        app(RbacService::class)->assignRole($buyer, 'customer');

        $executeResaleAction = app(ExecuteSecondaryTicketResaleAction::class);
        $executedResale = $executeResaleAction->execute(
            resaleId: $resaleListing->id,
            buyerUserId: $buyer->id
        );

        $this->assertEquals('SETTLED', $executedResale->status);
        $this->assertEquals(1, $executedResale->transfer_count);

        // Percobaan transfer kedua pada tiket yang sama wajib melempar Exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('maksimum 1 kali');

        $listAction->execute(
            ticket: $this->ticket->fresh(),
            sellerUserId: $buyer->id,
            askingPriceIdr: 1100000
        );
    }

    /**
     * Skenario 3: Dynamic Event Bundling & Floor Margin Guardrail
     */
    public function test_dynamic_bundle_pricing_and_floor_guardrail(): void
    {
        $bundlePricer = app(DynamicBundlePricer::class);

        // Komponen paket: Tiket Konser (Floor 400k, Base 800k), Kamar Hotel (Floor 600k, Base 1.2M), Shuttle Bus (Floor 100k, Base 200k)
        // Total Floor Cost = Rp 1.100.000, Total Base Price = Rp 2.200.000
        $components = [
            ['name' => 'VIP Ticket', 'floor_cost' => 400000, 'base_price' => 800000],
            ['name' => 'Deluxe Suite Room', 'floor_cost' => 600000, 'base_price' => 1200000],
            ['name' => 'Airport Shuttle Bus', 'floor_cost' => 100000, 'base_price' => 200000],
        ];

        // Okupansi tinggi 90% (surge) dan lead time 5 hari
        $bundleInput = new DynamicBundleInput(
            components: $components,
            venueOccupancyPercentage: 90,
            hotelOccupancyPercentage: 85,
            leadTimeDays: 5
        );

        $bundleResult = $bundlePricer->calculatePrice($bundleInput);
        $this->assertGreaterThan(2200000, $bundleResult->finalBundlePriceIdr);
        $this->assertGreaterThanOrEqual(1100000, $bundleResult->finalBundlePriceIdr);

        // Simpan bundle via action
        $allocateAction = app(AllocateDynamicBundleAction::class);
        $bundlePackage = $allocateAction->execute(
            eventId: $this->event->id,
            packageName: 'Sunset Luxury Weekend Getaway',
            components: $components,
            occupancyPercentage: 90,
            leadTimeDays: 5
        );

        $this->assertEquals('PUBLISHED', $bundlePackage->status);
        $this->assertEquals(3, $bundlePackage->components()->count());
    }

    /**
     * Skenario 4: Waitlist Priority Ranking & 15-Minute Expiry Timer
     */
    public function test_waitlist_priority_ranking_and_fifteen_minute_timed_offer(): void
    {
        $governor = app(WaitlistPriorityGovernor::class);

        // 3 Pendaftar: User A (Sun Tier, Belanja 5M), User B (Infinity Tier, Belanja 25M), User C (Non-member)
        $candidates = [
            ['user_id' => 101, 'tier' => 'Sun', 'spend_365_idr' => 5000000, 'registered_seconds_ago' => 600],
            ['user_id' => 102, 'tier' => 'Infinity', 'spend_365_idr' => 25000000, 'registered_seconds_ago' => 300],
            ['user_id' => 103, 'tier' => 'Reguler', 'spend_365_idr' => 500000, 'registered_seconds_ago' => 1200],
        ];

        $rankingResult = $governor->rankCandidates(new WaitlistGovernorInput($candidates));
        // User B (Infinity) wajib menempati ranking #1
        $this->assertEquals(102, $rankingResult->rankedUserIds[0]);

        // Simulasikan pendaftaran antrean dan auto-offer 15 menit
        $waitlist = TicketWaitlist::query()->create([
            'waitlist_code' => 'WTL-DEMO-001',
            'event_id' => $this->event->id,
            'zone_id' => $this->zone->id,
            'user_id' => 102,
            'status' => 'WAITING',
        ]);

        $offerAction = app(OfferSeatToNextWaitlistAction::class);
        $offeredWaitlist = $offerAction->execute($waitlist);

        $this->assertEquals('OFFERED', $offeredWaitlist->status);
        $this->assertNotNull($offeredWaitlist->offer_expires_at);
        $this->assertTrue($offeredWaitlist->offer_expires_at->isFuture());

        // Klaim penawaran sebelum 15 menit lewat
        $claimAction = app(ClaimWaitlistSeatOfferAction::class);
        $claimed = $claimAction->execute($offeredWaitlist);
        $this->assertEquals('CLAIMED', $claimed->status);
    }

    /**
     * Skenario 5: Waterfall Pajak PBJT Sekunder & Audit Mandiri Integritas
     */
    public function test_secondary_market_tax_waterfall_and_audit_reconciliation(): void
    {
        $waterfallEngine = app(SecondaryMarketTaxWaterfallEngine::class);

        // Resale Price Rp 1.200.000, Original Face Value Rp 1.000.000
        $input = new SecondaryMarketTaxWaterfallInput(
            resalePriceIdr: 1200000,
            originalFaceValueIdr: 1000000,
            taxRateBps: 1000, // PBJT 10%
            platformFeeRateBps: 800, // Platform 8%
            promoterRoyaltyBps: 200 // Promotor 2%
        );

        $split = $waterfallEngine->calculateWaterfall($input);

        $this->assertEquals(120000, $split->taxPbjtIdr); // 10% of 1.2M
        $this->assertEquals(96000, $split->platformFeeIdr); // 8% of 1.2M
        $this->assertEquals(24000, $split->promoterRoyaltyIdr); // 2% of 1.2M
        $this->assertEquals(960000, $split->sellerPayoutIdr); // Sisa bersih
        // Invarian Konservasi Total
        $this->assertEquals(
            1200000,
            $split->taxPbjtIdr + $split->platformFeeIdr + $split->promoterRoyaltyIdr + $split->sellerPayoutIdr
        );

        // Eksekusi Command Audit Mandiri Ekonomi Sekunder
        $exitCode = Artisan::call('venue:audit-secondary');
        $this->assertEquals(0, $exitCode);
    }
}
