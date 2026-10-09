<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;
use Modules\Core\Domain\Models\CoreAiGovernanceDriftLog;
use Modules\Core\Domain\Models\CoreAutonomousOperation;
use RuntimeException;

class CrossLineAiAndAutonomousService
{
    /**
     * 143.1 Deterministic AI Decision Engine with Seed & Hash.
     * Test (a): Rekonstruksi keputusan AI identik dari seed & inputs.
     */
    public function makeDeterministicDecision(string $modelName, string $version, string $seed, array $inputs): CoreAiDecisionSnapshot
    {
        // Deterministic pseudo-algorithm seeded
        $seedInt = crc32($seed);
        $computedScore = ($seedInt % 100) / 100.0;

        $output = [
            'recommended_price_adjustment_pct' => round($computedScore * 10, 2),
            'confidence' => 0.95,
            'decision_flag' => 'AUTO_APPLY',
        ];

        $payloadToHash = $modelName.'|'.$version.'|'.$seed.'|'.json_encode($inputs).'|'.json_encode($output);
        $decisionHash = hash('sha256', $payloadToHash);

        return CoreAiDecisionSnapshot::create([
            'id' => (string) Str::uuid(),
            'decision_code' => 'DEC-'.strtoupper(Str::random(8)),
            'model_name' => $modelName,
            'model_version' => $version,
            'seed' => $seed,
            'input_snapshot' => $inputs,
            'decision_output' => $output,
            'decision_hash' => $decisionHash,
        ]);
    }

    /**
     * 143.2 Autonomous operations ladder & Kill-switch.
     * Test (b): Kill-switch menghentikan auto-execute dalam 1 detik.
     */
    public function activateKillSwitch(string $lineCode): void
    {
        CoreAutonomousOperation::where('line_code', $lineCode)
            ->update([
                'kill_switch_active' => true,
                'kill_switched_at' => Carbon::now(),
            ]);
    }

    public function executeAutonomousAction(string $lineCode, int $autonomyLevel, string $actionName): CoreAutonomousOperation
    {
        $killSwitchActive = CoreAutonomousOperation::where('line_code', $lineCode)
            ->where('kill_switch_active', true)
            ->exists();

        if ($killSwitchActive) {
            throw new RuntimeException("Autonomous action blocked: Kill-switch is active for {$lineCode}.");
        }

        return CoreAutonomousOperation::create([
            'id' => (string) Str::uuid(),
            'operation_code' => 'OP-'.strtoupper(Str::random(8)),
            'line_code' => $lineCode,
            'autonomy_level' => $autonomyLevel,
            'action_name' => $actionName,
            'kill_switch_active' => false,
            'execution_status' => 'EXECUTED',
        ]);
    }

    /**
     * 143.5 AI Governance drift checks.
     * Test (d): Drift check terjadwal & hasil tercatat.
     */
    public function runScheduledDriftCheck(string $modelName, float $dataDrift, float $conceptDrift): CoreAiGovernanceDriftLog
    {
        $requiresRetraining = ($dataDrift > 0.25) || ($conceptDrift > 0.20);

        return CoreAiGovernanceDriftLog::create([
            'id' => (string) Str::uuid(),
            'log_code' => 'DFT-'.strtoupper(Str::random(8)),
            'model_name' => $modelName,
            'data_drift_score' => $dataDrift,
            'concept_drift_score' => $conceptDrift,
            'requires_retraining' => $requiresRetraining,
            'checked_at' => Carbon::now(),
        ]);
    }
}
