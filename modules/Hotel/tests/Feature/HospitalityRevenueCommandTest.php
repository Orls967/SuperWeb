<?php

declare(strict_types=1);

namespace Modules\Hotel\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Hotel\Application\Services\HospitalityRevenueCommandService;
use Modules\Hotel\Domain\Models\HotelProperty;
use Tests\TestCase;

class HospitalityRevenueCommandTest extends TestCase
{
    use RefreshDatabase;

    protected HospitalityRevenueCommandService $service;

    protected HotelProperty $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HospitalityRevenueCommandService::class);

        $this->property = HotelProperty::create([
            'id' => (string) Str::uuid(),
            'property_code' => 'HTL-SYND-01',
            'name' => 'Villa Cendana Ubud Syndicate',
            'property_type' => 'RESORT',
            'city' => 'Gianyar',
            'total_rooms' => 20,
        ]);

        // Register syndication ledger accounts
        $accounts = [
            'htl:syndication_yield_clearing:IDR' => 'asset',
            'htl:investor_token_payable:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Hotel {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_118_1_and_118_5_a_revpar_consistent_with_adr_and_occupancy(): void
    {
        // ADR: 2,000,000 IDR, Occupancy: 85% -> RevPAR = 2,000,000 * 0.85 = 1,700,000 IDR
        $metric = $this->service->computeRegionalRevenueMetric(
            regionCity: 'Bali-Denpasar',
            year: 2026,
            month: 10,
            adrIdr: 2_000_000,
            occupancyPercent: 85.0,
            venueTicketGmvIdr: 5_000_000_000,
            travelBundleGmvIdr: 12_000_000_000
        );

        $this->assertEquals(1_700_000, $metric->revpar_idr);
        $this->assertEquals(85.0, (float) $metric->occupancy_percentage);
        $this->assertEquals(5_000_000_000, $metric->total_venue_ticket_gmv_idr);
    }

    public function test_118_3_and_118_5_c_syndication_token_rental_yield_distribution(): void
    {
        // 10% ownership of 300,000,000 IDR monthly net yield = 30,000,000 IDR distributed to investor
        $dist = $this->service->distributeSyndicationYield(
            property: $this->property,
            investorUserId: 909,
            tokensHeld: 1000,
            ownershipPercent: 10.0,
            netMonthlyPropertyYieldIdr: 300_000_000
        );

        $this->assertEquals(30_000_000, $dist->distributed_yield_idr);

        $payable = LedgerAccount::where('code', 'htl:investor_token_payable:IDR')->first();
        $this->assertEquals('-30000000', (string) $payable->cached_balance);
    }
}
