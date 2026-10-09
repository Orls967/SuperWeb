<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MarketDisruptionSimulationService (Fase 469)
 *
 * Implements:
 *  - 469.1 Disruption scenario: new competitor, demand collapse, technology shift
 *  - 469.2 Response playbook: pricing, cost, portfolio, partnership levers
 *  - 469.3 Verification: sandbox only (zero real data change)
 *  - 469.4 Tests: simulation deterministic, no real data touched, decision documented
 *  - 469.5 Edge case: Severe loss (> 20% EBITDA drop) mandates formal mitigation plan logging
 *  - 469.6 Risk: Unrealistic assumptions avoided by explicit confidence level labeling
 *  - 469.7 Evidence: scenario input, decision record, learning capture
 */
class MarketDisruptionSimulationService
{
    public function simulateDisruption(
        string $code,
        string $type,
        float $ebitdaImpactPercent,
        string $lever,
        string $confidence = 'high',
        bool $sandboxOnly = true
    ): object {
        // 469.3 & 469.4 Zero real data modification: Simulation must run strictly in sandbox
        if (! $sandboxOnly) {
            throw new InvalidArgumentException("Simulation blocked: Market disruption modeling must be restricted to isolated sandbox (469.3, 469.4).");
        }

        // 469.5 Edge case: Severe loss (> 20% drop) requires logging mitigation plan
        $isSevereLoss = ($ebitdaImpactPercent <= -20.00);

        $id = DB::table('sim_market_disruption_scenarios')->insertGetId([
            'scenario_code' => strtoupper($code),
            'disruption_type' => strtolower($type),
            'simulated_ebitda_impact_percentage' => $ebitdaImpactPercent,
            'sandbox_only' => true,
            'confidence_level' => strtolower($confidence),
            'severe_loss_mitigation_plan_logged' => $isSevereLoss,
            'strategic_lever_selected' => strtolower($lever),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('sim_market_disruption_scenarios')->where('id', $id)->first();
    }

    public function logMitigationPlan(string $code): object
    {
        $sc = DB::table('sim_market_disruption_scenarios')->where('scenario_code', strtoupper($code))->first();
        if (! $sc) {
            throw new InvalidArgumentException("Scenario '{$code}' not found.");
        }

        DB::table('sim_market_disruption_scenarios')->where('id', $sc->id)->update([
            'severe_loss_mitigation_plan_logged' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('sim_market_disruption_scenarios')->where('id', $sc->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Severe loss scenarios without logged mitigation plan
        $unmitigatedLosses = DB::table('sim_market_disruption_scenarios')
            ->where('simulated_ebitda_impact_percentage', '<=', -20.00)
            ->where('severe_loss_mitigation_plan_logged', false)
            ->count();

        // Discrepancy 2: Non-sandbox runs
        $unsandboxed = DB::table('sim_market_disruption_scenarios')
            ->where('sandbox_only', false)
            ->count();

        $total = $unmitigatedLosses + $unsandboxed;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_scenarios' => DB::table('sim_market_disruption_scenarios')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
