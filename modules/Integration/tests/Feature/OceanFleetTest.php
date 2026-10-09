<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\OceanFleetService;
use Tests\TestCase;

/**
 * Fase 180 — Ocean Fleet, Ship Management & Marine Services Tests
 *
 * Covers:
 *  (a) invalid voyage cargo capacity rejected (exceeds DWT)
 *  (b) overdue safety certificate blocks voyage dispatch
 *  (c) charter party laytime & demurrage settlement follows terms
 *  (d) digital vessel passport cryptographic hash integrity
 *  (e) marinefleet:audit = 0 discrepancy
 */
class OceanFleetTest extends TestCase
{
    use RefreshDatabase;

    protected OceanFleetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OceanFleetService::class);
    }

    /**
     * (a) Voyage planning enforces DWT capacity constraint.
     */
    public function test_voyage_cargo_dwt_capacity(): void
    {
        $vessel = $this->service->registerVessel('IMO9876543', 'Samudera Nusantara', 'CONTAINER', 50000.0, Carbon::now()->addYear());

        // Valid voyage: 40,000 tons cargo + 2,000 tons bunker fuel = 42,000 tons (<= 50,000 DWT)
        $voyage = $this->service->planVoyage($vessel->imo_number, 'IDJKT', 'SGSIN', 40000.0, 2000.0);
        $this->assertSame('SCHEDULED', $voyage->status);

        // Over-capacity voyage: 49,000 tons cargo + 3,000 tons bunker fuel = 52,000 tons (> 50,000 DWT) -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->planVoyage($vessel->imo_number, 'IDJKT', 'SGSIN', 49000.0, 3000.0);
    }

    /**
     * (b) Overdue safety certificate blocks voyage dispatch.
     */
    public function test_overdue_safety_certificate_blocks_dispatch(): void
    {
        // Expired 15 days ago
        $expiredVessel = $this->service->registerVessel('IMO1122334', 'Pacific Explorer', 'BULKER', 30000.0, Carbon::now()->subDays(15));

        $this->expectException(\RuntimeException::class);
        $this->service->planVoyage($expiredVessel->imo_number, 'IDSUB', 'MYPKG', 20000.0, 1000.0);
    }

    /**
     * (c) Charter settlement calculates daily hire and demurrage correctly.
     */
    public function test_charter_party_settlement(): void
    {
        // 10 days voyage @ $25,000/day + $15,000 demurrage = $265,000 total
        $settlement = $this->service->settleCharter('VOY-001', 25000.0, 10.0, 15000.0);

        $this->assertEquals(265000.00, (float) $settlement->total_charter_settlement_usd);
    }

    /**
     * (d) Digital vessel passport cryptographic hash integrity.
     */
    public function test_vessel_passport_hash(): void
    {
        $vessel = $this->service->registerVessel('IMO5566778', 'Meratus Pioneer', 'CONTAINER', 20000.0, Carbon::now()->addMonths(8));

        $this->assertNotNull($vessel->passport_hash);
        $this->assertSame(64, strlen($vessel->passport_hash));
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_ocean_fleet_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
