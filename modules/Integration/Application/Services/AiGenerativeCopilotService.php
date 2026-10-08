<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AiGenerativeCopilotService (Fase 198)
 *
 * Implements:
 *  - 198.1 Knowledge assistant answering with mandatory source citation
 *  - 198.2 SOP checklist completion with step proofs prior to closing
 *  - 198.3 Controlled content generation (draft -> human approval -> publish with sha256 hash)
 *  - 198.4 Hallucination guard: rejects numeric claims unless grounded in verified system queries
 */
class AiGenerativeCopilotService
{
    /**
     * Generate grounded assistant response. Rejects answer if source citation or query grounding is absent.
     */
    public function answerWithCitation(string $question, string $answer, ?string $citationDoc, bool $numbersGroundedInQuery = true): object
    {
        if (empty(trim((string) $citationDoc))) {
            throw new \RuntimeException('Knowledge grounding rejected: Answers must include citation to verified internal documents (ARCHITECTURE/RUNBOOK/CONTRACT).');
        }

        // Check if answer contains numbers/digits and enforce query grounding
        if (preg_match('/\d+/', $answer) && ! $numbersGroundedInQuery) {
            throw new \RuntimeException('Hallucination guard triggered: Numerical figures in response must originate from verified system queries.');
        }

        $code = 'ANS-'.strtoupper(Str::random(8));

        $id = DB::table('ai_assistant_responses')->insertGetId([
            'response_code' => $code,
            'user_question' => $question,
            'generated_answer' => $answer,
            'source_citation_doc' => $citationDoc,
            'has_system_query_grounding' => $numbersGroundedInQuery,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_assistant_responses')->find($id);
    }

    /**
     * Start SOP execution checklist.
     */
    public function startSopChecklist(string $sopCode, string $title, int $totalSteps): object
    {
        DB::table('ai_sop_checklists')->updateOrInsert(
            ['sop_code' => $sopCode],
            [
                'sop_title' => $title,
                'total_steps' => $totalSteps,
                'completed_steps' => 0,
                'proof_records' => json_encode([]),
                'is_closed' => false,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('ai_sop_checklists')->where('sop_code', $sopCode)->first();
    }

    /**
     * Record SOP step proof and close when all steps are completed.
     */
    public function recordSopStepProof(string $sopCode, string $stepProof): object
    {
        $sop = DB::table('ai_sop_checklists')->where('sop_code', $sopCode)->first();
        if (! $sop) {
            throw new \InvalidArgumentException("SOP {$sopCode} not found.");
        }

        $proofs = json_decode($sop->proof_records, true) ?? [];
        $proofs[] = $stepProof;
        $completed = count($proofs);
        $isClosed = ($completed >= (int) $sop->total_steps);

        DB::table('ai_sop_checklists')->where('sop_code', $sopCode)->update([
            'completed_steps' => $completed,
            'proof_records' => json_encode($proofs),
            'is_closed' => $isClosed,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_sop_checklists')->where('sop_code', $sopCode)->first();
    }

    /**
     * Generate controlled content draft.
     */
    public function createGenerativeDraft(string $docType, string $content): object
    {
        $code = 'DFT-'.strtoupper(Str::random(8));
        $hash = hash('sha256', $content);

        $id = DB::table('ai_generative_drafts')->insertGetId([
            'draft_code' => $code,
            'doc_type' => strtoupper($docType),
            'draft_content' => $content,
            'content_sha256' => $hash,
            'status' => 'DRAFT',
            'approved_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_generative_drafts')->find($id);
    }

    /**
     * Approve and publish draft. Enforces human approval requirement.
     */
    public function publishDraft(string $draftCode, string $approverName): object
    {
        if (empty(trim($approverName))) {
            throw new \InvalidArgumentException('Controlled generation error: Draft cannot be published without human approval.');
        }

        DB::table('ai_generative_drafts')->where('draft_code', $draftCode)->update([
            'status' => 'PUBLISHED',
            'approved_by' => $approverName,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ai_generative_drafts')->where('draft_code', $draftCode)->first();
    }

    /**
     * Quality audit gate (`copilot:audit`).
     */
    public function audit(): array
    {
        $unapprovedPublished = DB::table('ai_generative_drafts')
            ->where('status', 'PUBLISHED')
            ->whereNull('approved_by')
            ->count();

        return [
            'status' => $unapprovedPublished === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_responses' => DB::table('ai_assistant_responses')->count(),
            'total_sops' => DB::table('ai_sop_checklists')->count(),
            'total_drafts' => DB::table('ai_generative_drafts')->count(),
            'discrepancy_count' => $unapprovedPublished,
        ];
    }
}
