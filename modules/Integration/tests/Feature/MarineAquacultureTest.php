<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\MarineAquacultureService;
use Tests\TestCase;

/**
 * Fase 170 — Perikanan, Aquaculture & Marine Supply Chain Tests
 *
 * Covers:
 *  (a) biomass conservation: harvest cannot exceed available cohort biomass
 *  (b) cold-chain breach (>4°C) automatically quarantines harvest lot
 *  (c) sustainable zone harvest quota enforced
 *  (d) marine:audit = 0 discrepancy
 */
class MarineAquacultureTest extends TestCase
{
    use RefreshDatabase;

    protected MarineAquacultureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MarineAquacultureService::class);
    }

    /**
     * (a) Biomass conservation limit: cannot harvest more than cohort total.
     */
    public function test_biomass_conservation_limit(): void
    {
        $cohort = $this->service->createCohort('SHRIMP_VANNAMEI', 5000.0); // 5,000 kg

        // Valid partial harvest of 2,000 kg
        $h1 = $this->service->recordHarvest($cohort->cohort_code, 'ZONE-MALUKU', 2000.0, -20.0);
        $this->assertSame('CLEARED', $h1->status);

        // Attempting to harvest 4,000 kg (remaining is only 3,000 kg) -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->recordHarvest($cohort->cohort_code, 'ZONE-MALUKU', 4000.0, -20.0);
    }

    /**
     * (b) Cold-chain temperature violation (>4°C) quarantines seafood lot.
     */
    public function test_cold_chain_breach_quarantine(): void
    {
        $cohort = $this->service->createCohort('TUNA', 10000.0);

        // Breached: temp 8.5°C -> QUARANTINED
        $badLot = $this->service->recordHarvest($cohort->cohort_code, 'ZONE-BANDA', 1500.0, 8.5);
        $this->assertTrue((bool) $badLot->cold_chain_breached);
        $this->assertSame('QUARANTINED', $badLot->status);

        // Safe fresh catch: 2.0°C -> CLEARED
        $goodLot = $this->service->recordHarvest($cohort->cohort_code, 'ZONE-BANDA', 1500.0, 2.0);
        $this->assertFalse((bool) $goodLot->cold_chain_breached);
        $this->assertSame('CLEARED', $goodLot->status);
    }

    /**
     * (c) Sustainable fishing zone quota limits total catch.
     */
    public function test_sustainable_zone_quota_enforcement(): void
    {
        $this->service->setZoneQuota('ZONE-NATUNA', 1000.0); // 1,000 kg cap
        $cohort = $this->service->createCohort('TILAPIA', 5000.0);

        // Harvest 800 kg -> OK
        $this->service->recordHarvest($cohort->cohort_code, 'ZONE-NATUNA', 800.0, 1.0);

        // Attempt to harvest 300 kg (total 1,100 kg > 1,000 kg) -> Exception
        $this->expectException(\RuntimeException::class);
        $this->service->recordHarvest($cohort->cohort_code, 'ZONE-NATUNA', 300.0, 1.0);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_marine_aquaculture_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
