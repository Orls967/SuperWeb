<?php

declare(strict_types=1);

namespace Tests\Acceptance\HotelLoyalty;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hotel\Application\Actions\AccrueCrossEcosystemPointsAction;
use Modules\Hotel\Application\Actions\GeneratePersonalizedTravelRecommendationsAction;
use Modules\Hotel\Application\Actions\IssueGlobalTravelPassAction;
use Modules\Hotel\Application\Actions\RecognizeLoyaltyBreakageRevenueAction;
use Modules\Hotel\Application\Actions\RedeemAwardNightWithDynamicPricingAction;
use Modules\Hotel\Application\Actions\TransferPointsToAlliancePartnerAction;
use Modules\Hotel\Domain\Models\HtlAlliancePartner;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Tests\TestCase;

class HotelGlobalLoyaltyAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * 01: RBAC Access Control & Route Authorization
     */
    public function test_01_access_control_and_route_authorization_global_loyalty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guest = User::factory()->create(['role' => 'guest']);
        $finance = User::factory()->create(['role' => 'finance']);
        $unauthorized = User::factory()->create(['role' => 'unverified']);

        // Dashboard accessible by admin & guest
        $this->actingAs($admin)->get('/hotel/travel-pass/dashboard')->assertOk();
        $this->actingAs($guest)->get('/hotel/travel-pass/dashboard')->assertOk();

        // Breakage audit accessible by finance, forbidden for guest
        $this->actingAs($finance)->get('/hotel/travel-pass/breakage-audit')->assertOk();
        $this->actingAs($guest)->get('/hotel/travel-pass/breakage-audit')->assertForbidden();

        // Guest denied access to personalization config
        $this->actingAs($unauthorized)->get('/hotel/travel-pass/dashboard')->assertForbidden();
    }

    /**
     * 02: Travel Pass Registration & Tier Qualification Lifecycle
     */
    public function test_02_travel_pass_registration_and_tier_qualification_lifecycle(): void
    {
        $user = User::factory()->create(['role' => 'guest']);
        $action = app(IssueGlobalTravelPassAction::class);

        $pass = $action->execute(
            userReference: (string) $user->id,
            initialTier: 'SILVER'
        );

        $this->assertInstanceOf(HtlTravelPass::class, $pass);
        $this->assertStringStartsWith('TP-', $pass->pass_number);
        $this->assertSame('SILVER', $pass->tier);
        $this->assertSame(0, (int) $pass->pts_balance);

        // Simulate rolling 365 nights and spend to qualify for PLATINUM
        $pass->update([
            'cumulative_nights' => 45,
            'cumulative_spend_idr' => 120000000,
        ]);

        $pass->refreshTier();
        $this->assertSame('PLATINUM', $pass->tier);
    }

    /**
     * 03: Cross-Ecosystem Points Accrual, FIFO Deduction & Breakage Recognition
     */
    public function test_03_cross_ecosystem_points_accrual_fifo_deduction_and_breakage(): void
    {
        $user = User::factory()->create(['role' => 'guest']);
        $issueAction = app(IssueGlobalTravelPassAction::class);
        $accrueAction = app(AccrueCrossEcosystemPointsAction::class);
        $breakageAction = app(RecognizeLoyaltyBreakageRevenueAction::class);

        $pass = $issueAction->execute(userReference: (string) $user->id);

        // 1. Accrue points from Hotel room stay
        $batch1 = $accrueAction->execute(
            travelPassId: $pass->id,
            originatingLine: 'HTL_ROOM',
            ptsAmount: 5000,
            referenceCode: 'STAY-INV-9901'
        );

        $this->assertSame(5000, (int) $pass->fresh()->pts_balance);

        // 2. Accrue points from Venue Concert ticket
        $batch2 = $accrueAction->execute(
            travelPassId: $pass->id,
            originatingLine: 'VEN_TICKET',
            ptsAmount: 7000,
            referenceCode: 'VEN-FEST-2026'
        );

        $this->assertSame(12000, (int) $pass->fresh()->pts_balance);

        // 3. Force expire batch 1 to test breakage recognition
        $batch1->update([
            'expires_at' => now()->subDay(),
            'state' => 'EXPIRED',
        ]);

        $breakageResult = $breakageAction->execute(travelPassId: $pass->id);

        $this->assertSame(5000, $breakageResult->totalExpiredPts);
        $this->assertSame(500000, $breakageResult->breakageRevenueIdr); // 5,000 * 100 IDR
        $this->assertSame(7000, (int) $pass->fresh()->pts_balance); // Remaining points
    }

    /**
     * 04: Alliance Partner Point Transfer Saga & Compensation Rollback
     */
    public function test_04_alliance_partner_point_transfer_saga_and_compensation_rollback(): void
    {
        $user = User::factory()->create(['role' => 'guest']);
        $pass = app(IssueGlobalTravelPassAction::class)->execute(userReference: (string) $user->id);
        app(AccrueCrossEcosystemPointsAction::class)->execute(
            travelPassId: $pass->id,
            originatingLine: 'HTL_ROOM',
            ptsAmount: 20000,
            referenceCode: 'STAY-BALI-101'
        );

        $partner = HtlAlliancePartner::create([
            'partner_code' => 'GARUDA_AIR',
            'partner_name' => 'Garuda Indonesia Airlines',
            'category' => 'AIRLINE',
            'base_exchange_ratio' => 10.0,
            'transfer_fee_percentage' => 0.035,
            'min_points_transfer' => 1000,
            'max_points_transfer_daily' => 50000,
            'is_active' => true,
        ]);

        $transferAction = app(TransferPointsToAlliancePartnerAction::class);

        // Skenario Sukses: Transfer 10,000 PTS -> 1,000 Miles
        $successResult = $transferAction->execute(
            travelPassId: $pass->id,
            partnerCode: 'GARUDA_AIR',
            memberPartnerAccount: 'GA-8829102',
            pointsToTransfer: 10000,
            forceFail: false
        );

        $this->assertSame('SUCCESS', $successResult->status);
        $this->assertSame(1000, $successResult->partnerUnitsCredited);
        $this->assertSame(10000, (int) $pass->fresh()->pts_balance); // 20,000 - 10,000 = 10,000

        // Skenario Gagal & Kompensasi: Transfer 5,000 PTS dengan API timeout -> Poin di-rollback
        $failResult = $transferAction->execute(
            travelPassId: $pass->id,
            partnerCode: 'GARUDA_AIR',
            memberPartnerAccount: 'GA-8829102',
            pointsToTransfer: 5000,
            forceFail: true
        );

        $this->assertSame('COMPENSATED', $failResult->status);
        // Saldo poin tetap 10,000 karena kompensasi berhasil mengembalikan dana
        $this->assertSame(10000, (int) $pass->fresh()->pts_balance);
    }

    /**
     * 05: Dynamic Award Night Pricing with Floor Protection & Personalization
     */
    public function test_05_dynamic_award_night_pricing_with_floor_protection_and_personalization(): void
    {
        $user = User::factory()->create(['role' => 'guest']);
        $pass = app(IssueGlobalTravelPassAction::class)->execute(
            userReference: (string) $user->id,
            initialTier: 'GOLD'
        );

        app(AccrueCrossEcosystemPointsAction::class)->execute(
            travelPassId: $pass->id,
            originatingLine: 'HTL_ROOM',
            ptsAmount: 50000,
            referenceCode: 'HOTEL-DEPOSIT-881'
        );

        $awardAction = app(RedeemAwardNightWithDynamicPricingAction::class);
        $recommendAction = app(GeneratePersonalizedTravelRecommendationsAction::class);

        // Redeem 1 Award Night with 85% Occupancy
        $booking = $awardAction->execute(
            travelPassId: $pass->id,
            propertyReference: 'PROP-BALI-01',
            roomTypeReference: 'DELUXE_OCEAN',
            stayDate: now()->addDays(14)->toDateString(),
            currentOccupancyRate: 0.85,
            marginalCostIdr: 300000
        );

        $this->assertNotNull($booking);
        $this->assertGreaterThan(0, $booking->pointsRedeemed);
        $this->assertGreaterThanOrEqual(345000, $booking->propertyReimbursementIdr); // Floor 115%

        // Personalization recommendations generated
        $recommendations = $recommendAction->execute(travelPassId: $pass->id);
        $this->assertNotEmpty($recommendations);
        $this->assertArrayHasKey('recommended_packages', $recommendations);
    }
}
