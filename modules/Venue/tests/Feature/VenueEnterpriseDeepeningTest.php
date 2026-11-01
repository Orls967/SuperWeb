<?php

declare(strict_types=1);

namespace Modules\Venue\tests\Feature;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Venue\Application\Actions\AccrueCreatorStreamRoyaltyIdempotentAction;
use Modules\Venue\Application\Actions\ApplyDynamicBottleSurgePricingAction;
use Modules\Venue\Application\Actions\ExecuteArtistContractEscrowAndWhtPayoutAction;
use Modules\Venue\Application\Actions\IssueFestivalTicketWithGaplessHashAction;
use Modules\Venue\Application\Actions\ReconcileLiquorPourSpoutVarianceAction;
use Modules\Venue\Application\Actions\ReleaseCreatorHeldRoyaltyAfterWindowAction;
use Modules\Venue\Application\Actions\ReserveTableWithMinimumSpendDepositAction;
use Modules\Venue\Application\Actions\ScanTicketGateWithAntiPassbackAndAgeCheckAction;
use Modules\Venue\Domain\Models\ArtistContract;
use Modules\Venue\Domain\Models\Creator;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\RightsContract;
use Tests\TestCase;

class VenueEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    private EntertainmentVenue $venue;

    private EntertainmentZone $zone;

    private EntertainmentEvent $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-BEACH-01',
            'name' => 'Azure Beach Club Bali',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 1200,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZONE-POOL-01',
            'venue_id' => $this->venue->id,
            'name' => 'Main Infinity Pool Deck',
            'capacity_limit' => 300,
            'current_occupancy' => 50,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-SUNSET-FEST',
            'venue_id' => $this->venue->id,
            'title' => 'Bali Sunset Sessions 2026',
            'doors_open_at' => now()->addDays(5),
            'ticket_floor_price_idr' => 350000,
            'ticket_ceiling_price_idr' => 1500000,
            'current_ticket_price_idr' => 500000,
        ]);

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_issue_festival_ticket_with_gapless_hash_action_generates_sequential_ticket(): void
    {
        $action = app(IssueFestivalTicketWithGaplessHashAction::class);

        $result1 = $action->handle(
            eventId: $this->event->id,
            buyerUserId: $this->admin->id,
            pricePaidIdr: 500000
        );

        $result2 = $action->handle(
            eventId: $this->event->id,
            buyerUserId: $this->admin->id,
            pricePaidIdr: 500000
        );

        $this->assertEquals(sprintf('TKT-%04d-000001', $this->event->id), $result1['ticket_number']);
        $this->assertEquals(sprintf('TKT-%04d-000002', $this->event->id), $result2['ticket_number']);
        $this->assertNotEmpty($result1['ticket_hash']);
        $this->assertNotEquals($result1['ticket_hash'], $result2['ticket_hash']);

        $this->assertDatabaseHas('ven_entertainment_tickets', [
            'ticket_number' => $result1['ticket_number'],
            'status' => 'VALID',
        ]);
    }

    public function test_scan_ticket_gate_action_allows_valid_adult_ticket(): void
    {
        $action = app(ScanTicketGateWithAntiPassbackAndAgeCheckAction::class);

        $ticket = EntertainmentTicket::create([
            'ticket_number' => 'TKT-TEST-001',
            'event_id' => $this->event->id,
            'buyer_user_id' => $this->admin->id,
            'price_paid_idr' => 500000,
            'ticket_hash' => 'dummyhash123',
            'status' => 'VALID',
        ]);

        $result = $action->handle(
            ticketId: $ticket->id,
            attendeeAge: 25,
            zoneId: $this->zone->id
        );

        $this->assertEquals('ALLOWED', $result['status']);
        $this->assertEquals(51, $result['zone_occupancy']);

        $ticket->refresh();
        $this->assertEquals('USED', $ticket->status);
        $this->assertNotNull($ticket->scanned_at);
    }

    public function test_scan_ticket_gate_action_blocks_underage_attendee(): void
    {
        $action = app(ScanTicketGateWithAntiPassbackAndAgeCheckAction::class);

        $ticket = EntertainmentTicket::create([
            'ticket_number' => 'TKT-TEST-002',
            'event_id' => $this->event->id,
            'buyer_user_id' => $this->admin->id,
            'price_paid_idr' => 500000,
            'ticket_hash' => 'dummyhash456',
            'status' => 'VALID',
        ]);

        $result = $action->handle(
            ticketId: $ticket->id,
            attendeeAge: 19, // underage (<21)
            zoneId: $this->zone->id
        );

        $this->assertEquals('DENIED', $result['status']);
        $this->assertStringContainsString('Underage', $result['reason']);

        $ticket->refresh();
        $this->assertEquals('VALID', $ticket->status); // not consumed
    }

    public function test_scan_ticket_gate_action_enforces_anti_passback_preventing_double_scan(): void
    {
        $action = app(ScanTicketGateWithAntiPassbackAndAgeCheckAction::class);

        $ticket = EntertainmentTicket::create([
            'ticket_number' => 'TKT-TEST-003',
            'event_id' => $this->event->id,
            'buyer_user_id' => $this->admin->id,
            'price_paid_idr' => 500000,
            'ticket_hash' => 'dummyhash789',
            'status' => 'USED', // already scanned
        ]);

        $result = $action->handle(
            ticketId: $ticket->id,
            attendeeAge: 28,
            zoneId: $this->zone->id
        );

        $this->assertEquals('DENIED', $result['status']);
        $this->assertStringContainsString('Anti-passback violation', $result['reason']);
    }

    public function test_reserve_table_with_minimum_spend_deposit_action_locks_table_and_escrow(): void
    {
        $action = app(ReserveTableWithMinimumSpendDepositAction::class);

        $result = $action->handle(
            eventId: $this->event->id,
            userId: $this->admin->id,
            tableNumber: 'CABANA-05',
            minimumSpendIdr: 20000000,
            depositAmountIdr: 5000000,
            idempotencyKey: 'test-cabana-05-deposit'
        );

        $this->assertStringStartsWith('VIP-CABANA-05-', $result['booking_code']);
        $this->assertEquals(5000000, $result['deposit_amount_idr']);

        $this->assertDatabaseHas('ven_entertainment_table_bookings', [
            'event_id' => $this->event->id,
            'table_number' => 'CABANA-05',
            'status' => 'HELD',
        ]);

        // Attempt duplicate reservation for same table -> must fail
        $this->expectException(\RuntimeException::class);
        $action->handle(
            eventId: $this->event->id,
            userId: $this->admin->id,
            tableNumber: 'CABANA-05',
            minimumSpendIdr: 20000000,
            depositAmountIdr: 5000000,
            idempotencyKey: 'test-cabana-05-dup'
        );
    }

    public function test_apply_dynamic_bottle_surge_pricing_action_scales_with_occupancy_and_time(): void
    {
        $action = app(ApplyDynamicBottleSurgePricingAction::class);

        // Peak night (occupancy 98%, 23:00) -> 1.50x
        $peak = $action->handle('BOTTLE-VODKA-01', 3000000, 98.0, 23);
        $this->assertEquals(1.50, $peak['surge_multiplier']);
        $this->assertEquals(4500000, $peak['final_price_idr']);
        $this->assertEquals('PEAK_SURGE_150', $peak['surge_tier']);

        // Standard daytime (occupancy 60%, 15:00) -> 1.00x
        $std = $action->handle('BOTTLE-VODKA-01', 3000000, 60.0, 15);
        $this->assertEquals(1.00, $std['surge_multiplier']);
        $this->assertEquals(3000000, $std['final_price_idr']);
    }

    public function test_execute_artist_contract_escrow_and_wht_payout_action_deducts_advance_and_tax(): void
    {
        $contract = ArtistContract::create([
            'id' => (string) Str::uuid(),
            'venue_id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'contract_number' => 'ART-BALI-DJ-01',
            'artist_name' => 'DJ Solar Eclipse',
            'advance_amount' => 15000000,
            'door_share_percentage' => 20.0,
            'total_door_sales' => 0,
            'door_share_gross' => 0,
            'net_payout_amount' => 0,
            'currency' => 'IDR',
            'status' => 'active',
        ]);

        $action = app(ExecuteArtistContractEscrowAndWhtPayoutAction::class);

        $result = $action->handle(
            artistContractId: $contract->id,
            totalDoorSalesIdr: 200000000, // 200M door sales -> 20% = 40M gross
            whtRatePct: 2.0, // PPh 23 2% = 800,000 IDR
            idempotencyKey: 'test-artist-settlement-1'
        );

        $this->assertEquals(40000000, $result['gross_share_idr']);
        $this->assertEquals(800000, $result['wht_tax_idr']);
        $this->assertEquals(15000000, $result['advance_deducted_idr']);
        // Net = 40,000,000 - 800,000 - 15,000,000 = 24,200,000 IDR
        $this->assertEquals(24200000, $result['net_payout_idr']);

        $contract->refresh();
        $this->assertEquals('settled', $contract->status);
    }

    public function test_reconcile_liquor_pour_spout_variance_action_flags_over_pour_shrinkage(): void
    {
        $action = app(ReconcileLiquorPourSpoutVarianceAction::class);

        // 20 shots billed @ 45ml = 900ml expected.
        // Metered dispensed = 1050ml -> variance = +150ml (16.67% over-pour) -> Shrinkage flag!
        $result = $action->handle(
            bottleSku: 'WHISKEY-SINGLE-MALT',
            billedShots: 20,
            meteredDispensedMl: 1050.0,
            standardShotVolumeMl: 45.0
        );

        $this->assertEquals(900.0, $result['expected_dispensed_ml']);
        $this->assertEquals(150.0, $result['variance_ml']);
        $this->assertTrue($result['is_shrinkage_flagged']);
        $this->assertEquals('SHRINKAGE_ALERT_OVER_POUR', $result['status']);
    }

    public function test_accrue_and_release_creator_stream_royalty_actions_flow(): void
    {
        $creator = Creator::create([
            'creator_code' => 'CRT-BALI-01',
            'stage_name' => 'Island Beats Collective',
            'genre' => 'Deep House',
            'status' => 'ACTIVE',
        ]);

        $contract = RightsContract::create([
            'contract_code' => 'RIGHTS-STREAM-01',
            'creator_id' => $creator->id,
            'royalty_share_percent' => 70.0,
            'payout_hold_window_days' => 14,
            'accrued_royalties_idr' => 0,
            'held_royalties_idr' => 0,
            'paid_royalties_idr' => 0,
            'status' => 'ACTIVE',
        ]);

        $accrueAction = app(AccrueCreatorStreamRoyaltyIdempotentAction::class);
        $accrueResult = $accrueAction->handle(
            rightsContractId: $contract->id,
            streamCount: 10000,
            ratePerStreamIdr: 200 // 2,000,000 gross
        );

        $this->assertEquals(2000000, $accrueResult['gross_stream_revenue_idr']);
        $this->assertEquals(1400000, $accrueResult['creator_share_idr']); // 70%
        $this->assertEquals(600000, $accrueResult['platform_fee_idr']);

        // Now release held royalties with 2.5% tax withholding
        $releaseAction = app(ReleaseCreatorHeldRoyaltyAfterWindowAction::class);
        $releaseResult = $releaseAction->handle(
            rightsContractId: $contract->id,
            whtRatePct: 2.5 // 2.5% of 1,400,000 = 35,000 IDR
        );

        $this->assertEquals(1400000, $releaseResult['released_amount_idr']);
        $this->assertEquals(35000, $releaseResult['wht_tax_idr']);
        $this->assertEquals(1365000, $releaseResult['net_payout_idr']);

        $contract->refresh();
        $this->assertEquals(0, $contract->held_royalties_idr);
        $this->assertEquals(1400000, $contract->paid_royalties_idr);
    }

    public function test_venue_enterprise_http_routes_workflow(): void
    {
        // 1. Ticketing page
        $tktGet = $this->actingAs($this->admin)->get(route('venue.ticketing.index'));
        $tktGet->assertOk();
        $tktGet->assertSeeText('Festival Gapless Sequential Ticketing');

        // 2. Issue ticket post
        $tktPost = $this->actingAs($this->admin)->post(route('venue.ticketing.issue'), [
            'event_id' => $this->event->id,
            'price_paid_idr' => 600000,
            'ticket_tier' => 'VIP_SUNSET',
        ]);
        $tktPost->assertRedirect(route('venue.ticketing.index'));
        $tktPost->assertSessionHas('success');

        // 3. Tables page
        $tblGet = $this->actingAs($this->admin)->get(route('venue.tables.index'));
        $tblGet->assertOk();
        $tblGet->assertSeeText('VIP Cabana');

        // 4. Bottle surge page & post
        $surgeGet = $this->actingAs($this->admin)->get(route('venue.tables.surge'));
        $surgeGet->assertOk();

        $surgePost = $this->actingAs($this->admin)->post(route('venue.tables.surge.calculate'), [
            'bottle_sku' => 'TEQUILA-DON-JULIO-1942',
            'base_price_idr' => 9500000,
            'venue_occupancy_pct' => 97.0,
            'hour_of_day' => 23,
        ]);
        $surgePost->assertRedirect(route('venue.tables.surge'));
        $surgePost->assertSessionHas('success');
    }
}
