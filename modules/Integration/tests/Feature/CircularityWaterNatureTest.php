<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\CircularityWaterNatureService;
use Tests\TestCase;

/**
 * Fase 230 — Keberlanjutan: Circularity, Water Stress & Nature Positive Scale Tests
 *
 * Covers:
 *  (a) Mass balance physical conservation validation
 *  (b) Edge Case 230.7: Anti-greenwashing policy restricting green premium to verified lifecycle data
 *  (c) Edge Case 230.6: Water-stressed sites prioritized with accelerated capex approval
 *  (d) Nature-positive restoration portfolio metric calculation
 *  (e) Green procurement tender award incorporating sustainability score weighting
 *  (f) Quality audit esg:audit clean with 0 discrepancies
 */
class CircularityWaterNatureTest extends TestCase
{
    use RefreshDatabase;

    protected CircularityWaterNatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CircularityWaterNatureService::class);
    }

    /**
     * (a) Mass balance physical conservation check (230.1 & 230.5).
     */
    public function test_mass_balance_conservation_integrity(): void
    {
        // 1. Inconsistent mass balance: Virgin (60) + Recycled (30) = 90 != Input (100) -> Throws exception
        try {
            $this->service->recordMassBalance('FASHION', 100.0, 60.0, 30.0);
            $this->fail('Expected exception for mass balance violation.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Mass balance conservation failure', $e->getMessage());
        }

        // 2. Consistent mass balance: Virgin (40) + Recycled (60) = 100 -> Succeeded
        $balance = $this->service->recordMassBalance('FASHION', 100.0, 40.0, 60.0, 25.0, 15.0, false);
        $this->assertEquals(60.0, (float) $balance->recycled_content_pct);
        $this->assertFalse((bool) $balance->green_premium_eligible);
    }

    /**
     * (b) Edge Case 230.7: Anti-greenwashing green premium protection.
     */
    public function test_anti_greenwashing_green_premium_protection(): void
    {
        // Unverified product record
        $unverified = $this->service->recordMassBalance('FOOD', 500.0, 200.0, 300.0, 100.0, 50.0, false);

        // Attempting to claim green premium on unverified data -> Throws exception
        try {
            $this->service->claimGreenPremium($unverified->balance_code);
            $this->fail('Expected exception for claiming green premium on unverified data.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Anti-greenwashing violation', $e->getMessage());
        }

        // Verified product record -> Green premium allowed
        $verified = $this->service->recordMassBalance('FOOD', 500.0, 150.0, 350.0, 120.0, 60.0, true);
        $claimed = $this->service->claimGreenPremium($verified->balance_code);
        $this->assertTrue((bool) $claimed->green_premium_eligible);
    }

    /**
     * (c) Edge Case 230.6: Water-stressed area site capex prioritization.
     */
    public function test_water_stressed_site_capex_acceleration(): void
    {
        // Water-stressed site in mining region
        $site = $this->service->registerWaterSite(
            'SITE-MINE-01',
            'MINING',
            'Soroako Processing Plant',
            true, // Water-stressed
            50000.0,
            42000.0,
            18000.0,
            12000.0
        );

        $this->assertTrue((bool) $site->is_water_stressed_area);
        $this->assertTrue((bool) $site->capex_fast_tracked); // 230.6 Accelerated capex
    }

    /**
     * (d) Nature-positive restoration portfolio (230.3).
     */
    public function test_nature_positive_restoration_ratio(): void
    {
        $project = $this->service->registerNaturePositiveProject(
            'PRJ-MANGROVE-01',
            'ENT-FOREST-ID',
            'MANGROVE',
            1200.0, // Restored 1200 ha
            800.0,  // Operational footprint 800 ha
            true
        );

        $this->assertEquals(1.5, (float) $project->net_positive_ratio);
        $this->assertSame('VERIFIED', $project->verification_cycle_status);
    }

    /**
     * (e) Green procurement tender evaluation with sustainability score weighting (230.4 & 230.5).
     */
    public function test_green_procurement_tender_evaluation(): void
    {
        // Vendor with good commercial (80) and strong green score (90) -> Composite = 80*0.7 + 90*0.3 = 56 + 27 = 83 >= 75 -> Awarded
        $tender = $this->service->evaluateGreenTender('TND-SUPPLY-01', 'VEND-ECO-TECH', 80.0, 90.0, true);
        $this->assertEquals(83.0, (float) $tender->composite_award_score);
        $this->assertTrue((bool) $tender->awarded);

        // Vendor failing green score (20) -> Composite = 80*0.7 + 20*0.3 = 56 + 6 = 62 < 75 -> Not awarded
        $failedTender = $this->service->evaluateGreenTender('TND-SUPPLY-02', 'VEND-DIRTY-IND', 80.0, 20.0, true);
        $this->assertEquals(62.0, (float) $failedTender->composite_award_score);
        $this->assertFalse((bool) $failedTender->awarded);
    }

    /**
     * (f) Audit status healthy with 0 discrepancies.
     */
    public function test_circularity_water_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
