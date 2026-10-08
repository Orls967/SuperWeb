<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\FraudRiskAssessmentDetectionService;
use Tests\TestCase;

class FraudRiskAssessmentDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected FraudRiskAssessmentDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FraudRiskAssessmentDetectionService::class);
    }

    public function test_fraud_rule_and_detected_exercise(): void
    {
        // 402.1 & 402.2
        $rule = $this->service->registerRule(
            ruleCode: 'RULE-P2P-SPLIT',
            businessCycle: 'procure-to-pay',
            name: 'Split PO threshold detector',
            schemeType: 'split_invoice'
        );

        $this->assertEquals('RULE-P2P-SPLIT', $rule->rule_code);

        // 402.3 Red-team exercise detected
        $exercise = $this->service->recordRedTeamExercise(
            exerciseCode: 'RED-2026-001',
            businessCycle: 'procure-to-pay',
            seededScheme: 'split_invoice',
            detected: true
        );

        $this->assertTrue((bool) $exercise->detected);
        $this->assertTrue((bool) $exercise->remediated);

        // Audit clean (402.4)
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['unremediated_gaps']);
    }

    public function test_undetected_gap_triggers_remediation_edge_case(): void
    {
        // 402.3 Red-team seeds scheme that is undetected
        $this->service->recordRedTeamExercise(
            exerciseCode: 'RED-2026-002',
            businessCycle: 'order-to-cash',
            seededScheme: 'loyalty_abuse',
            detected: false,
            gapNotes: 'Points churn below threshold not flagged'
        );

        // Audit immediately catches discrepancy
        $audit = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $audit['status']);
        $this->assertEquals(1, $audit['unremediated_gaps']);

        // 402.5 Edge case: Red-team finding gap triggers challenger rule + remediation
        $remediated = $this->service->remediateGap(
            exerciseCode: 'RED-2026-002',
            newRuleCode: 'RULE-LOYALTY-CHURN',
            ruleName: 'Velocity point churn detector'
        );

        $this->assertTrue((bool) $remediated->remediated);

        // Audit returns to healthy
        $auditClean = $this->service->audit();
        $this->assertEquals('HEALTHY', $auditClean['status']);
        $this->assertEquals(0, $auditClean['unremediated_gaps']);
    }
}
