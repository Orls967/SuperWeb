<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\ValueChainSimulationService;
use Tests\TestCase;

/**
 * Fase 186 — Integrasi 30 Lini A: End-to-End Value Chain Simulation Tests
 *
 * Covers:
 *  (a) 90-day simulation enforces strict double-entry ledger balance Σ=0
 *  (b) ledger imbalance immediately fails with exception
 *  (c) cross-line contract bridge adapter avoids duplicate contract state
 *  (d) valuechain:audit = 0 discrepancy
 */
class ValueChainSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected ValueChainSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ValueChainSimulationService::class);
    }

    /**
     * (a) & (b) Simulation ledger balance invariant Σ=0 strictly guarded.
     */
    public function test_value_chain_simulation_ledger_conservation(): void
    {
        // 1. Balanced simulation: Rp 50,000,000,000 debits & credits -> SUCCESS
        $sim = $this->service->runValueChainSimulation(
            'AGRI_FOOD_RETAIL_CHAIN',
            90,
            50000000000.0,
            50000000000.0
        );
        $this->assertSame('COMPLETED', $sim->status);
        $this->assertEquals(0.00, (float) $sim->net_imbalance_idr);

        // 2. Imbalanced simulation -> BLOCKED
        try {
            $this->service->runValueChainSimulation(
                'MINING_METAL_MANUFACTURING',
                90,
                10000000000.0,
                9999000000.0 // 1 million discrepancy
            );
            $this->fail('Expected exception for unbalanced simulation ledger.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulation ledger imbalance detected', $e->getMessage());
        }
    }

    /**
     * (c) Cross-line contract bridge adapter idempotency.
     */
    public function test_contract_bridge_adapter(): void
    {
        $bridge = $this->service->bridgeCrossLineContract(
            'CTR-PPA-SOLAR-001',
            'PPA',
            'ENERGY',
            'HOSPITALITY',
            2500000000.0
        );

        $this->assertSame('CTR-PPA-SOLAR-001', $bridge->core_contract_code);
        $this->assertFalse((bool) $bridge->has_duplicate_state);

        // Duplicate registration returns same instance
        $b2 = $this->service->bridgeCrossLineContract(
            'CTR-PPA-SOLAR-001',
            'PPA',
            'ENERGY',
            'HOSPITALITY',
            2500000000.0
        );
        $this->assertSame($bridge->bridge_code, $b2->bridge_code);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_value_chain_simulation_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
