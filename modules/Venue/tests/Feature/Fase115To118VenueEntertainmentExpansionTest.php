<?php

declare(strict_types=1);

namespace Modules\Venue\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Venue\Application\Actions\ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction;
use Modules\Venue\Application\Actions\OptimizeGlobalHospitalityEntertainmentPortfolioRevparAction;
use Modules\Venue\Application\Actions\SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction;
use Modules\Venue\Application\Actions\VerifyBiometricCrowdAccessAndZoneCrushCapacityAction;
use Modules\Venue\Domain\Models\Creator;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use RuntimeException;
use Tests\TestCase;

class Fase115To118VenueEntertainmentExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected Creator $creator;

    protected EntertainmentVenue $venue;

    protected EntertainmentZone $zone;

    protected EntertainmentEvent $event;

    protected EntertainmentTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->creator = Creator::create([
            'creator_code' => 'CRT-DJ-001',
            'stage_name' => 'DJ Solstice',
            'genre' => 'MELODIC_TECHNO',
            'status' => 'ACTIVE',
        ]);

        $this->venue = EntertainmentVenue::create([
            'venue_code' => 'VEN-BALI-001',
            'name' => 'Sunset Beach Club & Amphitheater',
            'venue_type' => 'BEACH_CLUB',
            'city' => 'Badung',
            'max_legal_capacity' => 2500,
            'min_age_requirement' => 21,
        ]);

        $this->zone = EntertainmentZone::create([
            'zone_code' => 'ZN-VIP-DECK',
            'venue_id' => $this->venue->id,
            'name' => 'VIP Sunset Deck',
            'capacity_limit' => 200,
            'current_occupancy' => 50,
            'crowd_lockdown_active' => false,
        ]);

        $this->event = EntertainmentEvent::create([
            'event_code' => 'EVT-FEST-001',
            'venue_id' => $this->venue->id,
            'title' => 'Solstice Horizon Festival 2026',
            'doors_open_at' => date('Y-m-d 16:00:00'),
            'ticket_floor_price_idr' => 750000,
            'ticket_ceiling_price_idr' => 2500000,
            'current_ticket_price_idr' => 1500000,
        ]);

        $this->ticket = EntertainmentTicket::create([
            'ticket_number' => 'TKT-001-000001',
            'event_id' => $this->event->id,
            'buyer_user_id' => 101,
            'price_paid_idr' => 1000000,
            'ticket_hash' => hash('sha256', 'ORIGINAL-TKT-HASH-001'),
            'status' => 'VALID',
        ]);
    }

    public function test_115_creator_rights_syndication_and_held_waterfall_escrow(): void
    {
        $action = app(SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction::class);

        $result = $action->handle(
            creatorId: $this->creator->id,
            assetTitle: 'Solstice Live Sunset Set 2026 4K',
            mediaType: 'DJ_SET_VIDEO',
            contentHash: hash('sha256', 'VIDEO-MEDIA-CONTENT-HASH-001'),
            royaltyPercent: 75.0,
            streamGrossRevenueIdr: 20000000
        );

        $this->assertNotNull($result['asset']);
        $this->assertEquals('EXCLUSIVE', $result['asset']->license_type);
        $this->assertEquals(15000000, $result['accrued_royalty_idr']);
        $this->assertEquals(15000000, $result['contract']->held_royalties_idr);
        $this->assertNull($result['merch_sale']);
    }

    public function test_115_merchandise_sales_revenue_split_between_artist_and_platform(): void
    {
        $action = app(SyndicateCreatorMediaRightsAndWaterfallRoyaltyAction::class);

        $result = $action->handle(
            creatorId: $this->creator->id,
            assetTitle: 'Solstice Anthem FLAC Audio',
            mediaType: 'TRACK_AUDIO',
            contentHash: hash('sha256', 'AUDIO-TRACK-HASH-002'),
            royaltyPercent: 70.0,
            streamGrossRevenueIdr: 0,
            merchSaleGrossIdr: 500000,
            merchItemName: 'Solstice Limited Edition Hoodie'
        );

        $this->assertNotNull($result['merch_sale']);
        $this->assertEquals(300000, $result['merch_sale']->artist_share_idr);
        $this->assertEquals(200000, $result['merch_sale']->platform_share_idr);
        $this->assertEquals('SETTLED', $result['merch_sale']->status);
    }

    public function test_116_secondary_ticket_listing_respects_120_percent_price_cap(): void
    {
        $action = app(ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction::class);

        $result = $action->handle(
            ticketId: $this->ticket->id,
            sellerUserId: 101,
            askingPriceIdr: 1200000 // Exactly 120% of 1000000
        );

        $this->assertNotNull($result['resale']);
        $this->assertEquals('LISTED', $result['resale']->status);
        $this->assertEquals(1200000, $result['resale']->resale_price_idr);
    }

    public function test_116_secondary_ticket_exceeding_120_percent_cap_is_rejected(): void
    {
        $action = app(ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds anti-scalping price cap');

        $action->handle(
            ticketId: $this->ticket->id,
            sellerUserId: 101,
            askingPriceIdr: 1300000 // 130% > 120% cap
        );
    }

    public function test_116_second_secondary_transfer_attempt_is_rejected(): void
    {
        $action = app(ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction::class);

        // First secondary purchase (101 -> 102)
        $action->handle(
            ticketId: $this->ticket->id,
            sellerUserId: 101,
            askingPriceIdr: 1100000,
            buyerUserId: 102
        );

        // Attempting second secondary listing (102 -> 103) must be rejected
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('maximum of 1 secondary transfer');

        $action->handle(
            ticketId: $this->ticket->id,
            sellerUserId: 102,
            askingPriceIdr: 1100000
        );
    }

    public function test_116_secondary_ticket_purchase_splits_platform_fee_and_tax(): void
    {
        $action = app(ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction::class);

        $result = $action->handle(
            ticketId: $this->ticket->id,
            sellerUserId: 101,
            askingPriceIdr: 1000000,
            buyerUserId: 202
        );

        $this->assertEquals(100000, $result['platform_fee_idr']);
        $this->assertEquals(100000, $result['entertainment_tax_idr']);
        $this->assertEquals(800000, $result['seller_payout_idr']);
        $this->assertEquals(202, $result['ticket']->buyer_user_id);
    }

    public function test_117_biometric_face_id_template_registration_and_verification(): void
    {
        $action = app(VerifyBiometricCrowdAccessAndZoneCrushCapacityAction::class);

        $result = $action->handle(
            userId: 301,
            featureVector: 'VEC-FLOAT-301-889922',
            livenessProof: 'PROOF-BLINK-SMILE-301',
            zoneId: $this->zone->id,
            currentHeadcount: 100
        );

        $this->assertNotNull($result['credential']);
        $this->assertTrue($result['credential']->is_active);
        $this->assertFalse($result['gate_restricted']);
    }

    public function test_117_automatic_gate_lockout_when_crowd_crush_capacity_exceeded(): void
    {
        $action = app(VerifyBiometricCrowdAccessAndZoneCrushCapacityAction::class);

        // VIP zone capacity is 200. With 190 headcount, predicted 15m is 209 (>= 200).
        $result = $action->handle(
            userId: 302,
            featureVector: 'VEC-FLOAT-302-112233',
            livenessProof: 'PROOF-BLINK-SMILE-302',
            zoneId: $this->zone->id,
            currentHeadcount: 190
        );

        $this->assertTrue($result['gate_restricted']);
        $this->assertEquals('RESTRICT_ACCESS', $result['telemetry']->recommended_action);
    }

    public function test_117_personalized_promotion_deduplication(): void
    {
        $action = app(VerifyBiometricCrowdAccessAndZoneCrushCapacityAction::class);

        // First dispatch
        $result1 = $action->handle(
            userId: 303,
            featureVector: 'VEC-FLOAT-303-445566',
            livenessProof: 'PROOF-BLINK-SMILE-303',
            zoneId: $this->zone->id,
            currentHeadcount: 80,
            promoTitle: 'Free Sunset Cocktail with Bottle Order',
            promoDiscountPct: 20
        );

        $this->assertNotNull($result1['promo']);

        // Duplicate dispatch in same zone
        $result2 = $action->handle(
            userId: 303,
            featureVector: 'VEC-FLOAT-303-445566',
            livenessProof: 'PROOF-BLINK-SMILE-303',
            zoneId: $this->zone->id,
            currentHeadcount: 85,
            promoTitle: 'Free Sunset Cocktail with Bottle Order',
            promoDiscountPct: 20
        );

        $this->assertNull($result2['promo']); // Deduplicated!
    }

    public function test_118_global_hospitality_and_entertainment_portfolio_revpar_optimization(): void
    {
        $action = app(OptimizeGlobalHospitalityEntertainmentPortfolioRevparAction::class);

        $result = $action->handle(
            regionCity: 'Badung',
            year: 2026,
            month: 10,
            adrIdr: 2500000,
            occupancyPercent: 80.0,
            venueTicketGmvIdr: 5000000000,
            travelBundleGmvIdr: 3000000000,
            fxRateUsdToIdr: 16000
        );

        $this->assertEquals(2000000, $result['revpar_idr']);
        $this->assertEquals(8000000000, $result['total_gmv_idr']);
        $this->assertEquals(500000, $result['total_gmv_usd']);
    }
}
