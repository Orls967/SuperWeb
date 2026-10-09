<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\QualityEngineeringAutomationService;
use Tests\TestCase;

class QualityEngineeringAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected QualityEngineeringAutomationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QualityEngineeringAutomationService::class);
    }

    public function test_quality_gate_evaluation_and_quarantine_flow(): void
    {
        // 426.1 & 426.3 Evaluate module quality gate with high coverage and high mutation score
        $mod = $this->service->evaluateModuleQualityGate(
            moduleCode: 'MOD-CORE-LEDGER',
            unit: 92.50,
            contract: 88.00,
            integration: 85.00,
            e2e: 78.00,
            mutationKillRate: 86.50
        );

        $this->assertEquals('MOD-CORE-LEDGER', $mod->module_code);
        $this->assertTrue((bool) $mod->passed_all_gates);

        // 426.2 Quarantine flaky test
        $quarantine = $this->service->quarantineFlakyTest(
            signature: 'Modules\Integration\Tests\FlakyThirdPartyWebhookTest::test_retry_timing',
            moduleCode: 'MOD-CORE-LEDGER',
            owner: 'Staff SDET'
        );

        $this->assertEquals('quarantined', $quarantine->status);
        $this->assertFalse((bool) $quarantine->is_flagged_as_defect);

        // 426.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['active_defects']);
    }

    public function test_substandard_mutation_score_blocked_and_quarantine_aging_defect_edge_cases(): void
    {
        // 426.3 & 426.6 Low mutation score (< 80%) fails gate even with 99% unit coverage
        try {
            $this->service->evaluateModuleQualityGate(
                moduleCode: 'MOD-WEAK-TESTS',
                unit: 99.00,
                contract: 90.00,
                integration: 85.00,
                e2e: 80.00,
                mutationKillRate: 62.00 // Weak tests! Missed mutation kill target
            );
            $this->fail('Expected exception for failed mutation score gate');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('does not meet required coverage / mutation kill rate', $e->getMessage());
        }

        // 426.5 Edge case: Test quarantined > 14 days converts to active defect
        $this->service->quarantineFlakyTest('FlakyTestA', 'MOD-A', 'Engineer X');
        $advanced = $this->service->advanceQuarantineDays('FlakyTestA', 15);

        $this->assertTrue((bool) $advanced->is_flagged_as_defect);
        $this->assertEquals('active_defect', $advanced->status);

        // Audit flags defect
        $audit = $this->service->audit();
        $this->assertEquals('DEFECTS_DETECTED', $audit['status']);
        $this->assertEquals(1, $audit['active_defects']);
    }
}
