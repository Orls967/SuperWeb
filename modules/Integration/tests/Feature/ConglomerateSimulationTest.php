<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ConglomerateSimulationService;
use Tests\TestCase;

class ConglomerateSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected ConglomerateSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConglomerateSimulationService::class);
    }

    public function test_conglomerate_365_day_simulation_and_determinism_proof(): void
    {
        // 465.1 & 465.2 Run 365-day compressed simulation with seed SEED-2026-ALPHA across 30 lines
        $run1 = $this->service->executeSimulation(
            code: 'SIM-RUN-2026-01',
            seed: 'SEED-2026-ALPHA',
            days: 365,
            lines: 30,
            auditsPassed: 112,
            varianceCount: 0
        );

        $this->assertEquals('completed', $run1->status);
        $this->assertEquals(0, $run1->audit_variance_count);

        // 465.3 Re-run with identical seed to prove determinism
        $run2 = $this->service->executeSimulation(
            code: 'SIM-RUN-2026-02',
            seed: 'SEED-2026-ALPHA',
            days: 365,
            lines: 30,
            auditsPassed: 112,
            varianceCount: 0
        );

        $isDeterministic = $this->service->verifyDeterminism('SIM-RUN-2026-01', 'SIM-RUN-2026-02');
        $this->assertTrue($isDeterministic);

        // 465.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_mid_simulation_variance_halts_flow_edge_case(): void
    {
        // 465.5 Edge case: Mid-simulation audit discrepancy stops execution
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit discrepancy detected mid-simulation (3 variances)');

        $this->service->executeSimulation(
            code: 'SIM-RUN-CORRUPT',
            seed: 'SEED-BAD',
            days: 180,
            lines: 30,
            auditsPassed: 90,
            varianceCount: 3 // Discrepancy!
        );
    }
}
