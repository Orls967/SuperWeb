<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EndToEndRiskService;
use Tests\TestCase;

class EndToEndRiskTest extends TestCase
{
    use RefreshDatabase;

    protected EndToEndRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EndToEndRiskService::class);
    }

    public function test_risk_register_creation_and_automatic_incident_bridge(): void
    {
        // 1. Unified Risk Register (253.1)
        $risk = $this->service->registerUnifiedRisk(
            riskCode: 'RSK-FX-001',
            businessLine: 'TREASURY',
            riskTitle: 'USD/IDR FX Volatility Exposure',
            riskCategory: 'MARKET_FX',
            inherentScore: 85.0,
            residualScore: 45.0,
            kriMetricValue: 12.50
        );

        $this->assertEquals(0, (int) $risk->incident_count);
        $this->assertEquals(45.0, (float) $risk->residual_score);

        // 2. Incident bridge event updates register automatically (253.1 & 253.6)
        $updated = $this->service->bridgeIncidentToRisk(
            riskId: (int) $risk->id,
            incidentCode: 'INC-FX-SHOCK-2026',
            impactUsd: 150000.0
        );

        $this->assertEquals(1, (int) $updated->incident_count);
        $this->assertEquals(50.0, (float) $updated->residual_score); // Elevated from 45 to 50
    }

    public function test_cross_line_aggregate_portfolio_risk_and_capital_buffer(): void
    {
        // Correlating Commodity (Mining), FX (Treasury), Credit (Banking/SaaS) (253.2 & 253.4)
        $portfolio = $this->service->calculateAggregatePortfolioRisk(
            portfolioCode: 'PORT-GROUP-RISK-2026',
            correlatedLines: ['MINING', 'TREASURY', 'COMMERCIAL_BANKING'],
            rawSumVaRUsd: 100000000.0,
            correlationFactor: 0.75
        );

        $this->assertEquals(25.00, (float) $portfolio->diversification_benefit_pct);
        $this->assertEquals(75000000.0, (float) $portfolio->tail_risk_var_99_usd);
        $this->assertEquals(93750000.0, (float) $portfolio->capital_buffer_required_usd); // 75M * 1.25
    }

    public function test_assurance_map_self_review_ban_and_material_gap_detection(): void
    {
        // 1. Process owner auditing own process is strictly prohibited (253.7)
        try {
            $this->service->mapProcessAssurance(
                processCode: 'PROC-PAYROLL-01',
                processName: 'Executive Payroll Processing',
                isMaterialProcess: true,
                processOwner: 'HR_PAYROLL_MANAGER',
                assuranceProvider: 'INTERNAL_AUDIT',
                assuranceTesterId: 'HR_PAYROLL_MANAGER' // Self-review!
            );
            $this->fail('Expected exception for self-review violation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Self-review prohibited', $e->getMessage());
        }

        // 2. Material process without assurance flags coverage gap & demands remediation (253.3 & 253.5 Edge Case)
        $gapProcess = $this->service->mapProcessAssurance(
            processCode: 'PROC-MINING-SAFETY-01',
            processName: 'Smelter Gas Leak Containment',
            isMaterialProcess: true,
            processOwner: 'SMELTER_CHIEF_ENGINEER',
            assuranceProvider: 'NONE',
            assuranceTesterId: null
        );

        $this->assertTrue((bool) $gapProcess->has_coverage_gap);
        $this->assertTrue((bool) $gapProcess->remediation_plan_required);

        // 3. Fully covered process with independent tester
        $coveredProcess = $this->service->mapProcessAssurance(
            processCode: 'PROC-TREASURY-SWIFT-01',
            processName: 'SWIFT Wire Transfer Sign-off',
            isMaterialProcess: true,
            processOwner: 'TREASURER',
            assuranceProvider: 'INTERNAL_AUDIT',
            assuranceTesterId: 'INDEPENDENT_AUDITOR_BOB'
        );

        $this->assertFalse((bool) $coveredProcess->has_coverage_gap);
        $this->assertFalse((bool) $coveredProcess->remediation_plan_required);
    }

    public function test_risk_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $risk = $this->service->registerUnifiedRisk('RSK-AUD-1', 'LOGISTICS', 'Fleet Accident', 'OPERATIONAL', 60.0, 30.0, 5.0);
        $this->service->calculateAggregatePortfolioRisk('PORT-AUD', ['A', 'B'], 1000.0);
        $this->service->mapProcessAssurance('PROC-AUD', 'Name', false, 'OWNER', 'INTERNAL_AUDIT', 'TESTER');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: material process gap without remediation plan requirement
        DB::table('risk_assurance_coverage_maps')->insert([
            'process_code' => 'PROC-ROGUE-GAP',
            'process_name' => 'High Voltage Grid Switching',
            'is_material_process' => true,
            'process_owner' => 'GRID_DIR',
            'assurance_provider' => 'NONE',
            'assurance_tester_id' => null,
            'is_self_reviewed' => false,
            'has_coverage_gap' => true,
            'remediation_plan_required' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
