<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\AiGenerativeCopilotService;
use Tests\TestCase;

/**
 * Fase 198 — AI: Generative Content, Knowledge Assistant & SOP Copilot Tests
 *
 * Covers:
 *  (a) answers without verified citations are rejected
 *  (b) answers with unverified numbers are rejected (hallucination guard)
 *  (c) SOP checklist requires all steps and proofs before closing
 *  (d) generative drafts cannot be published without human approval
 *  (e) copilot:audit = 0 discrepancy
 */
class AiGenerativeCopilotTest extends TestCase
{
    use RefreshDatabase;

    protected AiGenerativeCopilotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiGenerativeCopilotService::class);
    }

    /**
     * (a) & (b) Knowledge assistant citation and hallucination guarding.
     */
    public function test_knowledge_assistant_citation_and_hallucination_guard(): void
    {
        // 1. Valid grounded answer with citation and system query -> SUCCESS
        $resp = $this->service->answerWithCitation(
            'Berapa batas kredit maksimal supplier?',
            'Berdasarkan kebijakan, batas maksimal adalah Rp 1,000,000,000 per entitas.',
            'docs/POLICIES/CREDIT_LIMIT_2026.md#Section-2',
            true
        );
        $this->assertSame('docs/POLICIES/CREDIT_LIMIT_2026.md#Section-2', $resp->source_citation_doc);

        // 2. Missing citation -> Exception
        try {
            $this->service->answerWithCitation(
                'Berapa batas kredit maksimal supplier?',
                'Batas kredit adalah Rp 1,000,000,000.',
                null,
                true
            );
            $this->fail('Expected exception for missing citation.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Answers must include citation', $e->getMessage());
        }

        // 3. Hallucination guard: unverified numeric figures -> Exception
        try {
            $this->service->answerWithCitation(
                'Berapa perkiraan GMV besok?',
                'Perkiraan GMV adalah Rp 500,000,000.',
                'docs/RUNBOOK.md',
                false // Not grounded in query
            );
            $this->fail('Expected exception for hallucination guard.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Numerical figures in response must originate from verified system queries', $e->getMessage());
        }
    }

    /**
     * (c) SOP checklist completion with proofs.
     */
    public function test_sop_checklist_step_proofs_and_closure(): void
    {
        $sop = $this->service->startSopChecklist('SOP-AIRCRAFT-PREFLIGHT-01', 'Aircraft Pre-Flight Walkaround', 2);
        $this->assertFalse((bool) $sop->is_closed);

        // Step 1 proof recorded
        $sopStep1 = $this->service->recordSopStepProof('SOP-AIRCRAFT-PREFLIGHT-01', 'SHA256:inspeksi_mesin_foto_hash');
        $this->assertFalse((bool) $sopStep1->is_closed);

        // Step 2 proof recorded -> Completed -> Closes automatically
        $sopStep2 = $this->service->recordSopStepProof('SOP-AIRCRAFT-PREFLIGHT-01', 'SIGN:pilot_in_command_signature_id');
        $this->assertTrue((bool) $sopStep2->is_closed);
        $this->assertSame(2, (int) $sopStep2->completed_steps);
    }

    /**
     * (d) Controlled content generation approval gating.
     */
    public function test_controlled_draft_approval_and_publish(): void
    {
        $draft = $this->service->createGenerativeDraft('CONTRACT', 'Draft Surat Perjanjian Kerja Sama Distribusi Sembako');
        $this->assertSame('DRAFT', $draft->status);
        $this->assertNotNull($draft->content_sha256);

        // Publish without approver -> Exception
        try {
            $this->service->publishDraft($draft->draft_code, '   ');
            $this->fail('Expected exception for publish without approver.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Draft cannot be published without human approval', $e->getMessage());
        }

        // Publish with approver -> SUCCESS
        $published = $this->service->publishDraft($draft->draft_code, 'Direktur Legal & Kepatuhan');
        $this->assertSame('PUBLISHED', $published->status);
        $this->assertSame('Direktur Legal & Kepatuhan', $published->approved_by);
    }

    /**
     * (e) Audit status healthy with 0 discrepancies.
     */
    public function test_copilot_audit(): void
    {
        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
