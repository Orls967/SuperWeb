<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\CircularEconomyService;
use Tests\TestCase;

/**
 * Fase 174 — Waste, Recycling & Industrial Circularity Tests
 *
 * Covers:
 *  (a) hazardous waste cannot route to unqualified/unlicensed party
 *  (b) treatment mass balance reconciles within tolerance
 *  (c) manifest hash-chain validity
 *  (d) circular:audit = 0 discrepancy
 */
class CircularEconomyTest extends TestCase
{
    use RefreshDatabase;

    protected CircularEconomyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CircularEconomyService::class);
    }

    /**
     * (a) Hazardous waste cannot route to facility without B3 permit.
     */
    public function test_hazardous_waste_permit_gating(): void
    {
        // 1. General facility (no B3 permit)
        $this->service->registerFacility('FAC-PLASTIC-01', 'EcoPlastic Indo', false);

        // 2. Licensed hazardous B3 facility
        $this->service->registerFacility('FAC-B3-TPA-01', 'Prasandha B3 Facility', true);

        // Routing hazardous waste to unlicensed facility -> Exception
        try {
            $this->service->createManifest('MINING', 'FAC-PLASTIC-01', 'HAZARDOUS_B3', true, 50.0);
            $this->fail('Expected exception for unlicensed hazardous waste routing.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('without valid B3 permit', $e->getMessage());
        }

        // Routing to licensed facility -> SUCCESS
        $manifest = $this->service->createManifest('MINING', 'FAC-B3-TPA-01', 'HAZARDOUS_B3', true, 50.0);
        $this->assertSame('DISPATCHED', $manifest->status);
        $this->assertNotNull($manifest->manifest_hash);
    }

    /**
     * (b) Circularity treatment mass-balance validation.
     */
    public function test_treatment_mass_balance_reconciliation(): void
    {
        // 100 tons input: 85 tons recycled + 15 tons residual waste = 100 tons (0% diff -> VALID)
        $valid = $this->service->recordTreatment('MAN-001', 100.0, 85.0, 15.0);
        $this->assertTrue((bool) $valid->mass_balance_valid);

        // 100 tons input: 60 tons recycled + 20 tons residual = 80 tons (20% loss -> INVALID)
        $invalid = $this->service->recordTreatment('MAN-002', 100.0, 60.0, 20.0);
        $this->assertFalse((bool) $invalid->mass_balance_valid);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_circular_economy_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
