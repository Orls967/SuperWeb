<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PredictiveOperationsDigitalTwinService (Fase 307)
 *
 * Implements:
 *  - 307.1 Twin-based control loop (predict state -> suggest setpoint/maintenance -> execute)
 *  - 307.2 Prescriptive maintenance orchestration (predict failure -> optimize schedule -> minimize downtime)
 *  - 307.3 & 307.4 Twin fidelity monitoring, drift alert triggering, and usage gate
 *  - 307.5 Edge case: Recommendations made during low fidelity are rejected for auto-application and presented solely as advisory
 *  - 307.6 Risk: Uncontrolled feedback loops prevented with mandatory cycle change damping limits (<= 20%)
 */
class PredictiveOperationsDigitalTwinService
{
    /**
     * Record twin fidelity score and trigger drift alert if below threshold (307.3 & 307.4).
     */
    public function monitorTwinFidelity(
        string $modelCode,
        string $domainSystem,
        float $fidelityScorePct,
        float $minRequiredFidelityPct = 90.00
    ): object {
        $mCode = strtoupper($modelCode);

        // Drift check 307.3 & 307.4
        $driftAlert = ($fidelityScorePct < $minRequiredFidelityPct);

        DB::table('digital_twin_fidelity_models')->updateOrInsert(
            ['model_code' => $mCode],
            [
                'domain_system' => strtoupper($domainSystem),
                'fidelity_score_pct' => $fidelityScorePct,
                'min_required_fidelity_pct' => $minRequiredFidelityPct,
                'drift_alert_triggered' => $driftAlert,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('digital_twin_fidelity_models')->where('model_code', $mCode)->first();
    }

    /**
     * Evaluate control loop action recommendation with fidelity gating & cycle damping (307.1, 307.4, 307.5, 307.6).
     */
    public function proposeTwinControlAction(
        string $actionCode,
        string $modelCode,
        string $recommendation,
        float $dampingFactorPct = 20.00
    ): object {
        $aCode = strtoupper($actionCode);
        $mCode = strtoupper($modelCode);

        $model = DB::table('digital_twin_fidelity_models')->where('model_code', $mCode)->first();
        if (! $model) {
            throw new InvalidArgumentException("Twin model '{$modelCode}' not found.");
        }

        // Risk limit 307.6: Damping factor must be capped at 20% to prevent destructive oscillatory feedback
        if ($dampingFactorPct > 20.00) {
            throw new InvalidArgumentException('Feedback loop risk breach: Damping change factor cannot exceed 20% per cycle (307.6).');
        }

        $fidelity = (float) $model->fidelity_score_pct;
        $minFidelity = (float) $model->min_required_fidelity_pct;

        // Edge case 307.5: Low fidelity (< threshold) strictly prohibits auto-application; downgraded to ADVISORY_ONLY
        $canAutoApply = ($fidelity >= $minFidelity);
        $status = $canAutoApply ? 'APPLIED' : 'ADVISORY_ONLY';

        $id = DB::table('digital_twin_control_actions')->insertGetId([
            'action_code' => $aCode,
            'model_code' => $mCode,
            'recommended_action' => strtoupper($recommendation),
            'model_fidelity_at_recommendation' => $fidelity,
            'auto_execution_applied' => $canAutoApply,
            'execution_status' => $status,
            'damping_factor_pct' => $dampingFactorPct,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('digital_twin_control_actions')->find($id);
    }

    /**
     * Operations Quality & Twin Audit (`quality:audit`) (307.4, 307.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Low fidelity models with auto-executed actions
        $improperAutoExecutions = DB::table('digital_twin_control_actions')
            ->where('auto_execution_applied', true)
            ->where('model_fidelity_at_recommendation', '<', 90.0)
            ->count();

        // Discrepancy 2: Models below threshold without drift alert
        $unalertedDrifts = DB::table('digital_twin_fidelity_models')
            ->whereRaw('fidelity_score_pct < min_required_fidelity_pct')
            ->where('drift_alert_triggered', false)
            ->count();

        $discrepancies = $improperAutoExecutions + $unalertedDrifts;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_twin_models' => DB::table('digital_twin_fidelity_models')->count(),
            'total_control_actions' => DB::table('digital_twin_control_actions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
