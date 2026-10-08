<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\EndToEndFinanceService;
use Tests\TestCase;

class EndToEndFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected EndToEndFinanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EndToEndFinanceService::class);
    }

    public function test_close_cycle_initiation_and_variance_reconciliation_guard(): void
    {
        // 1. Fully aligned ledger truth (252.1 & 252.2)
        $alignedCycle = $this->service->initiateCloseCycle(
            cycleCode: 'CLOSE-2026-M09',
            fiscalPeriod: '2026-09',
            ledgerNetIncomeUsd: 5000000.0,
            statutoryNetIncomeUsd: 5000000.0,
            managementNetIncomeUsd: 5000000.0
        );

        $this->assertEquals(0.0, (float) $alignedCycle->reconciliation_variance_usd);
        $this->assertFalse((bool) $alignedCycle->is_cycle_locked);

        // 2. Unexplained variance between management and statutory is rejected (252.5 Edge Case)
        try {
            $this->service->initiateCloseCycle(
                cycleCode: 'CLOSE-2026-M10',
                fiscalPeriod: '2026-10',
                ledgerNetIncomeUsd: 5000000.0,
                statutoryNetIncomeUsd: 5000000.0,
                managementNetIncomeUsd: 5200000.0,
                varianceExplanation: null // Unexplained!
            );
            $this->fail('Expected exception for unexplained variance');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must have an explicit documented explanation', $e->getMessage());
        }

        // 3. Documented variance is accepted
        $explainedCycle = $this->service->initiateCloseCycle(
            cycleCode: 'CLOSE-2026-M10',
            fiscalPeriod: '2026-10',
            ledgerNetIncomeUsd: 5000000.0,
            statutoryNetIncomeUsd: 5000000.0,
            managementNetIncomeUsd: 5200000.0,
            varianceExplanation: 'Timing difference: Management report recognizes internal revenue allocation ahead of statutory period cutoff.'
        );
        $this->assertEquals(200000.0, (float) $explainedCycle->reconciliation_variance_usd);
    }

    public function test_close_tasks_dependency_and_cycle_locking(): void
    {
        $cycle = $this->service->initiateCloseCycle('CLOSE-DEP-01', '2026-10', 1000000.0, 1000000.0, 1000000.0);

        // Task A: AR Subledger Close
        $taskA = $this->service->addCloseTask((int) $cycle->id, 'AR_SUBLEDGER_CLOSE');

        // Task B: General Ledger Consolidation (depends on Task A) (252.7)
        $taskB = $this->service->addCloseTask((int) $cycle->id, 'GL_CONSOLIDATION', (int) $taskA->id);

        // 1. Attempting to complete Task B before Task A fails
        try {
            $this->service->completeCloseTask((int) $taskB->id);
            $this->fail('Expected exception for dependency failure');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Prerequisite task', $e->getMessage());
        }

        // 2. Complete Task A then Task B
        $this->service->completeCloseTask((int) $taskA->id);
        $completedB = $this->service->completeCloseTask((int) $taskB->id);
        $this->assertEquals('COMPLETED', $completedB->status);

        // 3. Lock close cycle (252.1 & 252.4)
        $locked = $this->service->lockCloseCycle((int) $cycle->id);
        $this->assertTrue((bool) $locked->is_cycle_locked);
    }

    public function test_shared_service_sla_tracking_and_capacity_review_trigger(): void
    {
        // 1. Healthy within SLA
        $healthySla = $this->service->recordSharedServiceSla(
            serviceStream: 'BILLING_OPERATIONS',
            volumeProcessed: 5000,
            targetSlaHours: 4.0,
            actualAvgHours: 2.5
        );
        $this->assertFalse((bool) $healthySla->is_sla_breached);
        $this->assertFalse((bool) $healthySla->capacity_review_triggered);

        // 2. SLA breached triggers automated capacity review (252.3 & 252.6 Edge Case)
        $breachedSla = $this->service->recordSharedServiceSla(
            serviceStream: 'AP_PROCESSING',
            volumeProcessed: 12000,
            targetSlaHours: 6.0,
            actualAvgHours: 9.8 // Breached!
        );
        $this->assertTrue((bool) $breachedSla->is_sla_breached);
        $this->assertTrue((bool) $breachedSla->capacity_review_triggered);
    }

    public function test_finance_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $cycle = $this->service->initiateCloseCycle('CLOSE-AUD-1', '2026-10', 500000.0, 500000.0, 500000.0);
        $t = $this->service->addCloseTask((int) $cycle->id, 'TASK-1');
        $this->service->completeCloseTask((int) $t->id);
        $this->service->lockCloseCycle((int) $cycle->id);
        $this->service->recordSharedServiceSla('CASH_APP', 100, 5.0, 2.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: locked cycle with divergence from ledger truth
        DB::table('finance_unified_close_cycles')->insert([
            'cycle_code' => 'CLOSE-FALSE-AUDIT',
            'fiscal_period' => '2026-10',
            'ledger_net_income_usd' => 500000.0,
            'statutory_net_income_usd' => 800000.0, // Ledger divergence!
            'management_net_income_usd' => 800000.0,
            'reconciliation_variance_usd' => 0.0,
            'checklist_tasks_total' => 1,
            'checklist_tasks_completed' => 1,
            'is_cycle_locked' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
