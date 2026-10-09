<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\DistributedTransactionSafetyService;
use Tests\TestCase;

class DistributedTransactionSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected DistributedTransactionSafetyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DistributedTransactionSafetyService::class);
    }

    public function test_region_failover_repeated_command_posts_once(): void
    {
        // 1. Post command in Primary Region AP-SOUTHEAST-1 (396.1 & 396.4)
        $first = $this->service->postFailoverCommand(
            globalKey: 'IDEMP-FAILOVER-TRANSFER-999',
            activeRegion: 'AP-SOUTHEAST-1'
        );
        $this->assertEquals(1, $first->posted_count);

        // 2. Network failover re-submits identical key in Backup Region AP-SOUTHEAST-3 (396.1 & 396.4)
        $second = $this->service->postFailoverCommand(
            globalKey: 'IDEMP-FAILOVER-TRANSFER-999',
            activeRegion: 'AP-SOUTHEAST-3'
        );
        $this->assertEquals(2, $second->posted_count);
        $this->assertEquals($first->id, $second->id);
    }

    public function test_unresolvable_saga_timeout_escalates_to_manual_owner_edge_case(): void
    {
        // Unresolvable saga post-timeout escalates to manual owner with checklist (396.2, 396.4, 396.5 Edge Case)
        $saga = $this->service->handleSagaTimeout(
            sagaCode: 'SAGA-CROSSLINE-MINING-PORT-01',
            isResolvableAutomatically: false
        );

        $this->assertEquals('ESCALATED_MANUAL_REVIEW', $saga->saga_status);
        $this->assertTrue((bool) $saga->manual_owner_escalated);

        // Automatically resolvable saga converges (396.4)
        $autoSaga = $this->service->handleSagaTimeout(
            sagaCode: 'SAGA-HOTEL-CHECKIN-02',
            isResolvableAutomatically: true
        );
        $this->assertEquals('CONVERGED_COMPENSATED', $autoSaga->saga_status);
        $this->assertFalse((bool) $autoSaga->manual_owner_escalated);
    }

    public function test_distributed_safety_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->postFailoverCommand('KEY-AUD', 'AP-1');
        $this->service->handleSagaTimeout('S-AUD', false);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: hanging timed out saga
        DB::table('global_stress_distributed_saga_states')->insert([
            'saga_code' => 'S-DEFECT-HANGING',
            'saga_status' => 'TIMED_OUT', // Discrepancy!
            'manual_owner_escalated' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
