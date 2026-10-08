<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * TalentValueOrganizationalOutcomesService (Fase 325)
 *
 * Implements:
 *  - 325.1 Learning, skill & mobility correlation analysis (strictly labeled correlation, guarded causal claims)
 *  - 325.3 Human capital investment prioritization (Training vs Hire vs Automation) with uncertainty margins
 *  - 325.4 Tests: Small cohorts (k < 10) suppressed for k-anonymity privacy, hcm:audit clean
 *  - 325.5 Edge case: Unmeasured outcomes strictly undergo methodology review; claiming unevidenced benefits is rejected
 *  - 325.6 Risk: Small cohorts protected via strict k-anonymity privacy thresholds
 */
class TalentValueOrganizationalOutcomesService
{
    /**
     * Register HCM analytics cohort with k-anonymity privacy suppression threshold (325.1, 325.4, 325.6 Risk).
     */
    public function registerAnalyticsCohort(
        string $cohortCode,
        string $departmentName,
        int $sampleSizeK,
        float $correlationValue
    ): object {
        $cCode = strtoupper($cohortCode);

        // Privacy threshold check 325.4 & 325.6: Cohorts with k < 10 must be suppressed
        $isSuppressed = ($sampleSizeK < 10);

        $id = DB::table('human_capital_analytics_cohorts')->insertGetId([
            'cohort_code' => $cCode,
            'department_name' => strtoupper($departmentName),
            'sample_size_k' => $sampleSizeK,
            'correlation_learning_to_retention' => $correlationValue,
            'is_suppressed_for_privacy' => $isSuppressed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('human_capital_analytics_cohorts')->find($id);
    }

    /**
     * Prioritize and evaluate HCM investment decision with evidence requirement (325.3 & 325.5 Edge Case).
     */
    public function registerInvestmentDecision(
        string $decisionCode,
        string $type,
        float $costUsd,
        float $projectedBenefitUsd,
        float $uncertaintyPct = 15.00,
        bool $hasEmpiricalEvidence = false
    ): object {
        $dCode = strtoupper($decisionCode);
        $t = strtoupper($type);

        if (! in_array($t, ['TRAINING', 'EXTERNAL_HIRE', 'AUTOMATION'], true)) {
            throw new InvalidArgumentException("Invalid investment type '{$type}'.");
        }

        // Edge case 325.5: Claiming benefits without empirical evidence is rejected
        if ($projectedBenefitUsd > 0.0 && ! $hasEmpiricalEvidence) {
            throw new InvalidArgumentException("Methodology review required: Cannot claim financial benefit without measured empirical evidence (325.5).");
        }

        $id = DB::table('human_capital_investment_decisions')->insertGetId([
            'decision_code' => $dCode,
            'investment_type' => $t,
            'cost_usd' => $costUsd,
            'projected_benefit_usd' => $projectedBenefitUsd,
            'uncertainty_margin_pct' => $uncertaintyPct,
            'has_measured_empirical_evidence' => $hasEmpiricalEvidence,
            'claim_approved' => $hasEmpiricalEvidence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('human_capital_investment_decisions')->find($id);
    }

    /**
     * Human Capital Management Organizational Outcomes Audit (`hcm:audit`) (325.4, 325.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Unsuppressed cohorts with sample size k < 10 (privacy breach)
        $privacyBreaches = DB::table('human_capital_analytics_cohorts')
            ->where('sample_size_k', '<', 10)
            ->where('is_suppressed_for_privacy', false)
            ->count();

        // Discrepancy 2: Approved benefit claims without empirical evidence
        $unsubstantiatedClaims = DB::table('human_capital_investment_decisions')
            ->where('claim_approved', true)
            ->where('has_measured_empirical_evidence', false)
            ->count();

        $discrepancies = $privacyBreaches + $unsubstantiatedClaims;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_cohorts' => DB::table('human_capital_analytics_cohorts')->count(),
            'total_investments' => DB::table('human_capital_investment_decisions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
