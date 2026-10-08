<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\GlobalPayrollCloseReconciliationService;
use Tests\TestCase;

class GlobalPayrollCloseReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected GlobalPayrollCloseReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GlobalPayrollCloseReconciliationService::class);
    }

    public function test_payroll_dry_run_variance_check_and_live_run_halt(): void
    {
        // 1. Dry run within small variance (e.g. 2% variance from prior) passes immediately (322.3 & 322.4)
        $passedRehearsal = $this->service->executePayrollRehearsal(
            batchCode: 'REHEARSE-2026-11-ALL-LINES',
            periodMonth: '2026-11',
            processedHeadcount: 5000,
            grossPayrollUsd: 10200000.0,
            priorGrossPayrollUsd: 10000000.0,
            varianceApproved: true
        );
        $this->assertTrue((bool) $passedRehearsal->is_dry_run_passed);
        $this->assertTrue((bool) $passedRehearsal->live_run_permitted);

        // 2. Dry run with massive unapproved variance (25% variance) halts live run (322.5 Edge Case)
        try {
            $this->service->executePayrollRehearsal(
                batchCode: 'REHEARSE-2026-12-UNAPPROVED',
                periodMonth: '2026-12',
                processedHeadcount: 5000,
                grossPayrollUsd: 12500000.0,
                priorGrossPayrollUsd: 10000000.0,
                varianceApproved: false // Unapproved!
            );
            $this->fail('Expected exception for unapproved material variance');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Payroll rehearsal halted: Material variance of 25% requires formal approval', $e->getMessage());
        }

        $failedRecord = DB::table('global_payroll_close_rehearsals')->where('rehearsal_batch_code', 'REHEARSE-2026-12-UNAPPROVED')->first();
        $this->assertFalse((bool) $failedRecord->is_dry_run_passed);
        $this->assertFalse((bool) $failedRecord->live_run_permitted);
    }

    public function test_payment_exception_accounting_treatment_remains_payable(): void
    {
        $this->service->executePayrollRehearsal('BATCH-RECON-01', '2026-11', 100, 100000.0, 100000.0, true);

        // Accounting requirement 322.4: Rejected payment must strictly remain payable, not expensed
        $exception = $this->service->logPaymentException(
            exceptionCode: 'EX-BANK-REJECT-01',
            batchCode: 'BATCH-RECON-01',
            employeeId: 'EMP_REMOTE_01',
            failedAmountUsd: 3500.0,
            reason: 'BANK_ACCOUNT_REJECTED',
            slaEscalated: true
        );
        $this->assertEquals('REMAINS_PAYABLE', $exception->accounting_treatment);
        $this->assertTrue((bool) $exception->sla_escalation_triggered);
    }

    public function test_hcm_payroll_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->executePayrollRehearsal('BATCH-AUD', '2026-11', 10, 1000.0, 1000.0, true);
        $this->service->logPaymentException('EX-AUD', 'BATCH-AUD', 'E1', 100.0, 'BANK_REJECT');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: improper accounting treatment
        DB::table('global_payroll_payment_exceptions')->insert([
            'exception_code' => 'EX-DEFECT-EXPENSED',
            'rehearsal_batch_code' => 'BATCH-AUD',
            'employee_id' => 'EMP_ERR',
            'failed_payment_amount_usd' => 500.0,
            'exception_reason' => 'BANK_ERR',
            'accounting_treatment' => 'WRONGLY_EXPENSED', // Discrepancy!
            'sla_escalation_triggered' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
