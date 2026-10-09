<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EnterpriseFutureReadinessService;
use Tests\TestCase;

class EnterpriseFutureReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseFutureReadinessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseFutureReadinessService::class);
    }

    public function test_future_scenario_and_innovation_pipeline_flow(): void
    {
        // 482.1 & 482.2 Register future scenario (Hydrogen Heavy Mobility 2035) with option investment
        $sc = $this->service->registerFutureScenario(
            code: 'SCENARIO-H2-MOBILITY-2035',
            title: 'Mass Commercial Hydrogen Fuel Cell Heavy Vehicle Adoption',
            horizon: '2035',
            optionInvestment: 5000000000.00,
            triggerOwner: 'VP Group Technology Strategy',
            cadence: 'semi-annually'
        );

        $this->assertEquals('SCENARIO-H2-MOBILITY-2035', $sc->scenario_code);
        $this->assertEquals('active_monitoring', $sc->status);
        $this->assertEquals(5000000000.00, (float) $sc->option_investment_idr);

        // 482.3 Record innovation pipeline health
        $pipe = $this->service->recordPipelineHealth(
            pipelineCode: 'PIPE-Q3-2026',
            ideas: 120,
            experiments: 40,
            pilots: 15,
            scaled: 8
        );

        $this->assertEquals(8, $pipe->scaled_solutions_count);

        // 482.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_missing_trigger_owner_and_re_review_assumptions_edge_cases(): void
    {
        // 482.6 Risk: Horizon scanning without trigger owner is blocked
        try {
            $this->service->registerFutureScenario('SC-NO-OWNER', 'Flying Cars', '2040', 1000.00, '', 'annual');
            $this->fail('Expected exception for missing trigger owner');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Horizon trigger monitoring requires an assigned owner', $e->getMessage());
        }

        // 482.5 Edge case: Re-review underlying assumptions for outlier scenario
        $this->service->registerFutureScenario('SC-OUTLIER', 'Hyperloop Freight', '2030', 250000000.00, 'Chief Futurist');
        $reviewed = $this->service->reReviewAssumptions('SC-OUTLIER');
        $this->assertTrue((bool) $reviewed->assumptions_reviewed);
    }
}
