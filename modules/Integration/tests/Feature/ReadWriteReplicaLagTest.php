<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\ReadWriteReplicaLagService;
use Tests\TestCase;

class ReadWriteReplicaLagTest extends TestCase
{
    use RefreshDatabase;

    protected ReadWriteReplicaLagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReadWriteReplicaLagService::class);
    }

    public function test_payment_confirmation_never_reads_stale_balance(): void
    {
        // 1. Attempting financial balance read against eventual replica fails (395.1 & 395.4)
        try {
            $this->service->routeReadQuery(
                queryCode: 'QRY-PAYMENT-BALANCE-CHECK-01',
                isFinancialOrCritical: true,
                intendedTarget: 'REPLICA' // Stale read forbidden!
            );
            $this->fail('Expected exception for routing financial read to replica');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Financial balance reads cannot route to eventual replica', $e->getMessage());
        }

        // 2. Financial read routed to primary succeeds (395.1 & 395.4)
        $query = $this->service->routeReadQuery(
            queryCode: 'QRY-PAYMENT-BALANCE-CHECK-02',
            isFinancialOrCritical: true,
            intendedTarget: 'PRIMARY'
        );
        $this->assertEquals('PRIMARY', $query->target_database);
        $this->assertTrue((bool) $query->is_financial_or_critical);
    }

    public function test_severe_replica_lag_fallback_edge_case(): void
    {
        // When replicas lag severely (>5000ms), critical traffic redirects to primary while analytics stays on replica (395.2 & 395.5 Edge Case)
        $fallback = $this->service->handleReplicaLagSurge(
            fallbackCode: 'FAL-LAG-SURGE-10S',
            replicaLagMs: 8500
        );

        $this->assertTrue((bool) $fallback->redirect_critical_to_primary);
        $this->assertTrue((bool) $fallback->analytics_kept_on_replica);
    }

    public function test_read_replica_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->routeReadQuery('Q-AUD-FIN', true, 'PRIMARY');
        $this->service->routeReadQuery('Q-AUD-ANA', false, 'REPLICA', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: financial query routed to replica
        DB::table('global_stress_read_routing_queries')->insert([
            'query_code' => 'Q-DEFECT-STALE-FINANCIAL',
            'target_database' => 'REPLICA', // Discrepancy!
            'is_financial_or_critical' => true,
            'freshness_label_attached' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
