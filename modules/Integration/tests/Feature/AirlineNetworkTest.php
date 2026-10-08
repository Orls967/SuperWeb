<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\AirlineNetworkService;
use Tests\TestCase;

/**
 * Fase 178 — Airline Network, Loyalty & Revenue Management Tests
 *
 * Covers:
 *  (a) dynamic fare quoting respects floor price guardrails
 *  (b) loyalty miles duplicate earning across codeshare prevented
 *  (c) disruption passenger compensation pool calculated
 *  (d) avi:audit = 0 discrepancy
 */
class AirlineNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected AirlineNetworkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AirlineNetworkService::class);
    }

    /**
     * (a) Dynamic yield quoting respects minimum price floor guardrail.
     */
    public function test_fare_quote_respects_floor_guardrails(): void
    {
        // Low season demand (multiplier 0.6) on Rp 1,000,000 fare = Rp 600,000, but floor is Rp 750,000
        $quote = $this->service->quoteDynamicFare('CGK-DPS', 1000000.0, 0.6, 750000.0);

        $this->assertEquals(750000.00, (float) $quote->quoted_fare_idr);
        $this->assertTrue((bool) $quote->floor_price_respected);
    }

    /**
     * (b) Loyalty miles earning deduplicated by codeshare key.
     */
    public function test_loyalty_miles_duplicate_earning_prevention(): void
    {
        $key = 'CODESHARE-SQ-GA-FLIGHT-20261008-001';

        // 1. First credit of 1,200 miles
        $credit1 = $this->service->creditLoyaltyMiles($key, 5001, 'GA-820', 1200);
        $this->assertSame(1200, (int) $credit1->miles_earned);
        $this->assertEquals(180000.00, (float) $credit1->liability_value_idr);

        // 2. Duplicate submission with same key
        $credit2 = $this->service->creditLoyaltyMiles($key, 5001, 'GA-820', 1200);
        $this->assertSame($credit1->earning_key, $credit2->earning_key);

        // Verify only 1 entry in DB
        $count = DB::table('avi_loyalty_miles_ledgers')->count();
        $this->assertSame(1, $count);
    }

    /**
     * (c) Disruption passenger compensation pool calculated accurately.
     */
    public function test_flight_disruption_compensation_pool(): void
    {
        // 150 passengers delayed due to mechanical fault @ Rp 300,000 = Rp 45,000,000 pool
        $disruption = $this->service->logFlightDisruption('GA-404', 'MECHANICAL', 150, 300000.0);

        $this->assertSame(150, (int) $disruption->impacted_pax_count);
        $this->assertEquals(45000000.00, (float) $disruption->total_compensation_pool_idr);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_airline_network_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
