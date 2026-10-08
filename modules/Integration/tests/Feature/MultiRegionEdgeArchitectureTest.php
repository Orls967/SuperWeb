<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\MultiRegionEdgeArchitectureService;
use Tests\TestCase;

class MultiRegionEdgeArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected MultiRegionEdgeArchitectureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MultiRegionEdgeArchitectureService::class);
    }

    public function test_multiregion_primary_registration_and_epoch_conflict_resolution(): void
    {
        // 1. Register domain primary: International commodity primary in SG (epoch = 2) (258.1)
        $primary = $this->service->registerDomainPrimary(
            domainName: 'COMMODITY_INTL',
            primaryRegion: 'AP_SOUTHEAST_SG',
            residencyCountry: 'SG',
            epoch: 2
        );

        $this->assertEquals('AP_SOUTHEAST_SG', $primary->primary_region);
        $this->assertEquals(2, (int) $primary->current_epoch);

        // 2. Primary region write commits cleanly (258.1)
        $validTx = $this->service->recordMultiRegionWrite('COMMODITY_INTL', 'AP_SOUTHEAST_SG', 2);
        $this->assertEquals('WINNER_COMMITTED', $validTx->conflict_outcome);
        $this->assertFalse((bool) $validTx->is_conflict_detected);

        // 3. Stale epoch write from secondary region triggers conflict & rollback (258.5 Edge Case)
        $conflictTx = $this->service->recordMultiRegionWrite('COMMODITY_INTL', 'ID_JAKARTA', 1);
        $this->assertTrue((bool) $conflictTx->is_conflict_detected);
        $this->assertEquals('LOSER_ROLLED_BACK', $conflictTx->conflict_outcome);
    }

    public function test_edge_sync_protocol_and_partition_healing_convergence(): void
    {
        // Edge node at remote mining facility in Kalimantan (258.2 & 258.4)
        $node = $this->service->syncEdgeNode(
            nodeCode: 'EDGE-MINE-KAL-01',
            nodeType: 'MINE_REMOTE',
            opsProcessed: 45,
            isPartitionHealed: true
        );

        $this->assertEquals(45, (int) $node->local_version_vector);
        $this->assertEquals(0, (int) $node->pending_ops_count);
        $this->assertFalse((bool) $node->is_partitioned);
        $this->assertTrue((bool) $node->is_converged);
    }

    public function test_data_gravity_query_cost_and_residency_constraint_enforcement(): void
    {
        // 1. Local execution evaluated close to data gravity has 0 egress cost (258.3 & 258.6)
        $localQuery = $this->service->planFederatedQuery(
            sourceDataRegion: 'ID_JAKARTA',
            executionRegion: 'ID_JAKARTA',
            dataVolumeMb: 500.0
        );
        $this->assertEquals(0.0000, (float) $localQuery->egress_transfer_cost_usd);

        // 2. Cross-region execution bears egress cost
        $crossQuery = $this->service->planFederatedQuery(
            sourceDataRegion: 'ID_JAKARTA',
            executionRegion: 'AP_SOUTHEAST_SG',
            dataVolumeMb: 200.0
        );
        $this->assertEquals(10.0000, (float) $crossQuery->egress_transfer_cost_usd); // 200 * 0.05

        // 3. Residency constraint breach is blocked (258.7)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Data residency violation');
        $this->service->planFederatedQuery('ID_JAKARTA', 'US_EAST', 100.0, true);
    }

    public function test_multiregion_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerDomainPrimary('DOMESTIC_OPS', 'ID_JAKARTA', 'ID', 1);
        $this->service->recordMultiRegionWrite('DOMESTIC_OPS', 'ID_JAKARTA', 1);
        $this->service->syncEdgeNode('EDGE-HOTEL-1', 'VENUE_HOTEL', 10, true);
        $this->service->planFederatedQuery('ID_JAKARTA', 'ID_JAKARTA', 10.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: non-compliant federated query
        DB::table('multiregion_federated_queries')->insert([
            'query_code' => 'QRY-RESIDENCY-BREACH',
            'source_data_region' => 'ID_JAKARTA',
            'execution_region' => 'FOREIGN_CLOUD',
            'egress_transfer_cost_usd' => 50.0,
            'data_residency_compliant' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
