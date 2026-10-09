<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\KnowledgeGroundingCitationService;
use Tests\TestCase;

class KnowledgeGroundingCitationTest extends TestCase
{
    use RefreshDatabase;

    protected KnowledgeGroundingCitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KnowledgeGroundingCitationService::class);
    }

    public function test_empty_corpus_hallucination_prevention_edge_case(): void
    {
        // 1. Query found in corpus returns grounded answer (347.1)
        $grounded = $this->service->processGroundedQuery(
            queryCode: 'QUERY-ESG-POLICY-01',
            queryText: 'What is our Scope 1 target for 2030?',
            foundInCorpus: true,
            candidateAnswer: 'Net zero emissions reduction of 45% by 2030.'
        );
        $this->assertTrue((bool) $grounded->found_in_grounded_corpus);
        $this->assertFalse((bool) $grounded->refused_due_to_empty_corpus);
        $this->assertEquals('Net zero emissions reduction of 45% by 2030.', $grounded->generated_answer);

        // 2. Query not in corpus refuses to hallucinate with "tidak ada di sumber" (347.5 Edge Case)
        $refused = $this->service->processGroundedQuery(
            queryCode: 'QUERY-UNKNOWN-TOPIC-02',
            queryText: 'What is the private home address of CEO?',
            foundInCorpus: false,
            candidateAnswer: null
        );
        $this->assertFalse((bool) $refused->found_in_grounded_corpus);
        $this->assertTrue((bool) $refused->refused_due_to_empty_corpus);
        $this->assertEquals('tidak ada di sumber', $refused->generated_answer);
    }

    public function test_citation_verification_and_staleness_detection(): void
    {
        // 1. Citation that does not support claim throws exception (347.3 & 347.4)
        try {
            $this->service->verifyCitation(
                verificationCode: 'VERIF-UNSUPPORTED-01',
                queryCode: 'QUERY-ESG-POLICY-01',
                docId: 'DOC-POLICY-ENV-2025',
                version: '1.0',
                isStale: false,
                supportsClaim: false // Claim unsupported!
            );
            $this->fail('Expected exception for unsupported citation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cited passage does not support generated claim', $e->getMessage());
        }

        // 2. Valid citation supporting claim is recorded (347.3 & 347.4)
        $validVerif = $this->service->verifyCitation(
            verificationCode: 'VERIF-SUPPORTED-02',
            queryCode: 'QUERY-ESG-POLICY-01',
            docId: 'DOC-POLICY-ENV-2026',
            version: '2.0',
            isStale: false,
            supportsClaim: true
        );
        $this->assertTrue((bool) $validVerif->citation_supports_claim);
        $this->assertFalse((bool) $validVerif->is_stale_version);
    }

    public function test_ai_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->processGroundedQuery('Q-AUD', 'Query', true, 'Answer');
        $this->service->verifyCitation('V-AUD', 'Q-AUD', 'DOC1', '1.0', false, true);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unrefused hallucination
        DB::table('knowledge_grounding_retrieval_queries')->insert([
            'query_code' => 'Q-DEFECT-FABRICATED',
            'user_query' => 'Fabricated query',
            'found_in_grounded_corpus' => false,
            'generated_answer' => 'Completely fabricated hallucination without sources',
            'refused_due_to_empty_corpus' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
