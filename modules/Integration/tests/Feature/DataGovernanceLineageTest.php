<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DataGovernanceLineageService;
use Tests\TestCase;

class DataGovernanceLineageTest extends TestCase
{
    use RefreshDatabase;

    protected DataGovernanceLineageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DataGovernanceLineageService::class);
    }

    public function test_domain_onboarding_steward_guard(): void
    {
        // 1. Success with steward
        $domain = $this->service->onboardDomain(
            domainName: 'HEALTHCARE',
            stewardId: 'STEWARD-DR-ALICE',
            retentionDays: 730,
            classification: 'RESTRICTED'
        );

        $this->assertEquals('HEALTHCARE', $domain->domain_name);
        $this->assertEquals('STEWARD-DR-ALICE', $domain->steward_id);
        $this->assertEquals(730, (int) $domain->retention_days);

        // 2. Reject domain onboarding without steward (241.6 Edge Case)
        $this->expectException(InvalidArgumentException::class);
        $this->service->onboardDomain('MINING', null);
    }

    public function test_data_quality_evaluation_quarantine_and_ticketing(): void
    {
        // 1. High DQ Score (no quarantine, no ticket)
        $cleanEvaluation = $this->service->evaluateDataQuality(
            domainName: 'FINANCIAL',
            datasetName: 'general_ledger_entries',
            ruleType: 'COMPLETENESS',
            dqScorePct: 98.50,
            failedRecordCount: 0
        );
        $this->assertEquals(98.50, (float) $cleanEvaluation->dq_score_pct);
        $this->assertNull($cleanEvaluation->owner_ticket_code);

        // 2. Low DQ Score & bad records quarantined with owner ticket (241.2)
        $flawedEvaluation = $this->service->evaluateDataQuality(
            domainName: 'CUSTOMER',
            datasetName: 'user_addresses',
            ruleType: 'VALIDITY',
            dqScorePct: 75.00,
            failedRecordCount: 42
        );
        $this->assertEquals(75.00, (float) $flawedEvaluation->dq_score_pct);
        $this->assertEquals(42, (int) $flawedEvaluation->quarantined_record_count);
        $this->assertNotNull($flawedEvaluation->owner_ticket_code);
        $this->assertStringStartsWith('TKT-DQ-', $flawedEvaluation->owner_ticket_code);
    }

    public function test_column_level_lineage_and_impact_analysis(): void
    {
        // Register lineage for orders.total_amount_usd
        $this->service->registerLineage(
            sourceColumn: 'orders.total_amount_usd',
            transformOperation: 'SUM_WITH_FX_CONVERSION',
            targetArtifact: 'dashboard.executive_revenue_kpi',
            consumerModule: 'FINANCE_REPORTING'
        );
        $this->service->registerLineage(
            sourceColumn: 'orders.total_amount_usd',
            transformOperation: 'SEGMENT_TIER_AGGREGATION',
            targetArtifact: 'model.customer_lifetime_value',
            consumerModule: 'MARKETING_AUTOMATION'
        );

        // Detect downstream impact on schema refactor (241.3 & 241.7)
        $impact = $this->service->detectLineageImpact('orders.total_amount_usd');

        $this->assertTrue($impact['has_downstream_impact']);
        $this->assertEquals(2, $impact['affected_node_count']);
        $this->assertContains('FINANCE_REPORTING', $impact['affected_consumers']);
        $this->assertContains('MARKETING_AUTOMATION', $impact['affected_consumers']);
        $this->assertContains('dashboard.executive_revenue_kpi', $impact['affected_artifacts']);
    }

    public function test_glossary_term_duplicate_detection_and_steward_approval(): void
    {
        // 1. Create term with steward approval requirement (241.4)
        $term = $this->service->registerGlossaryTerm(
            termKey: 'ARR',
            domainName: 'FINANCIAL',
            definition: 'Annual Recurring Revenue normalized across 12-month subscriptions.',
            approvedBySteward: false
        );
        $this->assertEquals('ARR', $term->term_key);
        $this->assertFalse((bool) $term->approved_by_steward);

        // Approve term (241.5)
        $approved = $this->service->approveGlossaryTerm((int) $term->id, 'STEWARD-FINANCE');
        $this->assertTrue((bool) $approved->approved_by_steward);

        // 2. Reject duplicate term registration (241.5)
        $this->expectException(InvalidArgumentException::class);
        $this->service->registerGlossaryTerm(
            termKey: 'ARR',
            domainName: 'FINANCIAL',
            definition: 'Duplicate definition attempt'
        );
    }

    public function test_data_governance_audit_healthy_and_discrepancy(): void
    {
        // Setup healthy records
        $this->service->onboardDomain('ENERGY', 'STEWARD-ENG', 365, 'INTERNAL');
        $this->service->evaluateDataQuality('ENERGY', 'meter_readings', 'TIMELINESS', 95.0, 0);
        $this->service->registerGlossaryTerm('KWH_PEAK', 'ENERGY', 'Peak hour power usage', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unapproved glossary term
        DB::table('data_glossary_terms')->insert([
            'term_key' => 'UNAPPROVED_METRIC',
            'domain_name' => 'ENERGY',
            'definition' => 'Pending review definition',
            'approved_by_steward' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
