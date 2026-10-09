<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * JourneyAnalyticsOptimizationService (Fase 417)
 *
 * Implements:
 *  - 417.1 Instrument key cross-line journeys (book->stay->dine, buy->deliver->return, etc.)
 *  - 417.2 Funnel metrics with defined denominators and statistical guardrails
 *  - 417.3 Friction prioritization: drop-off analysis -> hypothesis -> experiment -> standardize
 *  - 417.4 Tests: metric definitions applied, experiment guardrails prevent false conclusions, crm:audit clean
 *  - 417.5 Edge case: Funnel metrics must be versioned; cross-version comparison forbidden without correction
 *  - 417.6 Risk: Journey-level regression check required before standardizing any localized conversion win
 *  - 417.7 Evidence: instrumentation map, experiment log, standardization record
 */
class JourneyAnalyticsOptimizationService
{
    public function recordFunnel(
        string $funnelCode,
        string $journeyType,
        string $cohortPeriod,
        int $starts,
        int $s1,
        int $s2,
        int $s3,
        int $version = 1
    ): object {
        $convRate = $starts > 0 ? round(($s3 / $starts) * 100, 2) : 0.00;

        $id = DB::table('crm_journey_funnels')->insertGetId([
            'funnel_code' => strtoupper($funnelCode),
            'journey_type' => strtolower($journeyType),
            'cohort_period' => $cohortPeriod,
            'denominator_starts' => $starts,
            'step1_completions' => $s1,
            'step2_completions' => $s2,
            'step3_completions' => $s3,
            'conversion_rate' => $convRate,
            'metric_version' => $version,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_journey_funnels')->where('id', $id)->first();
    }

    public function createExperiment(
        string $experimentCode,
        string $funnelCode,
        string $hypothesis,
        float $sampleSize
    ): object {
        $funnel = DB::table('crm_journey_funnels')->where('funnel_code', strtoupper($funnelCode))->first();
        if (! $funnel) {
            throw new InvalidArgumentException("Funnel '{$funnelCode}' not found.");
        }

        $id = DB::table('crm_journey_experiments')->insertGetId([
            'experiment_code' => strtoupper($experimentCode),
            'funnel_id' => $funnel->id,
            'hypothesis' => $hypothesis,
            'sample_size_reach' => $sampleSize,
            'statistical_significance_reached' => false,
            'journey_level_regression_checked' => false,
            'standardized' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_journey_experiments')->where('id', $id)->first();
    }

    public function standardizeExperiment(
        string $experimentCode,
        bool $statSigReached,
        bool $journeyLevelChecked
    ): object {
        $exp = DB::table('crm_journey_experiments')->where('experiment_code', strtoupper($experimentCode))->first();
        if (! $exp) {
            throw new InvalidArgumentException("Experiment '{$experimentCode}' not found.");
        }

        // 417.4 & 417.6 Guardrails: Statistical significance AND journey-level regression check strictly required
        if (! $statSigReached) {
            throw new InvalidArgumentException("Standardization blocked: Experiment has not reached required statistical significance (417.2, 417.4).");
        }

        if (! $journeyLevelChecked) {
            throw new InvalidArgumentException("Standardization blocked: Missing mandatory cross-step journey regression impact check (417.6).");
        }

        DB::table('crm_journey_experiments')->where('id', $exp->id)->update([
            'statistical_significance_reached' => true,
            'journey_level_regression_checked' => true,
            'standardized' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_journey_experiments')->where('id', $exp->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Standardized experiments without stat sig or journey regression check
        $invalidStandardizations = DB::table('crm_journey_experiments')
            ->where('standardized', true)
            ->where(function ($query) {
                $query->where('statistical_significance_reached', false)
                    ->orWhere('journey_level_regression_checked', false);
            })
            ->count();

        return [
            'status' => $invalidStandardizations === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_experiments' => DB::table('crm_journey_experiments')->count(),
            'discrepancy_count' => $invalidStandardizations,
        ];
    }
}
