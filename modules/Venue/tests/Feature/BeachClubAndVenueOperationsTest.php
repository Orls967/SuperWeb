<?php

namespace Modules\Venue\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Venue\Application\Services\VenueOperationsService;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Tests\TestCase;

class BeachClubAndVenueOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected VenueOperationsService $service;

    protected EntertainmentVenue $venue;

    protected EntertainmentZone $zone;

    protected EntertainmentEvent $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VenueOperationsService::class);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-BALI-BEACH-01',
            'name' => 'Atlas Beach Club Canggu',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 2000,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZN-POOL-VIP',
            'venue_id' => $this->venue->id,
            'name' => 'Lagoon Pool VIP Deck',
            'capacity_limit' => 100,
            'current_occupancy' => 99,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-SUNSET-SUMMER',
            'venue_id' => $this->venue->id,
            'title' => 'Sunset Summer Electronic Festival',
            'doors_open_at' => now()->addDays(2),
            'ticket_floor_price_idr' => 250_000,
            'ticket_ceiling_price_idr' => 1_500_000,
            'current_ticket_price_idr' => 500_000,
        ]);

        // Ledger accounts setup
        $accounts = [
            'ven:ticket_clearing:IDR' => 'asset',
            'ven:ticket_revenue:IDR' => 'revenue',
            'ven:vip_escrow:IDR' => 'liability',
            'ven:vip_unearned_rev:IDR' => 'liability',
            'ven:vip_noshow_rev:IDR' => 'revenue',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Venue {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_89_3_ticket_purchase_and_anti_replay_gate_scan(): void
    {
        $ticket = $this->service->purchaseTicket(
            event: $this->event,
            buyerUserId: 401,
            pricePaidIdr: 500_000
        );

        $this->assertEquals('VALID', $ticket->status);
        $this->assertNotNull($ticket->ticket_hash);

        // Verify ledger ticket sales
        $rev = LedgerAccount::where('code', 'ven:ticket_revenue:IDR')->first();
        $this->assertEquals('-500000', (string) $rev->cached_balance);

        // 1. Patron below 21 denied access
        try {
            $this->service->scanGateAccess($ticket, $this->zone, patronAge: 19);
            $this->fail('Underage patron should be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('below venue minimum requirement', $e->getMessage());
        }

        // 2. Successful scan for 22 year old patron
        $scanned = $this->service->scanGateAccess($ticket, $this->zone, patronAge: 22);
        $this->assertEquals('USED', $scanned->status);
        $this->assertNotNull($scanned->scanned_at);
        $this->assertEquals(100, $this->zone->refresh()->current_occupancy);

        // 3. Replay attack: scanned ticket used again fails
        try {
            $this->service->scanGateAccess($scanned, $this->zone, patronAge: 25);
            $this->fail('Replay ticket must be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Replay access denied', $e->getMessage());
        }
    }

    public function test_89_4_zone_at_max_capacity_blocks_further_entry(): void
    {
        // Zone occupancy is 99/100, one entry brings it to 100/100
        $t1 = $this->service->purchaseTicket($this->event, 501, 500_000);
        $this->service->scanGateAccess($t1, $this->zone, 25);

        // Next entry when zone is 100/100 must be blocked for crowd safety
        $t2 = $this->service->purchaseTicket($this->event, 502, 500_000);
        $this->expectException(\RuntimeException::class);
        $this->service->scanGateAccess($t2, $this->zone, 25);
    }

    public function test_89_5_vip_table_escrow_deposit_and_no_show_forfeiture(): void
    {
        // Minimum spend 10,000,000 IDR -> 50% deposit = 5,000,000 IDR held in escrow
        $booking = $this->service->bookVipTable(
            event: $this->event,
            tableNumber: 'VIP-BED-01',
            userId: 777,
            minimumSpendIdr: 10_000_000
        );

        $this->assertEquals(5_000_000, $booking->deposit_amount_idr);
        $this->assertEquals('HELD', $booking->status);

        // Verify ledger escrow deposit hold
        $escrow = LedgerAccount::where('code', 'ven:vip_escrow:IDR')->first();
        $this->assertEquals('5000000', (string) $escrow->cached_balance);

        // Guest does not show up -> retain deposit as penalty revenue
        $noShow = $this->service->handleTableNoShow($booking);
        $this->assertEquals('NO_SHOW', $noShow->status);

        $penaltyRev = LedgerAccount::where('code', 'ven:vip_noshow_rev:IDR')->first();
        $this->assertEquals('-5000000', (string) $penaltyRev->cached_balance);
    }
}
