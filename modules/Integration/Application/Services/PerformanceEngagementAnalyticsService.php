<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PerformanceEngagementAnalyticsService (Fase 226)
 *
 * Implements:
 *  - 226.1 Performance cycle, calibration review, rating determination & bonus multiplier
 *  - 226.2 Engagement survey pulse & driver analytics
 *  - 226.3 People analytics: attrition risk modeling & automated retention outreach trigger
 *  - 226.4 Manager effectiveness index & evidence-based promotion recommendation
 *  - 226.6 Edge case: Calibration requires strict written justification and auditor identity
 *  - 226.7 Survey anonymity enforcement: Groups below privacy threshold (n < threshold) are strictly suppressed
 */
class PerformanceEngagementAnalyticsService
{
    /**
     * Create initial performance review cycle entry (226.1).
     */
    public function createReview(string $employeeId, string $cyclePeriod, float $initialRating): object
    {
        $code = 'REV-'.strtoupper(Str::random(8));

        $id = DB::table('hcm_performance_reviews')->insertGetId([
            'review_code' => $code,
            'employee_id' => strtoupper($employeeId),
            'cycle_period' => strtoupper($cyclePeriod),
            'initial_rating' => $initialRating,
            'calibrated_rating' => null,
            'calibration_justification' => null,
            'calibrated_by' => null,
            'bonus_multiplier' => 1.00,
            'status' => 'SUBMITTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_performance_reviews')->find($id);
    }

    /**
     * Calibrate performance rating (226.1 & 226.6 Edge Case: Mandates justification).
     */
    public function calibrateRating(
        string $reviewCode,
        float $newRating,
        string $justification,
        string $calibratedBy
    ): object {
        if (trim($justification) === '' || strlen(trim($justification)) < 10) {
            throw new \InvalidArgumentException('Calibration justification required (minimum 10 characters) for audit trail.');
        }

        // Determine bonus multiplier based on calibrated rating
        $multiplier = 1.00;
        if ($newRating >= 4.5) {
            $multiplier = 1.50;
        } elseif ($newRating >= 4.0) {
            $multiplier = 1.25;
        } elseif ($newRating < 3.0) {
            $multiplier = 0.50;
        }

        DB::table('hcm_performance_reviews')
            ->where('review_code', strtoupper($reviewCode))
            ->update([
                'calibrated_rating' => $newRating,
                'calibration_justification' => trim($justification),
                'calibrated_by' => strtoupper($calibratedBy),
                'bonus_multiplier' => $multiplier,
                'status' => 'CALIBRATED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('hcm_performance_reviews')->where('review_code', strtoupper($reviewCode))->first();
    }

    /**
     * Create engagement survey with anonymity protection threshold (226.2 & 226.7).
     */
    public function createSurvey(
        string $surveyCode,
        string $title,
        string $cyclePeriod,
        int $minAnonymityThreshold = 5
    ): object {
        $id = DB::table('hcm_engagement_surveys')->insertGetId([
            'survey_code' => strtoupper($surveyCode),
            'title' => $title,
            'cycle_period' => strtoupper($cyclePeriod),
            'min_anonymity_threshold' => $minAnonymityThreshold,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_engagement_surveys')->find($id);
    }

    /**
     * Submit an anonymous response to engagement survey.
     */
    public function submitSurveyResponse(
        string $surveyCode,
        string $teamCohort,
        float $score,
        string $driverCategory
    ): void {
        DB::table('hcm_engagement_responses')->insert([
            'survey_code' => strtoupper($surveyCode),
            'team_cohort' => strtoupper($teamCohort),
            'score' => $score,
            'driver_category' => strtoupper($driverCategory),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Aggregate survey results enforcing strict anonymity threshold (226.5 & 226.7).
     */
    public function getTeamSurveyReport(string $surveyCode, string $teamCohort): array
    {
        $survey = DB::table('hcm_engagement_surveys')->where('survey_code', strtoupper($surveyCode))->first();
        if (! $survey) {
            throw new \InvalidArgumentException("Survey {$surveyCode} not found.");
        }

        $count = DB::table('hcm_engagement_responses')
            ->where('survey_code', strtoupper($surveyCode))
            ->where('team_cohort', strtoupper($teamCohort))
            ->count();

        // 226.7 Survey anonymity: threshold kelompok kecil ditegakkan agar responden aman
        if ($count < $survey->min_anonymity_threshold) {
            throw new \RuntimeException(
                "Anonymity privacy suppression: Team cohort {$teamCohort} has {$count} responses, which is strictly below the minimum anonymity threshold of {$survey->min_anonymity_threshold}."
            );
        }

        $avgScore = (float) DB::table('hcm_engagement_responses')
            ->where('survey_code', strtoupper($surveyCode))
            ->where('team_cohort', strtoupper($teamCohort))
            ->avg('score');

        return [
            'survey_code' => strtoupper($surveyCode),
            'team_cohort' => strtoupper($teamCohort),
            'response_count' => $count,
            'average_score' => round($avgScore, 2),
            'anonymity_preserved' => true,
        ];
    }

    /**
     * Compute predictive attrition risk (226.3).
     */
    public function computeAttritionRisk(
        string $employeeId,
        string $period,
        float $overtimeHours,
        int $tenureMonths,
        float $lastRating
    ): object {
        // Base risk calculation
        $risk = 0.10;

        if ($overtimeHours > 40) {
            $risk += 0.35;
        }
        if ($lastRating < 3.0) {
            $risk += 0.30;
        }
        if ($tenureMonths < 12) {
            $risk += 0.15;
        }

        $risk = min(0.95, round($risk, 2));
        $triggerOutreach = ($risk >= 0.70);

        $id = DB::table('hcm_people_analytics_metrics')->insertGetId([
            'employee_id' => strtoupper($employeeId),
            'period' => strtoupper($period),
            'overtime_hours' => $overtimeHours,
            'tenure_months' => $tenureMonths,
            'last_rating' => $lastRating,
            'predicted_attrition_risk' => $risk,
            'retention_outreach_triggered' => $triggerOutreach,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('hcm_people_analytics_metrics')->find($id);
    }

    /**
     * Record manager effectiveness index (226.4).
     */
    public function recordManagerEffectiveness(
        string $managerId,
        float $deliveryScore,
        float $engagementScore,
        float $growthScore,
        ?string $documentedJudgment = null
    ): object {
        $composite = round(($deliveryScore * 0.40) + ($engagementScore * 0.30) + ($growthScore * 0.30), 2);

        $recommendation = 'DEVELOPMENT_NEEDED';
        if ($composite >= 85.0) {
            $recommendation = 'RECOMMENDED';
        } elseif ($composite < 60.0) {
            $recommendation = 'NOT_READY';
        }

        DB::table('hcm_manager_effectiveness_scores')->updateOrInsert(
            ['manager_id' => strtoupper($managerId)],
            [
                'delivery_score' => $deliveryScore,
                'engagement_score' => $engagementScore,
                'growth_score' => $growthScore,
                'composite_score' => $composite,
                'promotion_recommendation' => $recommendation,
                'documented_judgment' => $documentedJudgment,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('hcm_manager_effectiveness_scores')->where('manager_id', strtoupper($managerId))->first();
    }

    /**
     * Quality audit gate (`hcm:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Calibrated reviews without written justification
        $unjustifiedCalibrations = DB::table('hcm_performance_reviews')
            ->where('status', 'CALIBRATED')
            ->where(function ($q) {
                $q->whereNull('calibration_justification')
                    ->orWhereNull('calibrated_by');
            })
            ->count();

        // Discrepancy 2: Invalid attrition risk boundaries (< 0 or > 1)
        $invalidRiskValues = DB::table('hcm_people_analytics_metrics')
            ->where(function ($q) {
                $q->where('predicted_attrition_risk', '<', 0)
                    ->orWhere('predicted_attrition_risk', '>', 1.0);
            })
            ->count();

        $discrepancies = $unjustifiedCalibrations + $invalidRiskValues;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_performance_reviews' => DB::table('hcm_performance_reviews')->count(),
            'total_survey_responses' => DB::table('hcm_engagement_responses')->count(),
            'total_attrition_assessments' => DB::table('hcm_people_analytics_metrics')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
