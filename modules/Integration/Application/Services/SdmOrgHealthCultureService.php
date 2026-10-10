<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * SdmOrgHealthCultureService (Fase 284)
 *
 * Implements:
 *  - 284.1 Culture & values 360 assessment framework
 *  - 284.2 Org Network Analysis (ONA) collaboration silo detection with minimum anonymity thresholds
 *  - 284.3 Deterministic Diversity, Equity & Inclusion (DEI) metrics with governance reporting
 *  - 284.4 & 284.6 Prohibits automated promotion/firing decisions based on culture scores (Human decides with documentation)
 *  - 284.5 Edge case: Small sample sizes (n < anonymity threshold) strictly withhold/suppress ONA publishing
 */
class SdmOrgHealthCultureService
{
    /**
     * Submit culture 360 assessment enforcing non-automated decisions (284.1, 284.4, 284.6).
     */
    public function recordCultureAssessment(
        string $assessmentCode,
        string $employeeId,
        float $valuesAlignmentScore,
        float $collaborationScore
    ): object {
        $code = strtoupper($assessmentCode);
        $composite = round(($valuesAlignmentScore * 0.5) + ($collaborationScore * 0.5), 2);

        $id = DB::table('sdm_culture_assessments')->insertGetId([
            'assessment_code' => $code,
            'employee_id' => strtoupper($employeeId),
            'values_alignment_score' => $valuesAlignmentScore,
            'collaboration_score' => $collaborationScore,
            'composite_culture_score' => $composite,
            'is_auto_decision_prohibited' => true, // 284.6
            'promotion_decision' => 'PENDING_HUMAN_REVIEW',
            'human_reviewer_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('sdm_culture_assessments')->find($id);
    }

    /**
     * Human reviewer records documented promotion/talent decision (284.6).
     */
    public function recordHumanTalentDecision(
        string $assessmentCode,
        string $reviewerId,
        string $decision // APPROVED, REJECTED
    ): object {
        $code = strtoupper($assessmentCode);

        DB::table('sdm_culture_assessments')
            ->where('assessment_code', $code)
            ->update([
                'promotion_decision' => strtoupper($decision),
                'human_reviewer_id' => strtoupper($reviewerId),
                'updated_at' => now(),
            ]);

        return (object) DB::table('sdm_culture_assessments')->where('assessment_code', $code)->first();
    }

    /**
     * Publish ONA metrics enforcing strict anonymity threshold (284.2, 284.4, 284.5 Edge Case).
     */
    public function recordOnaCluster(
        string $clusterCode,
        string $departmentName,
        int $sampleSizeN,
        float $siloIndexScore,
        int $anonymityMinN = 10
    ): object {
        $code = strtoupper($clusterCode);

        // Edge case 284.5: If sample size n < anonymity threshold, data is strictly withheld to protect employee identity
        $isWithheld = ($sampleSizeN < $anonymityMinN);
        $interlockTriggered = ($siloIndexScore >= 0.70 && ! $isWithheld);

        DB::table('sdm_ona_metrics')->updateOrInsert(
            ['cluster_code' => $code],
            [
                'department_name' => strtoupper($departmentName),
                'sample_size_n' => $sampleSizeN,
                'anonymity_threshold_min_n' => $anonymityMinN,
                'silo_index_score' => $siloIndexScore,
                'is_data_withheld' => $isWithheld,
                'interlock_intervention_triggered' => $interlockTriggered,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('sdm_ona_metrics')->where('cluster_code', $code)->first();
    }

    /**
     * Compute and submit deterministic DEI metrics (284.3 & 284.4).
     */
    public function recordDeiMetrics(
        string $metricCode,
        string $period,
        float $femaleLeadershipPct,
        float $regionalTalentPct
    ): object {
        $code = strtoupper($metricCode);

        $id = DB::table('sdm_dei_metrics')->insertGetId([
            'metric_code' => $code,
            'reporting_period' => $period,
            'female_leadership_pct' => $femaleLeadershipPct,
            'regional_talent_representation_pct' => $regionalTalentPct,
            'governance_report_submitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('sdm_dei_metrics')->find($id);
    }

    /**
     * SDM Org Health & Culture Platform Audit (`hcm:audit`) (284.4, 284.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Small sample ONA data (n < threshold) not marked withheld
        $leakedOnaData = DB::table('sdm_ona_metrics')
            ->whereRaw('sample_size_n < anonymity_threshold_min_n')
            ->where('is_data_withheld', false)
            ->count();

        // Discrepancy 2: Decided promotions missing human reviewer ID (auto-decided violation)
        $unaccountableDecisions = DB::table('sdm_culture_assessments')
            ->where('promotion_decision', '!=', 'PENDING_HUMAN_REVIEW')
            ->whereNull('human_reviewer_id')
            ->count();

        // Discrepancy 3: DEI metrics not submitted to governance
        $unsubmittedDei = DB::table('sdm_dei_metrics')
            ->where('governance_report_submitted', false)
            ->count();

        $discrepancies = $leakedOnaData + $unaccountableDecisions + $unsubmittedDei;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_assessments' => DB::table('sdm_culture_assessments')->count(),
            'total_ona_clusters' => DB::table('sdm_ona_metrics')->count(),
            'total_dei_metrics' => DB::table('sdm_dei_metrics')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
