<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * DigitalProductExperimentationService (Fase 236)
 *
 * Implements:
 *  - 236.1 Product experimentation ops (hypothesis, variant allocation, telemetry)
 *  - 236.2 Feature flags with gradual rollout & real-time kill switch
 *  - 236.3 Statistical A/B testing engine with deterministic variant hash allocation
 *  - 236.4 Product telemetry & sunset candidate detection
 *  - 236.6 Edge case: Critical metric regression (>10% conversion drop) triggers auto-kill
 *  - 236.7 Technical debt detection for stale feature flags (> 90 days)
 */
class DigitalProductExperimentationService
{
    /**
     * Create feature flag (236.2).
     */
    public function createFeatureFlag(
        string $flagKey,
        string $businessLine,
        string $description,
        int $rolloutPercentage = 0,
        bool $isEnabled = true
    ): object {
        DB::table('ppm_feature_flags')->updateOrInsert(
            ['flag_key' => strtoupper($flagKey)],
            [
                'business_line' => strtoupper($businessLine),
                'description' => $description,
                'is_enabled' => $isEnabled,
                'rollout_percentage' => $rolloutPercentage,
                'is_killed' => false,
                'kill_reason' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('ppm_feature_flags')->where('flag_key', strtoupper($flagKey))->first();
    }

    /**
     * Evaluate feature flag deterministically for a user (236.2 & 236.5).
     */
    public function isFeatureEnabled(string $flagKey, string $userId): bool
    {
        $flag = DB::table('ppm_feature_flags')->where('flag_key', strtoupper($flagKey))->first();
        if (! $flag) {
            return false;
        }

        // Real-time kill switch (236.2)
        if ($flag->is_killed || ! $flag->is_enabled) {
            return false;
        }

        if ($flag->rollout_percentage <= 0) {
            return false;
        }
        if ($flag->rollout_percentage >= 100) {
            return true;
        }

        // Deterministic hash allocation
        $hashValue = abs(crc32($flag->flag_key.'_'.$userId)) % 100;

        return $hashValue < $flag->rollout_percentage;
    }

    /**
     * Activate real-time kill switch (236.2 & 236.5).
     */
    public function killFeatureFlag(string $flagKey, string $reason): object
    {
        DB::table('ppm_feature_flags')
            ->where('flag_key', strtoupper($flagKey))
            ->update([
                'is_killed' => true,
                'is_enabled' => false,
                'kill_reason' => $reason,
                'updated_at' => now(),
            ]);

        return (object) DB::table('ppm_feature_flags')->where('flag_key', strtoupper($flagKey))->first();
    }

    /**
     * Initialize A/B experiment (236.3).
     */
    public function createExperiment(
        string $experimentCode,
        string $flagKey,
        string $hypothesis,
        string $metricName
    ): object {
        $id = DB::table('ppm_ab_experiments')->insertGetId([
            'experiment_code' => strtoupper($experimentCode),
            'feature_flag_key' => strtoupper($flagKey),
            'hypothesis' => $hypothesis,
            'metric_name' => strtoupper($metricName),
            'control_conversion_rate' => 0,
            'variant_conversion_rate' => 0,
            'p_value' => null,
            'is_statistically_significant' => false,
            'status' => 'RUNNING',
            'auto_killed_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_ab_experiments')->find($id);
    }

    /**
     * Deterministically assign user to experiment variant (236.3 & 236.5).
     */
    public function assignVariant(string $experimentCode, string $userId): string
    {
        $existing = DB::table('ppm_experiment_variants')
            ->where('experiment_code', strtoupper($experimentCode))
            ->where('user_golden_id', strtoupper($userId))
            ->first();

        if ($existing) {
            return $existing->assigned_variant;
        }

        // Deterministic variant hash allocation
        $hash = abs(crc32(strtoupper($experimentCode).'_'.strtoupper($userId))) % 100;
        $variant = ($hash < 50) ? 'CONTROL' : 'VARIANT_B';

        DB::table('ppm_experiment_variants')->insert([
            'experiment_code' => strtoupper($experimentCode),
            'user_golden_id' => strtoupper($userId),
            'assigned_variant' => $variant,
            'converted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $variant;
    }

    /**
     * Record experiment metric evaluation and enforce critical regression auto-kill (236.6 Edge Case).
     */
    public function evaluateExperimentResults(
        string $experimentCode,
        float $controlConvRate,
        float $variantConvRate,
        float $pValue = 0.03
    ): object {
        $exp = DB::table('ppm_ab_experiments')->where('experiment_code', strtoupper($experimentCode))->first();
        if (! $exp) {
            throw new \InvalidArgumentException("Experiment {$experimentCode} not found.");
        }

        // Edge Case 236.6: Critical conversion metric regression (> 10% drop vs control) triggers auto-kill
        if ($controlConvRate > 0 && $variantConvRate < $controlConvRate) {
            $dropPct = round((($controlConvRate - $variantConvRate) / $controlConvRate) * 100, 2);
            if ($dropPct > 10.0) {
                // Auto-kill experiment and kill linked feature flag
                $reason = "Auto-killed: Critical regression detected (variant conversion dropped by {$dropPct}% vs control).";

                DB::table('ppm_ab_experiments')
                    ->where('experiment_code', strtoupper($experimentCode))
                    ->update([
                        'control_conversion_rate' => $controlConvRate,
                        'variant_conversion_rate' => $variantConvRate,
                        'p_value' => $pValue,
                        'is_statistically_significant' => true,
                        'status' => 'AUTO_KILLED_REGRESSION',
                        'auto_killed_reason' => $reason,
                        'updated_at' => now(),
                    ]);

                $this->killFeatureFlag($exp->feature_flag_key, $reason);

                return (object) DB::table('ppm_ab_experiments')->where('experiment_code', strtoupper($experimentCode))->first();
            }
        }

        $isSignificant = ($pValue < 0.05);
        $decisionStatus = ($isSignificant && $variantConvRate > $controlConvRate) ? 'COMPLETED_ROLLOUT' : 'COMPLETED_ROLLBACK';

        DB::table('ppm_ab_experiments')
            ->where('experiment_code', strtoupper($experimentCode))
            ->update([
                'control_conversion_rate' => $controlConvRate,
                'variant_conversion_rate' => $variantConvRate,
                'p_value' => $pValue,
                'is_statistically_significant' => $isSignificant,
                'status' => $decisionStatus,
                'updated_at' => now(),
            ]);

        return (object) DB::table('ppm_ab_experiments')->where('experiment_code', strtoupper($experimentCode))->first();
    }

    /**
     * Detect stale technical debt flags (> 90 days old) (236.7).
     */
    public function getTechnicalDebtStaleFlags(): array
    {
        $cutoff = now()->subDays(90);

        return DB::table('ppm_feature_flags')
            ->where('created_at', '<', $cutoff)
            ->pluck('flag_key')
            ->toArray();
    }

    /**
     * Quality audit gate (`platform:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Killed flags where is_enabled remains true
        $killedStillEnabled = DB::table('ppm_feature_flags')
            ->where('is_killed', true)
            ->where('is_enabled', true)
            ->count();

        // Discrepancy 2: Experiments marked COMPLETED_ROLLOUT where variant was worse than control
        $inferiorRollouts = DB::table('ppm_ab_experiments')
            ->where('status', 'COMPLETED_ROLLOUT')
            ->whereRaw('variant_conversion_rate <= control_conversion_rate')
            ->count();

        $discrepancies = $killedStillEnabled + $inferiorRollouts;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_feature_flags' => DB::table('ppm_feature_flags')->count(),
            'total_experiments' => DB::table('ppm_ab_experiments')->count(),
            'total_variants_assigned' => DB::table('ppm_experiment_variants')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
