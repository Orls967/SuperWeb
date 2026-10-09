<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Integration\Application\Services\InstitutionalMemoryKnowledgeService;
use Tests\TestCase;

class InstitutionalMemoryKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected InstitutionalMemoryKnowledgeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InstitutionalMemoryKnowledgeService::class);
    }

    public function test_institutional_decision_log_and_knowledge_access_flow(): void
    {
        // 476.1 Log strategic decision with context & alternatives
        $dec = $this->service->logDecision(
            code: 'DEC-ERP-MIGRATION-2026',
            title: 'Transition from Legacy Oracle to SAP S/4HANA Cloud',
            contextAlternatives: 'Evaluated Microsoft Dynamics vs SAP S/4HANA; SAP chosen for multi-currency group consolidation depth',
            outcome: 'Approved by Group Investment Committee',
            reviewDate: now()->addYear()->toDateString(),
            owner: 'Group Chief Information Officer'
        );

        $this->assertEquals('DEC-ERP-MIGRATION-2026', $dec->decision_code);

        // 476.2 & 476.3 Record knowledge article and verify usage tracking
        $art = $this->service->recordKnowledgeArticle(
            code: 'KB-PIR-BLACKOUT-01',
            domain: 'pir',
            expert: 'USR-STAFF-SRE'
        );

        $this->assertEquals(0, $art->view_count);

        $accessed = $this->service->recordArticleAccess('KB-PIR-BLACKOUT-01');
        $this->assertEquals(1, $accessed->view_count);

        // 476.4 Audit clean
        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);
    }

    public function test_expert_rotation_handover_blocked_edge_case(): void
    {
        // 476.5 Edge case: Sole domain expert cannot rotate without certified handover pack
        $this->service->recordKnowledgeArticle('KB-OBSCURE-TAX-CODE', 'regulatory_interpretation', 'USR-TAX-WIZARD');

        try {
            $this->service->certifyStaffRotation('USR-TAX-WIZARD', false); // No handover!
            $this->fail('Expected exception for uncertified staff rotation');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must complete formal institutional knowledge handover before reassignment', $e->getMessage());
        }

        // Certified handover passes
        $updated = $this->service->certifyStaffRotation('USR-TAX-WIZARD', true);
        $this->assertEquals(1, $updated);
    }
}
