<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\PerformanceEngagementAnalyticsService;
use Tests\TestCase;

/**
 * Fase 226 — SDM: Performance, Engagement & People Analytics Tests
 *
 * Covers:
 *  (a) Performance calibration cycle with mandatory justification & bonus linkage
 *  (b) Edge Case 226.7: Engagement survey anonymity threshold enforcement (suppression for n < threshold)
 *  (c) People analytics deterministic attrition risk & automated outreach trigger
 *  (d) Manager effectiveness index & promotion recommendation
 *  (e) Quality audit hcm:audit clean with 0 discrepancies
 */
class PerformanceEngagementAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected PerformanceEngagementAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PerformanceEngagementAnalyticsService::class);
    }

    /**
     * (a) Performance calibration requiring written justification (226.1 & 226.6).
     */
    public function test_performance_calibration_and_bonus_link(): void
    {
        $review = $this->service->createReview('EMP-DEV-01', '2026-H2', 3.5);
        $this->assertSame('SUBMITTED', $review->status);

        // Blank or short justification -> Throws exception
        try {
            $this->service->calibrateRating($review->review_code, 4.8, 'ok', 'HR_COMMITTEE');
            $this->fail('Expected exception for missing calibration justification.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('justification required', $e->getMessage());
        }

        // Valid calibration
        $calibrated = $this->service->calibrateRating(
            $review->review_code,
            4.6,
            'Exceptional delivery of cloud infrastructure overhaul',
            'HR_COMMITTEE'
        );
        $this->assertSame('CALIBRATED', $calibrated->status);
        $this->assertEquals(4.6, (float) $calibrated->calibrated_rating);
        $this->assertEquals(1.50, (float) $calibrated->bonus_multiplier);
    }

    /**
     * (b) Edge Case 226.7: Survey anonymity suppression for small groups.
     */
    public function test_survey_anonymity_threshold_protection(): void
    {
        // Survey with min threshold of 5
        $this->service->createSurvey('SRV-2026-Q3', 'Quarterly Pulse', '2026-Q3', 5);

        // Submit only 2 responses for team 'SECRET_TEAM'
        $this->service->submitSurveyResponse('SRV-2026-Q3', 'SECRET_TEAM', 4.0, 'CULTURE');
        $this->service->submitSurveyResponse('SRV-2026-Q3', 'SECRET_TEAM', 3.5, 'LEADERSHIP');

        // Requesting report for team with 2 responses (< 5) -> Suppressed with Exception
        try {
            $this->service->getTeamSurveyReport('SRV-2026-Q3', 'SECRET_TEAM');
            $this->fail('Expected exception for anonymity threshold suppression.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('strictly below the minimum anonymity threshold', $e->getMessage());
        }

        // Add 3 more responses to reach 5
        $this->service->submitSurveyResponse('SRV-2026-Q3', 'SECRET_TEAM', 4.5, 'CULTURE');
        $this->service->submitSurveyResponse('SRV-2026-Q3', 'SECRET_TEAM', 4.0, 'WORKLOAD');
        $this->service->submitSurveyResponse('SRV-2026-Q3', 'SECRET_TEAM', 4.0, 'COMPENSATION');

        // Now report is accessible
        $report = $this->service->getTeamSurveyReport('SRV-2026-Q3', 'SECRET_TEAM');
        $this->assertSame(5, $report['response_count']);
        $this->assertEquals(4.0, (float) $report['average_score']);
        $this->assertTrue($report['anonymity_preserved']);
    }

    /**
     * (c) People analytics attrition risk modeling (226.3).
     */
    public function test_attrition_risk_and_retention_outreach(): void
    {
        // High overtime (60h), low rating (2.5), short tenure (6m) -> High Risk
        $metric = $this->service->computeAttritionRisk('EMP-RISK-01', '2026-10', 60.0, 6, 2.5);
        $this->assertGreaterThanOrEqual(0.70, (float) $metric->predicted_attrition_risk);
        $this->assertTrue((bool) $metric->retention_outreach_triggered);

        // Low risk employee
        $safeMetric = $this->service->computeAttritionRisk('EMP-SAFE-01', '2026-10', 5.0, 36, 4.5);
        $this->assertLessThan(0.70, (float) $safeMetric->predicted_attrition_risk);
        $this->assertFalse((bool) $safeMetric->retention_outreach_triggered);
    }

    /**
     * (d) Manager effectiveness scoring (226.4).
     */
    public function test_manager_effectiveness_evaluation(): void
    {
        $mgr = $this->service->recordManagerEffectiveness(
            'MGR-TECH-01',
            90.0, // Delivery (40%) -> 36
            85.0, // Engagement (30%) -> 25.5
            88.0, // Growth (30%) -> 26.4
            'Strong leadership and talent mentor'
        );
        $this->assertEquals(87.9, (float) $mgr->composite_score);
        $this->assertSame('RECOMMENDED', $mgr->promotion_recommendation);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_performance_engagement_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
