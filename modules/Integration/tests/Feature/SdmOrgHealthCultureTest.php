<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\SdmOrgHealthCultureService;
use Tests\TestCase;

class SdmOrgHealthCultureTest extends TestCase
{
    use RefreshDatabase;

    protected SdmOrgHealthCultureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SdmOrgHealthCultureService::class);
    }

    public function test_culture_assessment_strictly_requires_human_reviewer_for_decisions(): void
    {
        // 1. Submit culture assessment: auto-decision strictly prohibited (284.1, 284.4, 284.6)
        $assessment = $this->service->recordCultureAssessment(
            assessmentCode: 'CULT-2026-ENG-01',
            employeeId: 'EMP-LEAD-DEV-99',
            valuesAlignmentScore: 92.0,
            collaborationScore: 88.0
        );
        $this->assertTrue((bool) $assessment->is_auto_decision_prohibited);
        $this->assertEquals('PENDING_HUMAN_REVIEW', $assessment->promotion_decision);
        $this->assertNull($assessment->human_reviewer_id);

        // 2. Human reviewer decides and documents review (284.6)
        $decided = $this->service->recordHumanTalentDecision(
            assessmentCode: 'CULT-2026-ENG-01',
            reviewerId: 'VP_ENG_HENDRA',
            decision: 'APPROVED'
        );
        $this->assertEquals('APPROVED', $decided->promotion_decision);
        $this->assertEquals('VP_ENG_HENDRA', $decided->human_reviewer_id);
    }

    public function test_ona_metrics_enforce_anonymity_threshold(): void
    {
        // 1. Normal large sample cluster (n = 45 >= 10): data published & interlock triggered on high silo (284.2 & 284.4)
        $largeCluster = $this->service->recordOnaCluster(
            clusterCode: 'ONA-MINING-SITE-EAST',
            departmentName: 'MINING_OPS',
            sampleSizeN: 45,
            siloIndexScore: 0.82,
            anonymityMinN: 10
        );
        $this->assertFalse((bool) $largeCluster->is_data_withheld);
        $this->assertTrue((bool) $largeCluster->interlock_intervention_triggered);

        // 2. Small sample size cluster (n = 4 < 10): data strictly withheld to protect individual privacy (284.4 & 284.5 Edge Case)
        $smallCluster = $this->service->recordOnaCluster(
            clusterCode: 'ONA-SMALL-LAB-TEAM',
            departmentName: 'METALLURGY_LAB',
            sampleSizeN: 4,
            siloIndexScore: 0.85,
            anonymityMinN: 10
        );
        $this->assertTrue((bool) $smallCluster->is_data_withheld);
        $this->assertFalse((bool) $smallCluster->interlock_intervention_triggered); // Interlock suppressed when withheld
    }

    public function test_dei_metrics_deterministic_and_governance_report(): void
    {
        // DEI metrics submitted to governance (284.3 & 284.4)
        $dei = $this->service->recordDeiMetrics(
            metricCode: 'DEI-2026-ANNUAL',
            period: '2026-FY',
            femaleLeadershipPct: 34.5,
            regionalTalentPct: 48.0
        );

        $this->assertTrue((bool) $dei->governance_report_submitted);
        $this->assertEquals(34.5, (float) $dei->female_leadership_pct);
    }

    public function test_sdm_org_health_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordCultureAssessment('CULT-AUD', 'EMP-1', 90.0, 90.0);
        $this->service->recordHumanTalentDecision('CULT-AUD', 'DIR-1', 'APPROVED');
        $this->service->recordOnaCluster('ONA-AUD', 'DEPT', 20, 0.5, 10);
        $this->service->recordDeiMetrics('DEI-AUD', '2026', 30.0, 40.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: small sample ONA data leaked without being withheld
        DB::table('sdm_ona_metrics')->insert([
            'cluster_code' => 'ONA-LEAKED-PRIVACY',
            'department_name' => 'SECRET_OFFICE',
            'sample_size_n' => 3, // < 10!
            'anonymity_threshold_min_n' => 10,
            'silo_index_score' => 0.5,
            'is_data_withheld' => false, // Discrepancy!
            'interlock_intervention_triggered' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
