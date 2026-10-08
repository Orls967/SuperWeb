<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AssetReliabilityService;
use Tests\TestCase;

/**
 * Fase 214 — Operasi: Maintenance, Reliability & Asset Performance 30 Lini Tests
 *
 * Covers:
 *  (a) condition monitoring triggers work order once on failure prediction (< 40 score)
 *  (b) idempotent repeat evaluations do not regenerate duplicate work order codes
 *  (c) spare part availability gating for repair authorizations
 *  (d) ast:audit = 0 discrepancy
 */
class AssetReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected AssetReliabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssetReliabilityService::class);
    }

    /**
     * (a) & (b) Asset condition monitoring and idempotent work order generation.
     */
    public function test_asset_health_evaluation_and_work_order(): void
    {
        // 1. Healthy asset (Score 88.5 >= 40.0) -> No failure predicted, no WO
        $a1 = $this->service->evaluateAssetHealth('AST-TURBINE-01', 'FACTORY_TURBINE', 88.5);
        $this->assertFalse((bool) $a1->failure_predicted);
        $this->assertNull($a1->generated_work_order_code);

        // 2. Degraded asset (Score 32.0 < 40.0) -> Triggers Work Order
        $a2 = $this->service->evaluateAssetHealth('AST-TURBINE-01', 'FACTORY_TURBINE', 32.0);
        $this->assertTrue((bool) $a2->failure_predicted);
        $this->assertNotNull($a2->generated_work_order_code);
        $woCode = $a2->generated_work_order_code;

        // 3. Repeat evaluation (Score 28.0) -> Same Work Order code retained (idempotent)
        $a3 = $this->service->evaluateAssetHealth('AST-TURBINE-01', 'FACTORY_TURBINE', 28.0);
        $this->assertSame($woCode, $a3->generated_work_order_code);
    }

    /**
     * (c) Spare parts availability gating.
     */
    public function test_spare_part_availability_gate(): void
    {
        $this->service->setSparePartStock('PRT-TURBINE-BLADE', 'Gas Turbine Titanium Blade', 'CRITICAL', 5, 2);

        // Available: Request 2 <= 5 -> True
        $this->assertTrue($this->service->checkSparePartAvailability('PRT-TURBINE-BLADE', 2));

        // Unavailable: Request 10 > 5 -> False
        $this->assertFalse($this->service->checkSparePartAvailability('PRT-TURBINE-BLADE', 10));

        // Non-existent part -> False
        $this->assertFalse($this->service->checkSparePartAvailability('PRT-NON-EXISTENT'));
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_asset_reliability_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
