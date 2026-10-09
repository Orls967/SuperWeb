<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\EnterpriseSearchKnowledgeGraphService;
use Tests\TestCase;

class EnterpriseSearchKnowledgeGraphTest extends TestCase
{
    use RefreshDatabase;

    protected EnterpriseSearchKnowledgeGraphService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnterpriseSearchKnowledgeGraphService::class);
    }

    public function test_knowledge_graph_traversal_and_clearance_filtering(): void
    {
        // 1. Setup nodes with clearance levels (259.1 & 259.4)
        $this->service->createGraphNode('CUST-ACME', 'CUSTOMER', 'Acme Corp', 1);
        $this->service->createGraphNode('CTR-2026-01', 'CONTRACT', 'Master EPC Contract', 1);
        $this->service->createGraphNode('RSK-TOP-SECRET', 'RISK', 'Geopolitical Sanctions Exposure', 3); // Clearance 3

        // Edges: CUST -> CTR -> RSK
        $this->service->createGraphEdge('CUST-ACME', 'CTR-2026-01', 'BINDS_TO');
        $this->service->createGraphEdge('CTR-2026-01', 'RSK-TOP-SECRET', 'EXPOSED_TO');

        // 2. Traversal by user with Clearance 1 cannot penetrate Confidential Risk (clearance 3)
        $result = $this->service->traverseGraphWithBudget('CUST-ACME', 2, 1);
        $this->assertContains('CUST-ACME', $result['nodes']);
        $this->assertContains('CTR-2026-01', $result['nodes']);
        $this->assertNotContains('RSK-TOP-SECRET', $result['nodes']);

        // 3. Traversal by executive with Clearance 3 reaches all nodes
        $execResult = $this->service->traverseGraphWithBudget('CUST-ACME', 2, 3);
        $this->assertContains('RSK-TOP-SECRET', $execResult['nodes']);
    }

    public function test_traversal_depth_limit_budget(): void
    {
        $this->service->createGraphNode('NODE-A', 'ASSET', 'Asset A', 1);

        // Requested depth 7 is safely capped to 3 (259.5 Edge Case)
        $result = $this->service->traverseGraphWithBudget('NODE-A', 7, 1);
        $this->assertEquals(3, $result['effective_depth']);
    }

    public function test_semantic_entity_resolution_and_ambiguity_merge_guard(): void
    {
        // 1. High confidence (0.95) resolution (259.2)
        $exact = $this->service->resolveEntity('PT Nusantara Gas', 'CORP-NUSANTARA-GAS', 0.950);
        $this->assertFalse((bool) $exact->is_ambiguous_merge_recommended);

        // 2. Ambiguous match (0.82) recommends manual merge review (259.6 Edge Case)
        $ambiguous = $this->service->resolveEntity('Nusantara Tech Group', 'CORP-NUSANTARA-TECH', 0.820);
        $this->assertTrue((bool) $ambiguous->is_ambiguous_merge_recommended);
    }

    public function test_knowledge_article_expiry_and_stale_flagging(): void
    {
        // 1. Active article (259.3)
        $activeArticle = $this->service->publishArticle(
            articleCode: 'SOP-SMELTER-01',
            title: 'Smelter Emergency Shutdown Procedure',
            contentBody: 'Step 1: Disconnect high-voltage bus bar...',
            expiresAt: now()->addYear()->toDateString()
        );
        $this->assertTrue((bool) $activeArticle->is_current);
        $this->assertFalse((bool) $activeArticle->is_stale_expired);

        // 2. Expired article flagged stale and removed from current (259.7)
        $expiredArticle = $this->service->publishArticle(
            articleCode: 'SOP-LEGACY-02',
            title: 'Legacy Paper Invoice Handling',
            contentBody: 'Old procedures...',
            expiresAt: now()->subDay()->toDateString()
        );
        $checked = $this->service->checkArticleExpiry('SOP-LEGACY-02');
        $this->assertFalse((bool) $checked->is_current);
        $this->assertTrue((bool) $checked->is_stale_expired);
    }

    public function test_knowledge_graph_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->createGraphNode('N-1', 'ASSET', 'Asset 1', 1);
        $this->service->createGraphNode('N-2', 'RISK', 'Risk 2', 1);
        $this->service->createGraphEdge('N-1', 'N-2', 'EXPOSED_TO');
        $this->service->resolveEntity('Alias', 'ID', 0.9);
        $this->service->publishArticle('SOP-AUD', 'Title', 'Body', now()->addMonth()->toDateString());

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: stale article marked current
        DB::table('kg_knowledge_articles')->insert([
            'article_code' => 'SOP-DISCREPANT',
            'title' => 'Outdated SOP',
            'content_body' => 'Body',
            'version' => 1,
            'is_current' => true, // Discrepancy: marked current while stale!
            'is_stale_expired' => true,
            'expires_at' => now()->subYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
