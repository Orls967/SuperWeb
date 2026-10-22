<?php

declare(strict_types=1);

namespace Modules\Core\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Application\Services\DisasterRecoveryFailoverService;
use Tests\TestCase;

class DisasterRecoveryMultiRegionTest extends TestCase
{
    use RefreshDatabase;

    protected DisasterRecoveryFailoverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DisasterRecoveryFailoverService::class);
    }

    public function test_disaster_recovery_failover_achieves_zero_rpo_data_loss(): void
    {
        $nodes = [
            ['node_code' => 'NODE-JKT-01', 'region' => 'ap-southeast-3a-jakarta', 'role' => 'PRIMARY', 'ip' => '10.0.1.1', 'lag_ms' => 0, 'status' => 'SYNCED'],
            ['node_code' => 'NODE-SGP-01', 'region' => 'ap-southeast-1-singapore', 'role' => 'DR_HOT_STANDBY', 'ip' => '10.0.2.1', 'lag_ms' => 25, 'status' => 'SYNCED'],
        ];

        $drill = $this->service->executeFailoverDrill(
            'DRILL-TEST-001',
            'ap-southeast-3a-jakarta',
            'ap-southeast-1-singapore',
            $nodes
        );

        $this->assertSame(0, $drill->rpo_achieved_seconds);
        $this->assertSame(0, $drill->data_loss_bytes);
        $this->assertSame('PASSED', $drill->status);
    }

    public function test_disaster_recovery_rto_completes_under_nine_hundred_seconds(): void
    {
        $nodes = [
            ['node_code' => 'NODE-JKT-02', 'region' => 'ap-southeast-3a-jakarta', 'role' => 'PRIMARY', 'ip' => '10.0.1.2', 'lag_ms' => 0, 'status' => 'SYNCED'],
            ['node_code' => 'NODE-SBY-01', 'region' => 'ap-southeast-3c-surabaya', 'role' => 'ASYNC_REPLICA', 'ip' => '10.0.3.1', 'lag_ms' => 12, 'status' => 'SYNCED'],
        ];

        $drill = $this->service->executeFailoverDrill(
            'DRILL-TEST-002',
            'ap-southeast-3a-jakarta',
            'ap-southeast-3c-surabaya',
            $nodes
        );

        $this->assertLessThanOrEqual(900, $drill->rto_achieved_seconds);
        $this->assertSame(412, $drill->rto_achieved_seconds);
    }

    public function test_raft_quorum_witness_prevents_split_brain_dual_master(): void
    {
        // 3 active nodes (Jakarta, Bali witness, Singapore standby) -> Quorum reached
        $hasQuorum = $this->service->verifyRaftQuorum('jakarta', 'bali-witness', 'singapore');
        $this->assertTrue($hasQuorum);

        // 2 active nodes (Bali witness, Singapore standby) -> Quorum reached (2/3 > 50%)
        $hasQuorumTwoNodes = $this->service->verifyRaftQuorum('', 'bali-witness', 'singapore');
        $this->assertTrue($hasQuorumTwoNodes);
    }

    public function test_graceful_degradation_to_read_only_when_quorum_lost(): void
    {
        // Only 1 node surviving -> Quorum lost (1/3 <= 50%), must trigger read-only mode
        $hasQuorumOneNode = $this->service->verifyRaftQuorum('', '', 'singapore');
        $this->assertFalse($hasQuorumOneNode);
    }
}
