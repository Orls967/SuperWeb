<?php

declare(strict_types=1);

namespace Modules\ControlTower\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\ControlTower\Application\Actions\RunForecastModelSelectionAction;
use Modules\ControlTower\Application\Services\ForecastOverrideService;
use Modules\ControlTower\Domain\Algorithms\ForecastModelSelector;
use Modules\ControlTower\Domain\Models\DemandForecast;
use Modules\ControlTower\Domain\Models\ForecastOverride;
use Modules\Core\Domain\Models\ApprovalRequest;
use Tests\TestCase;

final class Slice2ForecastSelectionAndOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_i2_selector_picks_ses_for_stationary_series_with_exact_integer_arithmetic(): void
    {
        $selector = app(ForecastModelSelector::class);

        $result = $selector->select('SKU-STAT-01', [100, 125, 126, 124, 125, 126, 125]);

        $this->assertSame('SES', $result['selected_model']);
        $this->assertIsInt($result['forecast_qty']);
        $this->assertIsInt($result['forecast_scaled']);
        $this->assertIsInt($result['mae_scaled']);
        $this->assertIsInt($result['mape_bps']);
        $this->assertSame(6, $result['evaluation_points']);
        $this->assertSame(64, strlen($result['certificate_hash']));
        $this->assertLessThan(
            $result['candidates']['CROSTON']['mae_scaled'],
            $result['candidates']['SES']['mae_scaled'],
        );
        $this->assertLessThan(
            $result['candidates']['HOLT']['mae_scaled'],
            $result['candidates']['SES']['mae_scaled'],
        );
    }

    public function test_i2_selector_picks_holt_for_linear_upward_trend(): void
    {
        $selector = app(ForecastModelSelector::class);

        $result = $selector->select('SKU-TREND-01', [100, 120, 140, 160, 180, 200, 220]);

        $this->assertSame('HOLT', $result['selected_model']);
        $this->assertSame(240, $result['forecast_qty']);
        $this->assertLessThan(
            $result['candidates']['SES']['mae_scaled'],
            $result['candidates']['HOLT']['mae_scaled'],
        );
        $this->assertLessThan(
            $result['candidates']['CROSTON']['mae_scaled'],
            $result['candidates']['HOLT']['mae_scaled'],
        );
    }

    public function test_i2_selector_picks_croston_for_intermittent_zero_heavy_series(): void
    {
        $selector = app(ForecastModelSelector::class);

        $result = $selector->select('SKU-SPARSE-01', [0, 0, 0, 100, 0, 0, 0, 100, 0, 0, 0, 100]);

        $this->assertSame('CROSTON', $result['selected_model']);
        $this->assertSame(6, $result['evaluation_points']);
        $this->assertLessThan(
            $result['candidates']['SES']['mae_scaled'],
            $result['candidates']['CROSTON']['mae_scaled'],
        );
        $this->assertLessThan(
            $result['candidates']['HOLT']['mae_scaled'],
            $result['candidates']['CROSTON']['mae_scaled'],
        );
    }

    public function test_i2_selector_tie_breaker_is_deterministic_and_handles_short_series(): void
    {
        $selector = app(ForecastModelSelector::class);

        // Constant series -> all 3 candidates have mae_scaled = 0 -> tie-breaker SES -> HOLT -> CROSTON picks SES
        $first = $selector->select('SKU-TIE-01', [100, 100, 100, 100]);
        $second = $selector->select('SKU-TIE-01', [100, 100, 100, 100]);

        $this->assertSame('SES', $first['selected_model']);
        $this->assertSame(0, $first['mae_scaled']);
        $this->assertSame(3, $first['evaluation_points']);
        $this->assertSame($first['certificate_hash'], $second['certificate_hash']);

        // Minimum 3 periods allowed
        $minSeries = $selector->select('SKU-MIN-01', [50, 50, 50]);
        $this->assertSame(2, $minSeries['evaluation_points']);
        $this->assertSame('SES', $minSeries['selected_model']);
    }

    public function test_i2_selector_rejects_short_or_negative_history(): void
    {
        $selector = app(ForecastModelSelector::class);

        $this->expectException(InvalidArgumentException::class);
        $selector->select('SKU-ERR-01', [100, 110]);
    }

    public function test_i2_selector_rejects_negative_demand_values(): void
    {
        $selector = app(ForecastModelSelector::class);

        $this->expectException(InvalidArgumentException::class);
        $selector->select('SKU-ERR-02', [100, -10, 120]);
    }

    public function test_x53_4_override_with_reason_and_revert_and_rejects_invalid_inputs(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        $action = app(RunForecastModelSelectionAction::class);
        $overrideService = app(ForecastOverrideService::class);

        $selection = $action->execute('SKU-OV-01', '2026-11', [100, 100, 100, 100, 100, 100, 100]);
        $forecast = $selection['forecast'];
        $this->assertSame(100, $forecast->forecast_qty);

        // Small deviation (10% = 1000 bps < 2000 bps) -> applied immediately
        $override = $overrideService->recordOverride(
            forecast: $forecast,
            newQty: 110,
            reason: 'Promosi akhir tahun regional',
            actor: $planner,
        );

        $this->assertSame('applied', $override->status);
        $this->assertSame(100, $override->previous_qty);
        $this->assertSame(110, $override->new_qty);
        $this->assertSame(10, $override->delta_qty);
        $this->assertSame(1000, $override->deviation_bps);

        $forecast->refresh();
        $this->assertSame(110, $forecast->forecast_qty);
        $this->assertTrue($forecast->is_overridden);

        // Revert restores baseline and updates audit trail
        $reverted = $overrideService->revertOverride($override, $planner);
        $this->assertSame('reverted', $reverted->status);
        $this->assertNotNull($reverted->reverted_at);
        $this->assertSame($planner->id, $reverted->reverted_by_user_id);

        $forecast->refresh();
        $this->assertSame(100, $forecast->forecast_qty);
        $this->assertFalse($forecast->is_overridden);

        // Reject empty reason
        try {
            $overrideService->recordOverride($forecast, 105, '   ', $planner);
            $this->fail('Expected InvalidArgumentException for empty reason');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('OVERRIDE_REASON_REQUIRED', $e->getMessage());
        }

        // Reject negative qty
        try {
            $overrideService->recordOverride($forecast, -5, 'Alasan valid lebih dari lima huruf', $planner);
            $this->fail('Expected InvalidArgumentException for negative quantity');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('NEGATIVE_FORECAST_QTY', $e->getMessage());
        }
    }

    public function test_x53_4_large_deviation_triggers_approval_engine_and_supports_approve_and_reject(): void
    {
        $planner = User::factory()->create(['role' => 'planner']);
        $approver = User::factory()->create(['role' => 'logistics_admin']);
        $action = app(RunForecastModelSelectionAction::class);
        $overrideService = app(ForecastOverrideService::class);

        $forecast = $action->execute('SKU-APP-01', '2026-11', [100, 100, 100, 100, 100, 100, 100])['forecast'];

        // 50% increase (5000 bps >= 2000 bps) -> pending_approval
        $pending = $overrideService->recordOverride(
            forecast: $forecast,
            newQty: 150,
            reason: 'Lonjakan kontrak OEM Q4',
            actor: $planner,
        );

        $this->assertSame('pending_approval', $pending->status);
        $this->assertSame(5000, $pending->deviation_bps);
        $this->assertNotNull($pending->approval_id);
        $this->assertDatabaseHas('core_approvals', [
            'id' => $pending->approval_id,
            'status' => 'pending',
            'approval_type' => 'CONTROL_TOWER_FORECAST_OVERRIDE',
        ]);

        // Forecast remains at baseline while pending approval
        $forecast->refresh();
        $this->assertSame(100, $forecast->forecast_qty);

        // Approve by logistics_admin
        $approved = $overrideService->approveOverride($pending, $approver, 'Disetujui sesuai PO OEM');
        $this->assertSame('applied', $approved->status);

        $forecast->refresh();
        $this->assertSame(150, $forecast->forecast_qty);
        $this->assertTrue($forecast->is_overridden);

        $approval = ApprovalRequest::query()->findOrFail($pending->approval_id);
        $this->assertSame('approved', $approval->status);

        // Submit another large override and reject it
        $secondPending = $overrideService->recordOverride(
            forecast: $forecast,
            newQty: 300,
            reason: 'Permintaan spekulatif cabang',
            actor: $planner,
        );
        $this->assertSame('pending_approval', $secondPending->status);

        $rejected = $overrideService->rejectOverride($secondPending, $approver, 'Tidak ada PO pendukung');
        $this->assertSame('rejected', $rejected->status);

        $forecast->refresh();
        $this->assertSame(150, $forecast->forecast_qty);
    }

    public function test_tower_forecast_select_command_is_idempotent(): void
    {
        $this->artisan('tower:forecast-select', [
            '--item' => 'SKU-CMD-01',
            '--period' => '2026-11',
        ])->assertExitCode(0);

        $firstRow = DemandForecast::query()
            ->where('item_code', 'SKU-CMD-01')
            ->where('period', '2026-11')
            ->firstOrFail();

        $this->artisan('tower:forecast-select', [
            '--item' => 'SKU-CMD-01',
            '--period' => '2026-11',
        ])->assertExitCode(0);

        $count = DemandForecast::query()
            ->where('item_code', 'SKU-CMD-01')
            ->where('period', '2026-11')
            ->count();

        $secondRow = DemandForecast::query()
            ->where('item_code', 'SKU-CMD-01')
            ->where('period', '2026-11')
            ->firstOrFail();

        $this->assertSame(1, $count);
        $this->assertSame($firstRow->id, $secondRow->id);
        $this->assertSame($firstRow->certificate_hash, $secondRow->certificate_hash);
        $this->assertSame($firstRow->mae_scaled, $secondRow->mae_scaled);
    }

    public function test_routes_rbac_override_revert_and_csv_export(): void
    {
        $auditor = User::factory()->create(['role' => 'auditor']);
        $planner = User::factory()->create(['role' => 'planner']);
        $customer = User::factory()->create(['role' => 'customer']);

        // Unauthorized role rejected
        $this->actingAs($customer)
            ->get(route('control_tower.forecast.index'))
            ->assertStatus(403);

        // Planner runs model selection via HTTP POST
        $this->actingAs($planner)
            ->post(route('control_tower.forecast.select'), [
                'item_code' => 'SKU-WEB-01',
                'period' => '2026-11',
                'history' => '100,120,140,160,180,200,220',
            ])
            ->assertRedirect();

        $forecast = DemandForecast::query()
            ->where('item_code', 'SKU-WEB-01')
            ->where('period', '2026-11')
            ->firstOrFail();

        $this->assertSame('HOLT', $forecast->algorithm_model);
        $this->assertSame(240, $forecast->forecast_qty);

        // Auditor can view page & export CSV, but cannot mutate
        $this->actingAs($auditor)
            ->get(route('control_tower.forecast.index'))
            ->assertOk()
            ->assertSee('SKU-WEB-01')
            ->assertSee('HOLT')
            ->assertSee('Ekspor CSV');

        $csvResponse = $this->actingAs($auditor)
            ->get(route('control_tower.forecast.export'));
        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('forecast_code,item_code,period,algorithm_model', $csvContent);
        $this->assertStringContainsString('SKU-WEB-01', $csvContent);

        $this->actingAs($auditor)
            ->post(route('control_tower.forecast.select'), [
                'item_code' => 'SKU-WEB-02',
                'period' => '2026-11',
                'history' => '100,100,100',
            ])
            ->assertStatus(403);

        $this->actingAs($auditor)
            ->post(route('control_tower.forecast.override'), [
                'demand_forecast_id' => $forecast->id,
                'new_qty' => 250,
                'reason' => 'Auditor tidak boleh mengubah',
            ])
            ->assertStatus(403);

        // Validation rejects empty reason on override endpoint
        $this->actingAs($planner)
            ->post(route('control_tower.forecast.override'), [
                'demand_forecast_id' => $forecast->id,
                'new_qty' => 250,
                'reason' => '   ',
            ])
            ->assertSessionHasErrors('reason');

        // Planner applies valid small override (< 20%) and reverts it
        $this->actingAs($planner)
            ->post(route('control_tower.forecast.override'), [
                'demand_forecast_id' => $forecast->id,
                'new_qty' => 250,
                'reason' => 'Penyesuaian jadwal kirim mingguan',
            ])
            ->assertRedirect();

        $forecast->refresh();
        $this->assertSame(250, $forecast->forecast_qty);

        $override = ForecastOverride::query()
            ->where('demand_forecast_id', $forecast->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($auditor)
            ->post(route('control_tower.forecast.revert', $override->id))
            ->assertStatus(403);

        $this->actingAs($planner)
            ->post(route('control_tower.forecast.revert', $override->id))
            ->assertRedirect();

        $forecast->refresh();
        $override->refresh();
        $this->assertSame(240, $forecast->forecast_qty);
        $this->assertSame('reverted', $override->status);
    }
}
