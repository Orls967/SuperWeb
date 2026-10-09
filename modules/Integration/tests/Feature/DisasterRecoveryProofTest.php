<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\DisasterRecoveryProofService;
use Tests\TestCase;

class DisasterRecoveryProofTest extends TestCase
{
    use RefreshDatabase;

    protected DisasterRecoveryProofService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DisasterRecoveryProofService::class);
    }

    public function test_rpo_rto_breach_records_blocker_edge_case(): void
    {
        // 1. RTO breach records blocker finding (399.1, 399.4, 399.5 Edge Case)
        try {
            $this->service->recordDrillExecution(
                drillCode: 'DR-DRILL-REGION-FAILOVER-2026-Q4',
                measuredRpoSeconds: 45,
                targetRpoSeconds: 60,
                measuredRtoSeconds: 450, // 450s > 300s target!
                targetRtoSeconds: 300
            );
            $this->fail('Expected exception for RTO target breach');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('blocker recorded', $e->getMessage());
        }

        // Verify blocker record in DB
        $drill = DB::table('global_stress_dr_failover_drills')->where('drill_code', 'DR-DRILL-REGION-FAILOVER-2026-Q4')->first();
        $this->assertNotNull($drill);
        $this->assertTrue((bool) $drill->is_blocker_finding);

        // 2. Successful drill within targets (399.4)
        $validDrill = $this->service->recordDrillExecution(
            drillCode: 'DR-DRILL-REGION-FAILOVER-2026-Q4-RETEST',
            measuredRpoSeconds: 30,
            targetRpoSeconds: 60,
            measuredRtoSeconds: 180,
            targetRtoSeconds: 300
        );
        $this->assertFalse((bool) $validDrill->is_blocker_finding);
    }

    public function test_ledger_recovery_zero_discrepancy(): void
    {
        // 1. Discrepancy during ledger recovery fails (399.2 & 399.4)
        try {
            $this->service->verifyLedgerRecovery(
                recoveryCode: 'REC-LEDGER-RESTORE-01',
                unexplainedDiscrepancies: 3, // Discrepancies present!
                hashChainVerified: true
            );
            $this->fail('Expected exception for recovery discrepancies');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('unexplained discrepancies or broken hash chain', $e->getMessage());
        }

        // 2. Clean recovery with zero discrepancy succeeds (399.4)
        $recovery = $this->service->verifyLedgerRecovery(
            recoveryCode: 'REC-LEDGER-RESTORE-02',
            unexplainedDiscrepancies: 0,
            hashChainVerified: true
        );
        $this->assertEquals(0, $recovery->unexplained_discrepancy_count);
        $this->assertTrue((bool) $recovery->hash_chain_verified);
    }

    public function test_disaster_recovery_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->recordDrillExecution('D-AUD', 10, 60, 100, 300);
        $this->service->verifyLedgerRecovery('R-AUD', 0, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: blocker drill
        DB::table('global_stress_dr_failover_drills')->insert([
            'drill_code' => 'D-DEFECT-BLOCKER',
            'measured_rpo_seconds' => 999,
            'target_rpo_seconds' => 60,
            'measured_rto_seconds' => 9999,
            'target_rto_seconds' => 300,
            'is_blocker_finding' => true, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
