<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\ContinuousControlsMonitoringService;
use Tests\TestCase;

class ContinuousControlsMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected ContinuousControlsMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContinuousControlsMonitoringService::class);
    }

    public function test_transaction_monitoring_step_up_auth_and_circuit_breaker(): void
    {
        // 1. Normal low-value transaction clears immediately (311.1 & 311.4)
        $txNormal = $this->service->monitorTransaction('TX-NORMAL-01', 120.0, 'MERCHANT_SHELL', false);
        $this->assertEquals('CLEARED', $txNormal->clearance_status);

        // 2. High-value transaction ($75k >= $50k) without step-up MFA is held (311.2)
        $txHighRisk = $this->service->monitorTransaction('TX-HELD-02', 75000.0, 'OFFSHORE_SUPPLIER', false, false);
        $this->assertEquals('HELD_FRAUD', $txHighRisk->clearance_status);

        // 3. Edge case 311.5: Circuit breaker appeal overrides hold
        $txAppealed = $this->service->appealHeldTransactionViaCircuitBreaker('TX-HELD-02');
        $this->assertTrue((bool) $txAppealed->is_circuit_breaker_active);
        $this->assertEquals('CIRCUIT_BREAKER_APPEAL', $txAppealed->clearance_status);
    }

    public function test_straight_through_processing_reconciliation_batch(): void
    {
        // 1. Batch achieving 98.0% STP (>= 95.0% target) succeeds (311.3 & 311.4)
        $batch = $this->service->recordReconciliationBatch('BATCH-RECON-Q3', 1000, 980, 95.0);
        $this->assertTrue((bool) $batch->stp_target_achieved);
        $this->assertEquals(98.0, (float) $batch->stp_rate_pct);

        // 2. Subpar STP batch (91.0% < 95.0%) is flagged (311.3 & 311.4)
        $subparBatch = $this->service->recordReconciliationBatch('BATCH-RECON-SUBPAR', 1000, 910, 95.0);
        $this->assertFalse((bool) $subparBatch->stp_target_achieved);
    }

    public function test_bank_reconcile_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->monitorTransaction('TX-AUD', 100.0, 'VENDOR', false);
        $this->service->recordReconciliationBatch('BATCH-AUD', 100, 99, 95.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: uncompleted step-up marked cleared
        DB::table('finance_monitored_transactions')->insert([
            'transaction_code' => 'TX-FRAUD-LEAK',
            'amount_usd' => 100000.0,
            'counterparty_account' => 'UNKNOWN_BENEFICIARY',
            'is_suspicious_velocity_or_split' => true,
            'step_up_auth_required' => true,
            'step_up_auth_completed' => false, // Discrepancy!
            'is_circuit_breaker_active' => false,
            'clearance_status' => 'CLEARED', // Improper clearance!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
