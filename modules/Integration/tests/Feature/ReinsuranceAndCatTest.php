<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ReinsuranceAndCatService;
use Tests\TestCase;

/**
 * Fase 157 — Reinsurance & CAT Modelling Tests
 *
 * Covers:
 *  (a) ceded + retained = gross premium (invarian)
 *  (b) solvabilitas deterministik (RBC ratio)
 *  (c) CAT loss tak melebihi layer structure
 *  (d) retrocession & treaty commission terhitung
 *  (e) ins:reinsurance-audit = 0 selisih
 */
class ReinsuranceAndCatTest extends TestCase
{
    use RefreshDatabase;

    protected ReinsuranceAndCatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReinsuranceAndCatService::class);
    }

    /**
     * (a) & (d) Ceded + Retained = Gross Premium invariant.
     */
    public function test_cession_balances_gross_premium_exactly(): void
    {
        $this->service->createTreaty('TR-QS-30', 'QUOTA_SHARE', 'SWISS-RE', 30.0, 500000000.0, 5.0);

        // Gross premium Rp 10,000,000 -> 30% ceded (3m), 70% retained (7m), comm 5% of ceded (150k)
        $cession = $this->service->processCession('TR-QS-30', 'POL-TEST-001', 10000000.00);

        $this->assertEquals(3000000.00, (float) $cession->ceded_premium);
        $this->assertEquals(7000000.00, (float) $cession->retained_premium);
        $this->assertEquals(150000.00, (float) $cession->reinsurance_commission);

        $sum = (float) $cession->ceded_premium + (float) $cession->retained_premium;
        $this->assertEquals((float) $cession->gross_premium, $sum);
    }

    /**
     * (b) Solvabilitas deterministik.
     */
    public function test_solvency_ratio_calculation(): void
    {
        // Assets: 150m, Required capital: 100m -> Solvency ratio = 150% (SOLVENT)
        $solvency = $this->service->calculateSolvencyRatio('Q3-2026', 150000000.0, 100000000.0);
        $this->assertSame('SOLVENT', $solvency->status);
        $this->assertEquals(150.00, (float) $solvency->solvency_ratio_pct);

        // Assets: 90m, Required: 100m -> 90% (CAPITAL_CALL)
        $under = $this->service->calculateSolvencyRatio('Q4-2026', 90000000.0, 100000000.0);
        $this->assertSame('CAPITAL_CALL', $under->status);
        $this->assertEquals(90.00, (float) $under->solvency_ratio_pct);
    }

    /**
     * (c) CAT loss allocation does not exceed layer structure.
     */
    public function test_cat_loss_layer_allocation(): void
    {
        // Gross loss: 100m. Attachment point: 20m. Layer limit: 50m.
        // Retained by primary: 20m.
        // Reinsurance covered: 50m (capped at layer limit).
        // Exhausted uncovered loss: 30m.
        $allocation = $this->service->allocateCatLoss('EARTHQUAKE', 100000000.0, 20000000.0, 50000000.0);

        $this->assertEquals(20000000.00, $allocation['primary_retained']);
        $this->assertEquals(50000000.00, $allocation['reinsurance_recovered']);
        $this->assertEquals(30000000.00, $allocation['exhausted_uncovered']);
        $this->assertEquals(100000000.00, $allocation['primary_retained'] + $allocation['reinsurance_recovered'] + $allocation['exhausted_uncovered']);
    }

    /**
     * (e) Reinsurance audit quality gate.
     */
    public function test_reinsurance_audit_returns_zero_discrepancy(): void
    {
        $this->service->createTreaty('TR-AUTO-20', 'QUOTA_SHARE', 'MUNICH-RE', 20.0, 300000000.0);
        $this->service->processCession('TR-AUTO-20', 'POL-AUTO-999', 5000000.00);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
