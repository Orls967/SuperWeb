<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Actions\CalculateHotelFranchiseRoyaltyAndBrandAuditAction;
use Modules\Hotel\Application\Actions\ComposeMultiModalTravelItineraryWithSlaGuaranteeAction;
use Modules\Hotel\Application\Actions\ContractGlobalMiceAndWeddingEventEscrowAction;
use Modules\Hotel\Application\Actions\RedeemGlobalLoyaltyTravelPassAcrossPropertiesAction;
use Modules\Hotel\Domain\Models\HotelProperty;
use RuntimeException;
use Tests\TestCase;

class Fase111To114HotelGlobalExpansionTest extends TestCase
{
    use RefreshDatabase;

    protected HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-BALI-111',
            'name' => 'The Royal Purnama Bali Resort & Villas',
            'property_type' => 'RESORT',
            'city' => 'Gianyar',
            'total_rooms' => 150,
        ]);
    }

    public function test_111_brand_standard_audit_calculates_5_star_grade_and_active_listing(): void
    {
        $action = app(CalculateHotelFranchiseRoyaltyAndBrandAuditAction::class);

        $result = $action->handle(
            propertyId: $this->property->id,
            passedChecklistItems: 190,
            totalChecklistItems: 200,
            monthlyFolioRevenueIdr: 800000000,
            royaltyPercentage: 5.0
        );

        $this->assertEquals(95.0, $result['audit']->compliance_score_percent);
        $this->assertEquals('5_STAR_LUXURY', $result['audit']->simulated_star_grade);
        $this->assertEquals('ACTIVE', $result['audit']->listing_status);
        $this->assertEquals(40000000, $result['royalty_due_idr']);
        $this->assertNull($result['violation']);
    }

    public function test_111_brand_standard_audit_below_60_suspends_listing(): void
    {
        $action = app(CalculateHotelFranchiseRoyaltyAndBrandAuditAction::class);

        $result = $action->handle(
            propertyId: $this->property->id,
            passedChecklistItems: 100,
            totalChecklistItems: 200,
            monthlyFolioRevenueIdr: 200000000,
            royaltyPercentage: 5.0
        );

        $this->assertEquals(50.0, $result['audit']->compliance_score_percent);
        $this->assertEquals('UNGRADED', $result['audit']->simulated_star_grade);
        $this->assertEquals('SUSPENDED', $result['audit']->listing_status);
    }

    public function test_111_franchise_royalty_and_rate_parity_penalty_levy(): void
    {
        $action = app(CalculateHotelFranchiseRoyaltyAndBrandAuditAction::class);

        $result = $action->handle(
            propertyId: $this->property->id,
            passedChecklistItems: 160,
            totalChecklistItems: 200,
            monthlyFolioRevenueIdr: 600000000,
            royaltyPercentage: 5.0,
            otaChannel: 'OTA_EXPEDIA',
            directBarRateIdr: 2000000,
            otaUndercutRateIdr: 1700000
        );

        $this->assertEquals(30000000, $result['royalty_due_idr']);
        $this->assertNotNull($result['violation']);
        $this->assertEquals(600000, $result['violation']->penalty_levy_idr);
        $this->assertEquals('PENALTY_ACCRUED', $result['violation']->status);
    }

    public function test_112_travel_pass_points_issuance_and_pts_liability(): void
    {
        $action = app(RedeemGlobalLoyaltyTravelPassAcrossPropertiesAction::class);

        $result = $action->handle(
            userId: 991,
            originatingLine: 'HOTEL',
            ptsToEarn: 12000
        );

        $this->assertEquals(12000, $result['pts_balance']);
        $this->assertEquals('GOLD', $result['pass']->tier);
        $this->assertNotNull($result['entry']);
        $this->assertEquals('EARN', $result['entry']->transaction_type);
        $this->assertEquals(1200000, $result['entry']->liability_value_idr);
    }

    public function test_112_travel_pass_points_redemption_and_balance_deduction(): void
    {
        $action = app(RedeemGlobalLoyaltyTravelPassAcrossPropertiesAction::class);

        // First earn 20000
        $action->handle(userId: 992, originatingLine: 'HOTEL', ptsToEarn: 20000);

        // Redeem 8000
        $result = $action->handle(
            userId: 992,
            originatingLine: 'VENUE',
            ptsToEarn: 0,
            redeemingLine: 'VENUE',
            ptsToRedeem: 8000
        );

        $this->assertEquals(12000, $result['pts_balance']);
        $this->assertEquals('REDEEM', $result['entry']->transaction_type);
        $this->assertEquals(800000, $result['entry']->liability_value_idr);
    }

    public function test_112_insufficient_points_redemption_throws_exception(): void
    {
        $action = app(RedeemGlobalLoyaltyTravelPassAcrossPropertiesAction::class);

        $action->handle(userId: 993, originatingLine: 'HOTEL', ptsToEarn: 1000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient points');

        $action->handle(
            userId: 993,
            ptsToRedeem: 5000
        );
    }

    public function test_112_dynamic_award_pricing_respects_115_percent_floor(): void
    {
        $action = app(RedeemGlobalLoyaltyTravelPassAcrossPropertiesAction::class);

        $result = $action->handle(
            userId: 994,
            propertyId: $this->property->id,
            occupancyPercent: 85.0,
            baseRoomCostIdr: 2000000
        );

        $this->assertNotNull($result['award_pricing']);
        $this->assertEquals(23000, $result['award_pricing']->minimum_floor_pts);
        $this->assertGreaterThanOrEqual(23000, $result['award_pricing']->dynamic_award_pts);
    }

    public function test_113_travel_bundle_escrow_deposit_and_final_settlement(): void
    {
        $action = app(ComposeMultiModalTravelItineraryWithSlaGuaranteeAction::class);

        // Book bundle
        $bookResult = $action->handle(
            userId: 995,
            destinationCity: 'BALI',
            flightShareIdr: 4000000,
            hotelShareIdr: 8000000,
            transportShareIdr: 2000000,
            eventShareIdr: 1000000
        );

        $this->assertEquals(15000000, $bookResult['total_price_idr']);
        $this->assertEquals('ESCROWED', $bookResult['status']);

        // Settle bundle
        $settleResult = $action->handle(
            userId: 995,
            destinationCity: 'BALI',
            flightShareIdr: 4000000,
            hotelShareIdr: 8000000,
            transportShareIdr: 2000000,
            eventShareIdr: 1000000,
            settleBundleId: $bookResult['bundle']->id
        );

        $this->assertEquals('SETTLED', $settleResult['status']);
        $this->assertEquals('SETTLED', $settleResult['bundle']->status);
    }

    public function test_113_corporate_travel_policy_budget_breach_rejection(): void
    {
        $action = app(ComposeMultiModalTravelItineraryWithSlaGuaranteeAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Corporate travel policy breach');

        $action->handle(
            userId: 996,
            destinationCity: 'SINGAPORE',
            flightShareIdr: 10000000,
            hotelShareIdr: 15000000,
            transportShareIdr: 3000000,
            eventShareIdr: 2000000,
            corporateEmployeeGrade: 'ASSOCIATE',
            corporatePolicyBudgetLimitIdr: 15000000
        );
    }

    public function test_114_mice_contract_h30_room_block_release_and_beo_margin(): void
    {
        $action = app(ContractGlobalMiceAndWeddingEventEscrowAction::class);

        $result = $action->handle(
            propertyId: $this->property->id,
            eventType: 'CONFERENCE',
            clientName: 'Global Tech Summit 2026',
            eventDate: '2026-12-15',
            contractTotalValueIdr: 350000000,
            allottedRooms: 80,
            currentDate: '2026-11-20', // Past H-30 release deadline (2026-11-15)
            fnbMaterialsCostIdr: 80000000,
            avVendorCostIdr: 30000000,
            decorFloristCostIdr: 20000000,
            paxCount: 300
        );

        $this->assertEquals(80, $result['released_rooms']);
        $this->assertEquals('RELEASED', $result['room_block']->status);
        $this->assertNotNull($result['banquet_sheet']);
        $this->assertEquals(130000000, $result['contract']->actual_banquet_cost_idr);
        $this->assertEquals(220000000, $result['event_gross_margin_idr']);
    }
}
