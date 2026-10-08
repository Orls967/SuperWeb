<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AiOptimizationEngineService;
use Tests\TestCase;

/**
 * Fase 199 — AI: Optimization Engine (Routing, Scheduling, Allocation) Tests
 *
 * Covers:
 *  (a) deterministic solver execution with matching seeds
 *  (b) hard constraint satisfaction and feasible allocation
 *  (c) infeasible demand reports infeasibility cleanly without violating hard constraints
 *  (d) optimizer:audit = 0 discrepancy
 */
class AiOptimizationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected AiOptimizationEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiOptimizationEngineService::class);
    }

    /**
     * (a) & (b) Hard constraint satisfaction & determinism.
     */
    public function test_optimizer_hard_constraints_and_determinism(): void
    {
        $constraints = ['max_capacity' => 100, 'strict_capacity' => true];
        $demands = [
            ['id' => 'ROUTE_A', 'demand' => 40],
            ['id' => 'ROUTE_B', 'demand' => 50],
        ];

        // Run 1 with seed 42 -> SUCCESS (90 <= 100)
        $run1 = $this->service->solve('FLEET_ROUTING', 42, $constraints, $demands);
        $this->assertTrue((bool) $run1->hard_constraints_satisfied);
        $this->assertFalse((bool) $run1->is_infeasible);

        // Run 2 with identical seed produces identical recommendation
        $run2 = $this->service->solve('FLEET_ROUTING', 42, $constraints, $demands);
        $this->assertSame($run1->recommended_solution, $run2->recommended_solution);
    }

    /**
     * (c) Infeasible demand handling.
     */
    public function test_optimizer_infeasible_problem_handling(): void
    {
        $constraints = ['max_capacity' => 50, 'strict_capacity' => true];
        $demands = [
            ['id' => 'OVERFLOW_1', 'demand' => 40],
            ['id' => 'OVERFLOW_2', 'demand' => 30],
        ];

        // Total demand 70 exceeds max capacity 50 -> Marked infeasible, unsatisfied
        $run = $this->service->solve('FLEET_ROUTING', 99, $constraints, $demands);
        $this->assertTrue((bool) $run->is_infeasible);
        $this->assertFalse((bool) $run->hard_constraints_satisfied);
        $this->assertStringContainsString('Infeasible', $run->recommended_solution);
    }

    /**
     * (d) Audit status healthy with 0 discrepancies.
     */
    public function test_optimizer_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
