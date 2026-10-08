<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AviationService;
use Tests\TestCase;

/**
 * Fase 177 — Aviation, Airport Services & Air Cargo Tests
 *
 * Covers:
 *  (a) slot and aircraft capacity enforced
 *  (b) expired airworthiness maintenance certificate blocks flight
 *  (c) cargo custody complete and dangerous goods validated
 *  (d) refund idempotent
 *  (e) avi:audit = 0 discrepancy
 */
class AviationTest extends TestCase
{
    use RefreshDatabase;

    protected AviationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AviationService::class);
    }

    /**
     * (a) Aircraft capacity limit enforced.
     */
    public function test_aircraft_seating_capacity_limit(): void
    {
        $plane = $this->service->registerAircraft('PK-GIA-01', 'Boeing 737-800', 180, 5000.0, Carbon::now()->addYear());

        // Book 100 seats -> OK
        $b1 = $this->service->bookFlight($plane->tail_number, 'GA-501', 100, 150000000.0);
        $this->assertSame('CONFIRMED', $b1->status);

        // Attempt to book 90 more seats (total 190 > 180 cap) -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->bookFlight($plane->tail_number, 'GA-501', 90, 135000000.0);
    }

    /**
     * (b) Expired airworthiness blocks flight dispatch.
     */
    public function test_expired_airworthiness_blocks_flight(): void
    {
        // Expired 10 days ago
        $expiredPlane = $this->service->registerAircraft('PK-LNI-02', 'Airbus A320', 150, 4000.0, Carbon::now()->subDays(10));

        $this->expectException(\RuntimeException::class);
        $this->service->bookFlight($expiredPlane->tail_number, 'JT-202', 2, 3000000.0);
    }

    /**
     * (c) Air cargo dangerous goods certification and custody hash.
     */
    public function test_air_cargo_custody_and_dg_safety(): void
    {
        // Dangerous goods without IATA certification -> Exception
        try {
            $this->service->manifestCargo('PK-GIA-01', 500.0, true, false);
            $this->fail('Expected exception for uncertified dangerous goods cargo.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Dangerous goods cargo must carry valid IATA DG certification', $e->getMessage());
        }

        // Certified dangerous goods -> SUCCESS
        $manifest = $this->service->manifestCargo('PK-GIA-01', 500.0, true, true);
        $this->assertNotNull($manifest->custody_hash);
    }

    /**
     * (d) Flight booking refund idempotent.
     */
    public function test_flight_refund_idempotent(): void
    {
        $plane = $this->service->registerAircraft('PK-SRI-03', 'ATR 72-600', 70, 2000.0, Carbon::now()->addMonths(6));
        $booking = $this->service->bookFlight($plane->tail_number, 'SJ-100', 1, 1200000.0);

        // First refund
        $r1 = $this->service->processRefund($booking->booking_code);
        $this->assertTrue((bool) $r1->is_refunded);
        $this->assertEquals(1200000.00, (float) $r1->refund_amount_idr);

        // Duplicate refund -> Idempotent
        $r2 = $this->service->processRefund($booking->booking_code);
        $this->assertSame($r1->booking_code, $r2->booking_code);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_aviation_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
