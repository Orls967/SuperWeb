<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EthicsComplianceMaturityService;
use Tests\TestCase;

class EthicsComplianceMaturityTest extends TestCase
{
    use RefreshDatabase;

    protected EthicsComplianceMaturityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EthicsComplianceMaturityService::class);
    }

    public function test_line_scorecard_and_high_score_incident_anomaly_edge_case(): void
    {
        // 1. Mature line with low incidents (30%*90 + 40%*85 + 30%*90 = 27 + 34 + 27 = 88.0) (337.1 & 337.4)
        $mature = $this->service->recordLineComplianceScorecard(
            scorecardCode: 'SC-SMELTER-WEDA-2026',
            lineCode: 'LINE_SMELTER',
            trainingScore: 90.0,
            monitoringScore: 85.0,
            remediationScore: 90.0,
            incidentCount: 1
        );
        $this->assertEquals(88.0, (float) $mature->weighted_maturity_score);
        $this->assertEquals('MATURE', $mature->maturity_tier);
        $this->assertFalse((bool) $mature->flagged_for_consistency_investigation);

        // 2. High score with frequent incidents flags inconsistency anomaly (337.5 Edge Case)
        $anomaly = $this->service->recordLineComplianceScorecard(
            scorecardCode: 'SC-MINING-CONTRADICTION',
            lineCode: 'LINE_MINING',
            trainingScore: 95.0,
            monitoringScore: 90.0,
            remediationScore: 90.0, // Weighted = 91.5 (> 85)
            incidentCount: 8 // >= 5 incidents!
        );
        $this->assertEquals(91.5, (float) $anomaly->weighted_maturity_score);
        $this->assertEquals('INCONSISTENT_ANOMALY', $anomaly->maturity_tier);
        $this->assertTrue((bool) $anomaly->flagged_for_consistency_investigation);
    }

    public function test_vendor_ethics_due_process_and_contract_termination(): void
    {
        // 1. Unverified speak-up hotline channel throws exception (337.4)
        try {
            $this->service->adjudicateVendorEthicsBreach(
                caseCode: 'CASE-HOTLINE-FAIL',
                vendorId: 'VEN-HAULAGE-CORP',
                incidentType: 'BRIBERY_SOLICITATION',
                speakUpChannelVerified: false, // Unverified hotline!
                violationSubstantiated: true,
                exerciseTermination: true
            );
            $this->fail('Expected exception for unverified speak-up channel');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Vendor case processing requires verified speak-up hotline channel', $e->getMessage());
        }

        // 2. Terminating contract without substantiated violation throws exception (337.2 & 337.4)
        try {
            $this->service->adjudicateVendorEthicsBreach(
                caseCode: 'CASE-UNSUBSTANTIATED-TERMINATION',
                vendorId: 'VEN-LOGISTICS-SUPPLIER',
                incidentType: 'CONFLICT_OF_INTEREST',
                speakUpChannelVerified: true,
                violationSubstantiated: false, // Not substantiated!
                exerciseTermination: true // Illegally terminated!
            );
            $this->fail('Expected exception for unsupported contract termination');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Contractual termination right requires substantiated violation evidence', $e->getMessage());
        }

        // 3. Fully substantiated breach with verified channel exercises termination (337.2)
        $case = $this->service->adjudicateVendorEthicsBreach(
            caseCode: 'CASE-SUBSTANTIATED-FRAUD',
            vendorId: 'VEN-SECURITY-SERVICES',
            incidentType: 'KICKBACK_PAYMENT',
            speakUpChannelVerified: true,
            violationSubstantiated: true,
            exerciseTermination: true
        );
        $this->assertTrue((bool) $case->has_admitted_or_substantiated_violation);
        $this->assertTrue((bool) $case->contract_termination_exercised);
    }

    public function test_ethics_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordLineComplianceScorecard('SC-AUD', 'L1', 80.0, 80.0, 80.0, 1);
        $this->service->adjudicateVendorEthicsBreach('CASE-AUD', 'V1', 'GIFT_POLICY', true, true, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unflagged anomaly
        DB::table('compliance_program_line_scorecards')->insert([
            'scorecard_code' => 'SC-DEFECT-UNFLAGGED',
            'line_code' => 'L2',
            'weighted_maturity_score' => 90.0,
            'annual_ethics_incident_count' => 10,
            'flagged_for_consistency_investigation' => false, // Discrepancy!
            'maturity_tier' => 'MATURE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
