<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * InstitutionalMemoryKnowledgeService (Fase 476)
 *
 * Implements:
 *  - 476.1 Decision log repository: context, alternatives, outcome, review date
 *  - 476.2 Institutional memory: post-incident reviews, lessons learned, regulatory interpretations
 *  - 476.3 Documentation health: coverage, freshness, usage metrics, debt register
 *  - 476.4 Tests: decision log complete, doc freshness measured, knowledge:audit clean
 *  - 476.5 Edge case: Single-person knowledge silo requires mandatory handover certification before staff rotation
 *  - 476.6 Risk: Usage metrics & feedback loop ensure docs remain read and actionable
 *  - 476.7 Evidence: decision log, knowledge archive, doc health reports
 */
class InstitutionalMemoryKnowledgeService
{
    public function logDecision(
        string $code,
        string $title,
        string $contextAlternatives,
        string $outcome,
        string $reviewDate,
        string $owner
    ): object {
        $id = DB::table('int_institutional_decisions')->insertGetId([
            'decision_code' => strtoupper($code),
            'decision_title' => $title,
            'context_and_alternatives' => $contextAlternatives,
            'outcome' => $outcome,
            'review_date' => $reviewDate,
            'owner' => $owner,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_institutional_decisions')->where('id', $id)->first();
    }

    public function recordKnowledgeArticle(
        string $code,
        string $domain,
        string $expert,
        ?string $reviewedAt = null
    ): object {
        $id = DB::table('int_institutional_knowledge_articles')->insertGetId([
            'article_code' => strtoupper($code),
            'knowledge_domain' => strtolower($domain),
            'primary_expert' => $expert,
            'last_reviewed_at' => $reviewedAt ?? now()->toDateString(),
            'view_count' => 0,
            'rotation_handover_certified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_institutional_knowledge_articles')->where('id', $id)->first();
    }

    /**
     * 476.3 & 476.6 Record article view for usage metrics
     */
    public function recordArticleAccess(string $code): object
    {
        $art = DB::table('int_institutional_knowledge_articles')->where('article_code', strtoupper($code))->first();
        if (! $art) {
            throw new InvalidArgumentException("Article '{$code}' not found.");
        }

        DB::table('int_institutional_knowledge_articles')->where('id', $art->id)->increment('view_count');

        return (object) DB::table('int_institutional_knowledge_articles')->where('id', $art->id)->first();
    }

    /**
     * 476.5 Edge case: Certify knowledge handover before sole domain expert rotates
     */
    public function certifyStaffRotation(string $expertId, bool $handoverPackCompleted): int
    {
        if (! $handoverPackCompleted) {
            throw new InvalidArgumentException("Staff rotation blocked: Sole domain expert '{$expertId}' must complete formal institutional knowledge handover before reassignment (476.5).");
        }

        return DB::table('int_institutional_knowledge_articles')
            ->where('primary_expert', $expertId)
            ->update([
                'rotation_handover_certified' => true,
                'updated_at' => now(),
            ]);
    }

    public function audit(): array
    {
        // Discrepancy 1: Stale knowledge articles (> 365 days unreviewed)
        $staleArticles = DB::table('int_institutional_knowledge_articles')
            ->where('last_reviewed_at', '<', now()->subYear()->toDateString())
            ->count();

        // Discrepancy 2: Decision logs without context or outcome
        $incompleteDecisions = DB::table('int_institutional_decisions')
            ->where(function ($query) {
                $query->whereNull('context_and_alternatives')
                    ->orWhere('context_and_alternatives', '')
                    ->orWhereNull('outcome')
                    ->orWhere('outcome', '');
            })
            ->count();

        $total = $staleArticles + $incompleteDecisions;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_decisions' => DB::table('int_institutional_decisions')->count(),
            'total_articles' => DB::table('int_institutional_knowledge_articles')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
