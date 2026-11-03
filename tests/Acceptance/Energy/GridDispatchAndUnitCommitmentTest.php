<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\GenerationAsset;
use Tests\TestCase;

class GridDispatchAndUnitCommitmentTest extends TestCase
{
    use RefreshDatabase;

    protected EnergyGridAndMeteringService $gridService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gridService = app(EnergyGridAndMeteringService::class);
    }

    public function test_unit_commitment_exact_vector_produces_optimal_cost_2850(): void
    {
        // 3 units from prompt vector:
        // A (min 20, max 100, cost 10, start 100)
        // B (min 10, max 50, cost 20, start 50)
        // C (min 5, max 40, cost 50, start 0)
        // Demands: [50, 120, 80]
        $units = [
            ['id' => 'A', 'min_power' => 20, 'max_power' => 100, 'cost_per_mwh' => 10, 'startup_cost' => 100],
            ['id' => 'B', 'min_power' => 10, 'max_power' => 50, 'cost_per_mwh' => 20, 'startup_cost' => 50],
            ['id' => 'C', 'min_power' => 5, 'max_power' => 40, 'cost_per_mwh' => 50, 'startup_cost' => 0],
        ];
        $demands = [50, 120, 80];

        $plan = $this->gridService->planUnitCommitment($units, $demands);

        expect($plan['status'])->toBe('OPTIMAL')
            ->and($plan['total_cost'])->toBe(2850);
    }

    public function test_demand_exceeding_total_capacity_returns_infeasible(): void
    {
        $units = [
            ['id' => 'A', 'min_power' => 10, 'max_power' => 50, 'cost_per_mwh' => 10, 'startup_cost' => 50],
        ];
        $demands = [40, 100]; // 100 > max 50

        $plan = $this->gridService->planUnitCommitment($units, $demands);

        expect($plan['status'])->toBe('INFEASIBLE')
            ->and($plan['infeasible_hour'])->toBe(1);
    }

    public function test_unit_min_and_max_generation_constraints_respected(): void
    {
        $units = [
            ['id' => 'A', 'min_power' => 20, 'max_power' => 100, 'cost_per_mwh' => 10, 'startup_cost' => 100],
            ['id' => 'B', 'min_power' => 10, 'max_power' => 50, 'cost_per_mwh' => 20, 'startup_cost' => 50],
        ];
        $demands = [30, 110];

        $plan = $this->gridService->planUnitCommitment($units, $demands);

        foreach ($plan['schedule'] as $hour => $dispatches) {
            foreach ($dispatches as $unitId => $power) {
                if ($power > 0) {
                    $uConfig = collect($units)->firstWhere('id', $unitId);
                    expect($power)->toBeGreaterThanOrEqual($uConfig['min_power'])
                        ->and($power)->toBeLessThanOrEqual($uConfig['max_power']);
                }
            }
        }
    }

    public function test_marginal_merit_order_dispatch_fills_lowest_cost_generator_first(): void
    {
        $units = [
            ['id' => 'EXPENSIVE', 'min_power' => 10, 'max_power' => 50, 'cost_per_mwh' => 50, 'startup_cost' => 0],
            ['id' => 'CHEAP', 'min_power' => 10, 'max_power' => 50, 'cost_per_mwh' => 10, 'startup_cost' => 0],
        ];
        $demands = [40];

        $plan = $this->gridService->planUnitCommitment($units, $demands);

        expect($plan['schedule'][0]['CHEAP'])->toBe(40)
            ->and($plan['schedule'][0]['EXPENSIVE'] ?? 0)->toBe(0);
    }

    public function test_artisan_command_commit_units_executes_and_stores_plan(): void
    {
        GenerationAsset::create([
            'id' => (string) Str::uuid(),
            'asset_code' => 'GEN-UC-01',
            'name' => 'Solar Genco 1',
            'asset_type' => 'SOLAR_FARM',
            'installed_capacity_mw' => 50.0,
            'current_output_mw' => 0.0,
            'status' => 'ONLINE',
        ]);

        $exitCode = Artisan::call('egy:commit-units');
        expect($exitCode)->toBe(0);
    }

    public function test_unit_commitment_verifier_validates_produced_schedule(): void
    {
        $units = [
            ['id' => 'A', 'min_power' => 10, 'max_power' => 100, 'cost_per_mwh' => 10, 'startup_cost' => 50],
        ];
        $demands = [50];

        $plan = $this->gridService->planUnitCommitment($units, $demands);

        expect($plan['certificate']['is_valid'])->toBeTrue();
    }

    public function test_curtailed_generation_logged_when_minimum_exceeds_demand(): void
    {
        $units = [
            ['id' => 'MUST_RUN', 'min_power' => 50, 'max_power' => 100, 'cost_per_mwh' => 10, 'startup_cost' => 0],
        ];
        $demands = [30]; // Must run min 50 > demand 30 -> 20 curtailed

        $plan = $this->gridService->planUnitCommitment($units, $demands);

        expect($plan['curtailment_mwh'])->toBeGreaterThan(0);
    }

    public function test_unit_startup_costs_correctly_accounted_for_when_offline_unit_starts(): void
    {
        $units = [
            ['id' => 'A', 'min_power' => 10, 'max_power' => 50, 'cost_per_mwh' => 10, 'startup_cost' => 500],
        ];
        $demands = [20];

        $plan = $this->gridService->planUnitCommitment($units, $demands, ['A' => false]);

        // Total cost = 500 (startup) + 20 * 10 (fuel) = 700
        expect($plan['total_cost'])->toBe(700);
    }
}
