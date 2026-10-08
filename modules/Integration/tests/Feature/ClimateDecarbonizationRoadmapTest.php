<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ClimateDecarbonizationRoadmapService;
use Tests\TestCase;

/**
 * Fase 229 — Keberlanjutan: Climate, Energy Transition & Decarbonization Roadmap Tests
 *
 * Covers:
 *  (a) Net-zero roadmap and Edge Case 229.6: Carbon offsets cannot substitute priority direct abatement
 *  (b) Internal shadow carbon price applied in energy transition project appraisals
 *  (c) Climate risk asset management and mandatory insurance alignment for high severity risks
 *  (d) Edge Case 229.7: Scope 3 emissions explicitly labeled as estimates, not claimed as measured
 *  (e) Quality audit esg:audit clean with 0 discrepancies
 */
class ClimateDecarbonizationRoadmapTest extends TestCase
{
    use RefreshDatabase;

    protected ClimateDecarbonizationRoadmapService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ClimateDecarbonizationRoadmapService::class);
    }

    /**
     * (a) Roadmap initiation and Edge Case 229.6: Direct abatement priority over offsets.
     */
    public function test_roadmap_abatement_priority_over_offsets(): void
    {
        $rdm = $this->service->initiateRoadmap('LOGISTICS', 100000, 50000, 85000, 80.0);
        $this->assertSame('ON_TRACK', $rdm->status);

        // 1. Trying to use 50% offset and 50% direct reduction (< 80% threshold) -> Exception
        try {
            $this->service->recordAbatementAndOffset($rdm->roadmap_code, 5000, 5000);
            $this->fail('Expected exception for offset substituting priority direct reduction.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('violates the mandatory minimum threshold', $e->getMessage());
        }

        // 2. Compliant reduction: 9000 direct abatement + 1000 offset (90% direct >= 80%) -> OK
        $updated = $this->service->recordAbatementAndOffset($rdm->roadmap_code, 9000, 1000);
        $this->assertEquals(9000.0, (float) $updated->direct_abatement_achieved_tco2e);
        $this->assertEquals(1000.0, (float) $updated->offset_applied_tco2e);
    }

    /**
     * (b) Energy transition project appraisal with shadow carbon pricing (229.2 & 229.3).
     */
    public function test_energy_project_shadow_carbon_pricing(): void
    {
        // 1,000 tCO2e reduction with $50/tCO2e shadow price -> $50,000 shadow carbon value
        $proj = $this->service->evaluateEnergyProject(
            'PRJ-SOLAR-01',
            'HOTEL',
            'SOLAR',
            500000, // Capex
            60000,  // Annual electricity savings
            12.5,   // IRR
            1000,   // tCO2e abatement
            50.0    // Shadow carbon price USD
        );

        $this->assertEquals(50000.0, (float) $proj->shadow_carbon_value);
        $this->assertEquals(110000.0, (float) $proj->adjusted_economic_value); // 60,000 + 50,000
        $this->assertSame('APPROVED', $proj->funding_status);
    }

    /**
     * (c) Climate risk physical/transition alignment with insurance policy (229.4 & 229.5).
     */
    public function test_climate_asset_risk_insurance_alignment(): void
    {
        // High severity risk without insurance policy -> Throws exception
        try {
            $this->service->registerClimateAssetRisk(
                'AST-PORT-01',
                'PORT',
                'Tanjung Priok Terminal',
                'FLOOD',
                'CARBON_TAX',
                'HIGH',
                'Construct seawalls & flood gates',
                null // Missing policy
            );
            $this->fail('Expected exception for high climate risk missing insurance alignment.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('requires linked insurance policy alignment', $e->getMessage());
        }

        // High severity risk with insurance policy -> Succeeded
        $risk = $this->service->registerClimateAssetRisk(
            'AST-PORT-01',
            'PORT',
            'Tanjung Priok Terminal',
            'FLOOD',
            'CARBON_TAX',
            'HIGH',
            'Construct seawalls & flood gates',
            'POL-INS-CAT-99'
        );
        $this->assertSame('HIGH', $risk->risk_severity);
        $this->assertSame('POL-INS-CAT-99', $risk->linked_insurance_policy_id);
    }

    /**
     * (d) Edge Case 229.7: Scope 3 emission estimate labeling.
     */
    public function test_scope3_estimation_labeling(): void
    {
        // Attempting to claim modeled Scope 3 as measured -> Throws exception
        try {
            $this->service->recordScope3Estimate(
                'AVIATION',
                'Purchased Aviation Biofuel',
                4200.0,
                'IPCC_2026_V2',
                true // Claimed as measured
            );
            $this->fail('Expected exception for claiming Scope 3 estimate as measured.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be claimed as measured', $e->getMessage());
        }

        // Properly labeled as estimate -> OK
        $est = $this->service->recordScope3Estimate(
            'AVIATION',
            'Purchased Aviation Biofuel',
            4200.0,
            'IPCC_2026_V2',
            false
        );
        $this->assertTrue((bool) $est->is_labeled_as_estimate);
        $this->assertFalse((bool) $est->claimed_as_measured);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_climate_decarbonization_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
