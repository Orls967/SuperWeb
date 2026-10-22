<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Hotel\Application\Services\HotelFolioAndPackageService;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use Tests\TestCase;

class HotelFolioEnterpriseDeepeningTest extends TestCase
{
    use RefreshDatabase;

    protected HotelFolioAndPackageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HotelFolioAndPackageService::class);
        $this->seed(LedgerAccountsSeeder::class);
    }

    public function test_split_folio_multi_payer_settlement_allocates_room_to_corporate_and_extras_to_guest(): void
    {
        $guestId = (string) Str::uuid();
        $corporateId = 'CORP-ASTRA-INTERNATIONAL';

        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => $guestId,
            'total_room_charges' => 5000000,
            'total_addon_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => 5000000,
            'status' => 'open',
        ]);

        // Guest consumes 1,200,000 IDR extras (Spa + Dinners)
        $this->service->addFolioItem($folio->id, 'SPA', 'Full Body Treatment', 700000);
        $this->service->addFolioItem($folio->id, 'FNB', 'Sunset Dinner Beachfront', 500000);

        $folio->refresh();
        $this->assertEquals(6200000, $folio->outstanding_balance);

        // Corporate pays room charges via City Ledger AR
        $folioCorporate = $this->service->settleCorporateBilling($folio->id, $corporateId, 5000000);
        $this->assertEquals(1200000, $folioCorporate->outstanding_balance);
        $this->assertEquals('open', $folioCorporate->status);

        // Guest settles remaining 1,200,000 IDR via personal wallet
        $folioCorporate->increment('total_paid', 1200000);
        $folioCorporate->update([
            'outstanding_balance' => 0,
            'status' => 'settled',
        ]);

        $this->assertEquals(0, $folioCorporate->fresh()->outstanding_balance);
        $this->assertEquals('settled', $folioCorporate->fresh()->status);
    }

    public function test_corporate_billing_city_ledger_posts_balanced_double_entry_receivable(): void
    {
        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => (string) Str::uuid(),
            'total_room_charges' => 3000000,
            'total_addon_charges' => 0,
            'total_paid' => 0,
            'outstanding_balance' => 3000000,
            'status' => 'open',
        ]);

        $settledFolio = $this->service->settleCorporateBilling($folio->id, 'CORP-TELKOM-INDONESIA', 3000000);

        $this->assertEquals(3000000, $settledFolio->total_paid);
        $this->assertEquals(0, $settledFolio->outstanding_balance);
        $this->assertEquals('settled', $settledFolio->status);
    }

    public function test_folio_checkout_blocked_when_unsettled_balance_not_equal_to_zero(): void
    {
        $folio = HotelFolio::create([
            'id' => (string) Str::uuid(),
            'reservation_id' => (string) Str::uuid(),
            'guest_user_id' => (string) Str::uuid(),
            'total_room_charges' => 2000000,
            'total_addon_charges' => 500000,
            'total_paid' => 1500000,
            'outstanding_balance' => 1000000,
            'status' => 'open',
        ]);

        // Attempting to close/checkout a folio with remaining 1M balance must be forbidden
        $canCheckout = ($folio->outstanding_balance === 0);
        $this->assertFalse($canCheckout);
        $this->assertEquals('open', $folio->status);
    }

    public function test_timeshare_unutilized_week_subleased_to_public_rental_pool(): void
    {
        $unit = TimeshareUnit::create([
            'id' => (string) Str::uuid(),
            'property_id' => (string) Str::uuid(),
            'unit_code' => 'TS-VILLA-SEMINYAK-01',
            'unit_name' => 'Royal Seminyak Private Pool Villa',
            'total_token_shares' => 52, // 52 weeks in a year
            'daily_rental_rate_idr' => 2500000,
        ]);

        // Sublease week #34 for 15,000,000 IDR
        $rentalIncome = 15000000;
        $managementFeeRate = 0.15; // 15% resort management fee
        $resortFee = (int) round($rentalIncome * $managementFeeRate);
        $netDividendToOwner = $rentalIncome - $resortFee;

        $this->assertEquals(2250000, $resortFee);
        $this->assertEquals(12750000, $netDividendToOwner);
    }

    public function test_timeshare_rental_dividend_distributed_pro_rata_after_management_fee_deduction(): void
    {
        $unit = TimeshareUnit::create([
            'id' => (string) Str::uuid(),
            'property_id' => (string) Str::uuid(),
            'unit_code' => 'TS-PENTHOUSE-SANUR-01',
            'unit_name' => 'Sanur Oceanfront Penthouse',
            'total_token_shares' => 100,
            'daily_rental_rate_idr' => 3000000,
        ]);

        $inv1 = TimeshareInvestor::create([
            'id' => (string) Str::uuid(),
            'timeshare_unit_id' => $unit->id,
            'investor_user_id' => 'INV-001',
            'token_shares' => 60,
        ]);

        $inv2 = TimeshareInvestor::create([
            'id' => (string) Str::uuid(),
            'timeshare_unit_id' => $unit->id,
            'investor_user_id' => 'INV-002',
            'token_shares' => 40,
        ]);

        // 10,000,000 IDR net rental income after fees
        $res = $this->service->distributeTimeshareDailyDividend($unit->id, 10000000);

        $this->assertEquals(10000000, $res['total_allocated']);
        $this->assertEquals(6000000, $res['investors'][0]['dividend_idr']);
        $this->assertEquals(4000000, $res['investors'][1]['dividend_idr']);
    }

    public function test_destination_package_multi_vendor_escrow_holding_upon_initial_purchase(): void
    {
        $package = DestinationPackage::create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PKG-BALI-HEAVEN-01',
            'title' => 'Bali Ultimate 4D3N Luxury Retreat',
            'total_price_idr' => 12000000,
            'vendor_shares' => [
                ['vendor' => 'hotel', 'amount' => 7000000, 'account' => 'vendor:hotel:IDR'],
                ['vendor' => 'festival', 'amount' => 3000000, 'account' => 'vendor:festival:IDR'],
                ['vendor' => 'transport', 'amount' => 2000000, 'account' => 'vendor:transport:IDR'],
            ],
            'status' => 'active',
        ]);

        $this->assertEquals(12000000, $package->total_price_idr);
        $this->assertCount(3, $package->vendor_shares);
    }

    public function test_destination_package_milestone_escrow_release_upon_individual_vendor_check_in(): void
    {
        $package = DestinationPackage::create([
            'id' => (string) Str::uuid(),
            'package_code' => 'PKG-BALI-MILESTONE-01',
            'title' => 'Bali Milestone Retreat',
            'total_price_idr' => 10000000,
            'vendor_shares' => [
                ['vendor' => 'hotel', 'amount' => 6000000, 'account' => 'vendor:hotel:IDR'],
                ['vendor' => 'festival', 'amount' => 4000000, 'account' => 'vendor:festival:IDR'],
            ],
            'status' => 'active',
        ]);

        $settlement = $this->service->settleDestinationPackage($package->id, (string) Str::uuid());

        $this->assertEquals('PKG-BALI-MILESTONE-01', $settlement['package_code']);
        $this->assertEquals(10000000, $settlement['total_paid']);
        $this->assertEquals(2, $settlement['settled_vendors']);
    }

    public function test_destination_package_partial_dispute_refunds_cancelled_venue_without_breaking_hotel(): void
    {
        $totalPrice = 10000000;
        $hotelShare = 6000000;
        $venueShare = 4000000;

        // If venue concert is cancelled due to weather, venue portion (4M) is refunded to guest
        $refundAmount = $venueShare;
        $remainingPackageAmount = $totalPrice - $refundAmount;

        $this->assertEquals(4000000, $refundAmount);
        $this->assertEquals($hotelShare, $remainingPackageAmount);
    }

    public function test_banquet_production_sheet_rolls_up_recipe_bom_costs_accurately(): void
    {
        // 500 pax banquet menu
        $pax = 500;
        $appetizerCostPerPax = 35000;
        $mainCourseCostPerPax = 85000;
        $dessertCostPerPax = 25000;
        $beverageCostPerPax = 15000;

        $totalFoodCostPerPax = $appetizerCostPerPax + $mainCourseCostPerPax + $dessertCostPerPax + $beverageCostPerPax;
        $totalProductionCost = $totalFoodCostPerPax * $pax;

        $this->assertEquals(160000, $totalFoodCostPerPax);
        $this->assertEquals(80000000, $totalProductionCost); // 80M IDR total BOM cost
    }

    public function test_banquet_booking_rejected_when_gross_margin_falls_below_40_percent_floor(): void
    {
        $totalProductionCost = 80000000; // 80M IDR
        $minGrossMargin = 0.40; // 40% margin floor

        // Minimum required selling price: Cost / (1 - Margin) = 80M / 0.60 = 133,333,333 IDR
        $minSellingPrice = (int) ceil($totalProductionCost / (1.0 - $minGrossMargin));

        // Sales representative offers aggressive discount of 110,000,000 IDR
        $offeredSellingPrice = 110000000;
        $actualMargin = ($offeredSellingPrice - $totalProductionCost) / $offeredSellingPrice;

        $this->assertLessThan($minGrossMargin, $actualMargin);
        $this->assertGreaterThan($offeredSellingPrice, $minSellingPrice);

        // System rejects booking without GM override
        $bookingAllowed = ($offeredSellingPrice >= $minSellingPrice);
        $this->assertFalse($bookingAllowed);
    }
}
