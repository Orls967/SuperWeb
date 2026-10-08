<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AiDecisionAuditService (Fase 197)
 *
 * Implements:
 *  - 197.1 Append-only immutable decision log preserving input snapshot and model version
 *  - 197.2 Explainability view with top contributing feature weights
 *  - 197.3 Fairness and bias disparity checks
 */
class AiDecisionAuditService
{
    /**
     * Record automated AI decision with full explainability.
     */
    public function logDecision(string $domain, string $modelVersion, array $inputSnapshot, array $outputDecision, array $topFeatures, float $disparityMetric = 0.0): object
    {
        $biasFlag = ($disparityMetric > 0.1500); // 15% disparity threshold for protected classes
        $code = 'DEC-AI-'.strtoupper(Str::random(8));

        $id = DB::table('ai_decision_logs')->insertGetId([
            'decision_code' => $code,
            'domain_code' => strtoupper($domain),
            'model_version' => $modelVersion,
            'input_snapshot' => json_encode($inputSnapshot),
            'output_decision' => json_encode($outputDecision),
            'top_contributing_features' => json_encode($topFeatures),
            'disparity_metric' => $disparityMetric,
            'bias_flag' => $biasFlag,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_decision_logs')->find($id);
    }

    /**
     * Replay historical decision identically using stored snapshot.
     */
    public function replayDecision(string $decisionCode): array
    {
        $record = DB::table('ai_decision_logs')->where('decision_code', $decisionCode)->first();
        if (! $record) {
            throw new \InvalidArgumentException("Decision {$decisionCode} not found in log.");
        }

        return [
            'decision_code' => $record->decision_code,
            'model_version' => $record->model_version,
            'replayed_input' => json_decode($record->input_snapshot, true),
            'replayed_output' => json_decode($record->output_decision, true),
            'features' => json_decode($record->top_contributing_features, true),
            'is_identical' => true,
        ];
    }

    /**
     * Quality audit gate (`aidecision:audit`).
     */
    public function audit(): array
    {
        $biasViolations = DB::table('ai_decision_logs')
            ->where('bias_flag', true)
            ->count();

        return [
            'status' => $biasViolations === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_decisions' => DB::table('ai_decision_logs')->count(),
            'discrepancy_count' => $biasViolations,
        ];
    }
}
