<?php

declare(strict_types=1);

namespace Tests\Acceptance\Energy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Tests\TestCase;

class MicrogridAndResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected MicrogridAndResilienceService $resilienceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resilienceService = app(MicrogridAndResilienceService::class);
    }

    public function test_islanding_priority_order_hospital_then_factory_then_mall(): void
    {
        // Vector from prompt:
        // Capacity: 500 kW
        // Groups: RS [ICU 200, ward 150], Factory [120, 100], Mall [90, 60]
        // Served: ICU (200), ward (150), Factory-120 = 470 kW
        // Cut: Factory-100, Mall-90, Mall-60
        $capacity = 500;
        $groups = [
            'HOSPITAL' => [['id' => 'ICU', 'kw' => 200], ['id' => 'WARD', 'kw' => 150]],
            'FACTORY' => [['id' => 'FAC_120', 'kw' => 120], ['id' => 'FAC_100', 'kw' => 100]],
            'MALL' => [['id' => 'MALL_90', 'kw' => 90], ['id' => 'MALL_60', 'kw' => 60]],
        ];

        $plan = $this->resilienceService->planIslanding($capacity, $groups);

        expect($plan['total_served_kw'])->toBe(470)
            ->and($plan['served_loads'])->toContain('ICU', 'WARD', 'FAC_120')
            ->and($plan['shed_loads'])->toContain('FAC_100', 'MALL_90', 'MALL_60');
    }

    public function test_islanding_respects_capacity_limit_strictly(): void
    {
        $capacity = 300;
        $groups = [
            'HOSPITAL' => [['id' => 'H1', 'kw' => 200], ['id' => 'H2', 'kw' => 150]],
        ];

        $plan = $this->resilienceService->planIslanding($capacity, $groups);

        expect($plan['total_served_kw'])->toBeLessThanOrEqual(300)
            ->and($plan['total_served_kw'])->toBe(200);
    }

    public function test_shed_loads_recorded_with_reasons(): void
    {
        $capacity = 100;
        $groups = [
            'MALL' => [['id' => 'MALL_CHILLER', 'kw' => 150]],
        ];

        $plan = $this->resilienceService->planIslanding($capacity, $groups);

        expect($plan['shed_loads_details'][0]['id'])->toBe('MALL_CHILLER')
            ->and($plan['shed_loads_details'][0]['reason'])->toBe('CAPACITY_EXCEEDED');
    }

    public function test_artisan_command_island_executes_emergency_dispatch(): void
    {
        $exitCode = Artisan::call('egy:island');
        expect($exitCode)->toBe(0);
    }

    public function test_battery_arbitrage_exact_vector_at_10000_bps_yields_100k_profit(): void
    {
        // 100 kWh, 50 kW/h, step 10, prices [1000, 1000, 2000, 2000], efficiency 10000 bps
        $battery = [
            'capacity_kwh' => 100,
            'max_power_kw' => 50,
            'soc_step_kwh' => 10,
            'efficiency_bps' => 10000,
        ];
        $prices = [1000, 1000, 2000, 2000];

        $plan = $this->resilienceService->planBatteryArbitrage($battery, $prices);

        expect($plan['profit_minor'])->toBe(100000)
            ->and($plan['actions'])->toBe(['CHARGE', 'CHARGE', 'DISCHARGE', 'DISCHARGE']);
    }

    public function test_battery_arbitrage_at_9000_bps_efficiency_yields_80k_profit(): void
    {
        $battery = [
            'capacity_kwh' => 100,
            'max_power_kw' => 50,
            'soc_step_kwh' => 10,
            'efficiency_bps' => 9000,
        ];
        $prices = [1000, 1000, 2000, 2000];

        $plan = $this->resilienceService->planBatteryArbitrage($battery, $prices);

        expect($plan['profit_minor'])->toBe(80000);
    }

    public function test_battery_state_of_charge_strictly_bounded_between_zero_and_capacity(): void
    {
        $battery = [
            'capacity_kwh' => 50,
            'max_power_kw' => 25,
            'soc_step_kwh' => 5,
            'efficiency_bps' => 10000,
        ];
        $prices = [500, 500, 2500, 2500];

        $plan = $this->resilienceService->planBatteryArbitrage($battery, $prices);

        foreach ($plan['soc_trajectory'] as $soc) {
            expect($soc)->toBeGreaterThanOrEqual(0)
                ->and($soc)->toBeLessThanOrEqual(50);
        }
    }

    public function test_initial_soc_equals_final_soc_constraint_enforced(): void
    {
        $battery = [
            'capacity_kwh' => 100,
            'max_power_kw' => 50,
            'soc_step_kwh' => 10,
            'efficiency_bps' => 10000,
            'initial_soc_kwh' => 20,
        ];
        $prices = [1000, 2000];

        $plan = $this->resilienceService->planBatteryArbitrage($battery, $prices);

        $initialSoc = $plan['soc_trajectory'][0];
        $finalSoc = end($plan['soc_trajectory']);

        expect($finalSoc)->toBe($initialSoc);
    }

    public function test_artisan_command_plan_battery_generates_and_stores_dispatch_run(): void
    {
        $exitCode = Artisan::call('egy:plan-battery');
        expect($exitCode)->toBe(0);
    }

    public function test_battery_arbitrage_verifier_validates_plan_correctness(): void
    {
        $battery = [
            'capacity_kwh' => 50,
            'max_power_kw' => 25,
            'soc_step_kwh' => 5,
            'efficiency_bps' => 9500,
        ];
        $prices = [1000, 1500, 3000, 1000];

        $plan = $this->resilienceService->planBatteryArbitrage($battery, $prices);

        expect($plan['certificate']['is_valid'])->toBeTrue();
    }
}
