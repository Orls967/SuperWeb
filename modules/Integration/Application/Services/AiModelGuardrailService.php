<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AiModelGuardrailService (Fase 195)
 *
 * Implements:
 *  - 195.1 Centralized model registry with evaluation harness gating & rollback pointers
 *  - 195.3 Input/output guardrails detecting prompt injection and redacting PII before inference
 */
class AiModelGuardrailService
{
    /**
     * Register model version with training data hash and evaluation metrics.
     * Enforces evaluation threshold (AUC >= 0.80) before approval.
     */
    public function registerModel(string $domain, string $version, string $trainingHash, float $evalScoreAuc, ?string $rollbackVer = null): object
    {
        $code = 'MDL-'.strtoupper($domain).'-V'.str_replace('.', '_', $version);
        $approved = ($evalScoreAuc >= 0.80);

        DB::table('ai_model_registry')->updateOrInsert(
            ['model_code' => $code],
            [
                'domain_code' => strtoupper($domain),
                'version' => $version,
                'training_data_hash' => $trainingHash,
                'eval_score_auc' => $evalScoreAuc,
                'rollback_version' => $rollbackVer,
                'is_approved_for_release' => $approved,
                'is_active' => $approved,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ai_model_registry')->where('model_code', $code)->first();
    }

    /**
     * Rollback model to previous stable version.
     */
    public function rollbackModel(string $modelCode): object
    {
        $current = DB::table('ai_model_registry')->where('model_code', $modelCode)->first();
        if (! $current || ! $current->rollback_version) {
            throw new \RuntimeException("Rollback failed: No rollback pointer defined for model {$modelCode}.");
        }

        $prevCode = 'MDL-'.$current->domain_code.'-V'.str_replace('.', '_', $current->rollback_version);
        $prev = DB::table('ai_model_registry')->where('model_code', $prevCode)->first();
        if (! $prev) {
            throw new \RuntimeException("Rollback failed: Previous model {$prevCode} not found in registry.");
        }

        // Deactivate current, activate previous
        DB::table('ai_model_registry')->where('model_code', $modelCode)->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('ai_model_registry')->where('model_code', $prevCode)->update(['is_active' => true, 'updated_at' => now()]);

        return (object) DB::table('ai_model_registry')->where('model_code', $prevCode)->first();
    }

    /**
     * Inspect user input prompt against guardrails.
     * Rejects prompt injection patterns and redacts PII.
     */
    public function evaluateGuardrail(string $domain, string $rawInput): object
    {
        $code = 'GRD-'.strtoupper(Str::random(8));

        // Detect prompt injection keywords
        $injectionPatterns = [
            'ignore previous instructions',
            'system prompt',
            'drop database',
            'jailbreak',
            'dan mode',
        ];

        $lowerInput = strtolower($rawInput);
        $isInjection = false;
        foreach ($injectionPatterns as $pattern) {
            if (str_contains($lowerInput, $pattern)) {
                $isInjection = true;
                break;
            }
        }

        if ($isInjection) {
            $id = DB::table('ai_guardrail_logs')->insertGetId([
                'log_code' => $code,
                'domain_code' => strtoupper($domain),
                'sanitized_input' => '[BLOCKED_INJECTION]',
                'injection_detected' => true,
                'pii_redacted' => true,
                'status' => 'BLOCKED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new \RuntimeException('AI Guardrail triggered: Prompt injection pattern detected. Request blocked.');
        }

        // PII redaction
        $clean = preg_replace('/(\+62|08)[0-9]{8,11}/', '[REDACTED_PHONE]', $rawInput);
        $clean = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[REDACTED_EMAIL]', $clean);

        $id = DB::table('ai_guardrail_logs')->insertGetId([
            'log_code' => $code,
            'domain_code' => strtoupper($domain),
            'sanitized_input' => $clean,
            'injection_detected' => false,
            'pii_redacted' => true,
            'status' => 'PASSED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_guardrail_logs')->find($id);
    }

    /**
     * Quality audit gate (`aimodel:audit`).
     */
    public function audit(): array
    {
        $unapprovedActiveModels = DB::table('ai_model_registry')
            ->where('is_active', true)
            ->where('is_approved_for_release', false)
            ->count();

        return [
            'status' => $unapprovedActiveModels === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_models' => DB::table('ai_model_registry')->count(),
            'total_guardrail_checks' => DB::table('ai_guardrail_logs')->count(),
            'discrepancy_count' => $unapprovedActiveModels,
        ];
    }
}
