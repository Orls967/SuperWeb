<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hotel\Application\Services\TravelPlatformAndItineraryService;
use RuntimeException;
use Tests\TestCase;

class TravelPlatformAndItineraryTest extends TestCase
{
    use RefreshDatabase;

    protected TravelPlatformAndItineraryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TravelPlatformAndItineraryService::class);

        // Register travel ledger accounts
        $accounts = [
            'htl:travel_escrow_deposit:IDR' => 'asset',
            'htl:travel_unearned_escrow:IDR' => 'liability',
            'air:flight_partner_payable:IDR' => 'liability',
            'htl:hotel_vendor_payable:IDR' => 'liability',
            'lgx:transport_vendor_payable:IDR' => 'liability',
            'ven:event_vendor_payable:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Travel {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_113_1_and_113_6_a_bundle_settlement_sum_equals_total_payment(): void
    {
        // Flight (4m) + Hotel (6m) + Transport (2m) + Event (3m) = 15,000,000 IDR Total
        $bundle = $this->service->bookTravelBundle(
            userId: 501,
            city: 'Denpasar, Bali',
            flightShareIdr: 4_000_000,
            hotelShareIdr: 6_000_000,
            transportShareIdr: 2_000_000,
            eventShareIdr: 3_000_000
        );

        $this->assertEquals(15_000_000, $bundle->total_price_idr);
        $this->assertEquals('ESCROWED', $bundle->status);

        $dep = LedgerAccount::where('code', 'htl:travel_escrow_deposit:IDR')->first();
        $this->assertEquals('15000000', (string) $dep->cached_balance);

        // Add 2 itinerary items
        $this->service->addItineraryItem($bundle, 1, 'Check-in & Welcome Dinner', 'HOTEL');
        $this->service->addItineraryItem($bundle, 2, 'Sunset Beach Festival VIP', 'VENUE');

        $this->assertCount(2, $bundle->itineraries);

        // Settle bundle
        $settled = $this->service->settleTravelBundle($bundle);
        $this->assertEquals('SETTLED', $settled->status);

        // Verify vendor liabilities
        $flight = LedgerAccount::where('code', 'air:flight_partner_payable:IDR')->first();
        $hotel = LedgerAccount::where('code', 'htl:hotel_vendor_payable:IDR')->first();
        $transport = LedgerAccount::where('code', 'lgx:transport_vendor_payable:IDR')->first();
        $event = LedgerAccount::where('code', 'ven:event_vendor_payable:IDR')->first();

        $this->assertEquals('-4000000', (string) $flight->cached_balance);
        $this->assertEquals('-6000000', (string) $hotel->cached_balance);
        $this->assertEquals('-2000000', (string) $transport->cached_balance);
        $this->assertEquals('-3000000', (string) $event->cached_balance);
    }

    public function test_113_4_and_113_6_c_corporate_travel_exceeding_policy_is_rejected(): void
    {
        // 1. Within policy limit -> approved
        $approved = $this->service->submitCorporateTravel(
            corporatePartyId: 'CORP-ASTRA-01',
            employeeId: 1002,
            grade: 'MANAGER',
            requestedAmountIdr: 7_500_000,
            policyBudgetLimitIdr: 10_000_000
        );
        $this->assertEquals('APPROVED', $approved->status);

        // 2. Exceeding policy limit -> rejected
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds policy budget limit');

        $this->service->submitCorporateTravel(
            corporatePartyId: 'CORP-ASTRA-01',
            employeeId: 1003,
            grade: 'STAFF',
            requestedAmountIdr: 12_000_000,
            policyBudgetLimitIdr: 5_000_000
        );
    }
}
