<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\KnowledgeGraphEnterpriseCopilotService;
use Tests\TestCase;

class KnowledgeGraphEnterpriseCopilotTest extends TestCase
{
    use RefreshDatabase;

    protected KnowledgeGraphEnterpriseCopilotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KnowledgeGraphEnterpriseCopilotService::class);
    }

    public function test_stale_graph_value_as_current_rejection_edge_case(): void
    {
        // 1. Attempting to present stale value as current live stock/financial value throws exception (357.2 & 357.5 Edge Case)
        try {
            $this->service->executeGraphQuery(
                queryCode: 'QUERY-NICKEL-INVENTORY-01',
                entityCode: 'INVENTORY_STOCK_NICKEL_ORE',
                requiresAuthoritativeLiveValue: true,
                isStaleValue: true, // Value is stale!
                attemptPresentAsCurrent: true // Attempting to present as current!
            );
            $this->fail('Expected exception when presenting stale graph value as current');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Stale graph value cannot be presented as current authoritative value', $e->getMessage());
        }

        // Verify rejected record exists
        $rejected = DB::table('knowledge_graph_authoritative_queries')->where('query_code', 'QUERY-NICKEL-INVENTORY-01')->first();
        $this->assertNotNull($rejected);
        $this->assertTrue((bool) $rejected->query_rejected_due_to_staleness);

        // 2. Fresh authoritative value query succeeds (357.2 & 357.4)
        $fresh = $this->service->executeGraphQuery(
            queryCode: 'QUERY-NICKEL-INVENTORY-02',
            entityCode: 'INVENTORY_STOCK_NICKEL_ORE',
            requiresAuthoritativeLiveValue: true,
            isStaleValue: false, // Fresh!
            attemptPresentAsCurrent: false
        );
        $this->assertFalse((bool) $fresh->is_value_stale);
        $this->assertFalse((bool) $fresh->query_rejected_due_to_staleness);
    }

    public function test_provenance_completeness_gate(): void
    {
        // 1. Incomplete provenance throws exception and blocks serving answer (357.4 & 357.6 Risk)
        try {
            $this->service->recordProvenanceAndServeAnswer(
                provenanceCode: 'PROV-INCOMPLETE-01',
                queryCode: 'QUERY-NICKEL-INVENTORY-02',
                sourceSystem: 'ERP_SAP_S4HANA',
                isComplete: false // Incomplete!
            );
            $this->fail('Expected exception for incomplete provenance record');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Incomplete provenance records prevent answer from being served', $e->getMessage());
        }

        // 2. Complete provenance serves answer (357.4)
        $prov = $this->service->recordProvenanceAndServeAnswer(
            provenanceCode: 'PROV-COMPLETE-02',
            queryCode: 'QUERY-NICKEL-INVENTORY-02',
            sourceSystem: 'ERP_SAP_S4HANA',
            isComplete: true
        );
        $this->assertTrue((bool) $prov->provenance_complete);
        $this->assertTrue((bool) $prov->answer_served);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->executeGraphQuery('Q-AUD', 'ASSET1', false, false, false);
        $this->service->recordProvenanceAndServeAnswer('P-AUD', 'Q-AUD', 'ERP', true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: stale presented as current without rejection
        DB::table('knowledge_graph_authoritative_queries')->insert([
            'query_code' => 'Q-DEFECT-UNREJECTED',
            'entity_code' => 'STOCK_DEFECT',
            'requires_authoritative_live_value' => true,
            'is_value_stale' => true,
            'presented_stale_as_current' => true,
            'query_rejected_due_to_staleness' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
