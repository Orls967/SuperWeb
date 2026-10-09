<?php

namespace Modules\Venue\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Venue\Application\Services\VenueOperationsService;
use Modules\Venue\Application\Services\VenueSecondaryMarketService;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use RuntimeException;
use Tests\TestCase;

class VenueSecondaryMarketTest extends TestCase
{
    use RefreshDatabase;

    protected VenueSecondaryMarketService $service;

    protected EntertainmentEvent $event;

    protected EntertainmentZone $zone;

    protected EntertainmentTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VenueSecondaryMarketService::class);

        $venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-BALI-01',
            'name' => 'Sunset Beach Club Uluwatu',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 1500,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'venue_id' => $venue->id,
            'zone_code' => 'ZONE-VIP-01',
            'name' => 'VIP Sunset Deck',
            'capacity_limit' => 200,
            'current_occupancy' => 0,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-FEST-01',
            'venue_id' => $venue->id,
            'title' => 'Tropical Dream Festival 2026',
            'doors_open_at' => now()->addDays(5),
            'ticket_floor_price_idr' => 500_000,
            'ticket_ceiling_price_idr' => 2_000_000,
            'current_ticket_price_idr' => 1_000_000,
        ]);

        // Register venue secondary and primary ledger accounts
        $accounts = [
            'ven:ticket_clearing:IDR' => 'asset',
            'ven:ticket_revenue:IDR' => 'revenue',
            'ven:secondary_ticket_clearing:IDR' => 'asset',
            'ven:resale_seller_payable:IDR' => 'liability',
            'ven:resale_platform_fee_revenue:IDR' => 'revenue',
            'ven:entertainment_tax_payable:IDR' => 'liability',
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

        $opsService = app(VenueOperationsService::class);
        $this->ticket = $opsService->purchaseTicket(
            event: $this->event,
            buyerUserId: 101,
            pricePaidIdr: 1_000_000
        );
    }

    public function test_116_1_and_116_6_b_price_cap_enforced_anti_scalping(): void
    {
        // Face value: 1,000,000 IDR. Max allowed price: 120% = 1,200,000 IDR.
        // Attempting to list at 1,500,000 IDR must fail!
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds anti-scalping price cap');

        $this->service->listTicketForResale($this->ticket, 101, 1_500_000);
    }

    public function test_116_1_and_116_6_a_and_e_valid_resale_purchase_and_second_transfer_blocked(): void
    {
        // 1. List at valid capped price: 1,150,000 IDR
        $resale = $this->service->listTicketForResale($this->ticket, 101, 1_150_000);
        $this->assertEquals('LISTED', $resale->status);
        $this->assertEquals(1_150_000, $resale->resale_price_idr);

        // 2. Buyer (202) purchases ticket:
        // Platform fee: 10% = 115,000 IDR
        // Tax: 10% = 115,000 IDR
        // Seller payout = 1,150,000 - 230,000 = 920,000 IDR
        $purchased = $this->service->purchaseResaleTicket($resale, 202);
        $this->assertEquals('SOLD', $purchased->status);
        $this->assertEquals(202, $purchased->buyer_user_id);
        $this->assertEquals(202, $this->ticket->refresh()->buyer_user_id);

        // Verify ledger balances
        $feeRev = LedgerAccount::where('code', 'ven:resale_platform_fee_revenue:IDR')->first();
        $taxPayable = LedgerAccount::where('code', 'ven:entertainment_tax_payable:IDR')->first();
        $sellerPayable = LedgerAccount::where('code', 'ven:resale_seller_payable:IDR')->first();

        $this->assertEquals('-115000', (string) $feeRev->cached_balance);
        $this->assertEquals('-115000', (string) $taxPayable->cached_balance);
        $this->assertEquals('-920000', (string) $sellerPayable->cached_balance);

        // 3. Rule 116.6 (a): Attempting a 2nd transfer on the same ticket must be rejected!
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already reached maximum of 1 secondary transfer');

        $this->service->listTicketForResale($this->ticket, 202, 1_100_000);
    }

    public function test_116_4_waitlist_offer_timer(): void
    {
        $waitlist = $this->service->joinWaitlist($this->event->id, $this->zone->id, 303);
        $this->assertEquals('WAITING', $waitlist->status);

        $offered = $this->service->offerSeatToWaitlist($waitlist, 15);
        $this->assertEquals('OFFERED', $offered->status);
        $this->assertNotNull($offered->offer_expires_at);
    }
}
