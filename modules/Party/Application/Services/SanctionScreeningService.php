<?php

declare(strict_types=1);

namespace Modules\Party\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Party\Domain\Enums\SanctionCheckStatus;
use Modules\Party\Domain\Models\Party;
use Modules\Party\Domain\Models\SanctionCheck;

class SanctionScreeningService
{
    private const FUZZY_THRESHOLD = 70.0; // 0-100 score

    /**
     * Screen a party against the simulated sanctions list.
     * Records result in pty_sanctions_checks (idempotent per trigger).
     */
    public function screen(Party $party, string $trigger = 'onboarding'): SanctionCheck
    {
        // Idempotent: if already screened for this trigger within last 24h, return existing
        $existing = $party->sanctionsChecks()
            ->where('trigger', $trigger)
            ->where('created_at', '>=', now()->subHours(24))
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        [$status, $hits, $score] = $this->performScreening($party);

        return DB::transaction(function () use ($party, $trigger, $status, $hits, $score) {
            return SanctionCheck::create([
                'party_id' => $party->id,
                'trigger' => $trigger,
                'status' => $status->value,
                'hits' => $hits,
                'match_score' => $score,
            ]);
        });
    }

    /**
     * Perform the actual matching against pty_sanctions_lists.
     *
     * @return array{SanctionCheckStatus, array, float}
     */
    private function performScreening(Party $party): array
    {
        $nameNorm = strtolower(trim($party->name));
        $hits = [];
        $maxScore = 0.0;

        // 1. Direct identifier match (NPWP hash)
        if ($party->npwp_hash) {
            $identifierEntries = DB::table('pty_sanctions_lists')
                ->where('is_active', true)
                ->whereNotNull('identifier')
                ->select('id', 'identifier')
                ->get();

            foreach ($identifierEntries as $entry) {
                if ($entry->identifier) {
                    $rawMatch = hash('sha256', strtolower($entry->identifier)) === $party->npwp_hash;
                    $normMatch = Party::hashIdentifier($entry->identifier) === $party->npwp_hash;
                    if ($rawMatch || $normMatch) {
                        $hits[] = ['id' => $entry->id, 'score' => 100.0];
                        $maxScore = 100.0;
                    }
                }
            }
        }

        // 2. Exact match on normalized name (indexed O(1))
        $exactMatches = DB::table('pty_sanctions_lists')
            ->where('is_active', true)
            ->where('name_normalized', $nameNorm)
            ->select('id', 'name_normalized')
            ->get();

        foreach ($exactMatches as $match) {
            $hits[] = ['id' => $match->id, 'score' => 100.0];
            $maxScore = 100.0;
        }

        // 3. Blocking-key candidate search (prefix-based)
        if ($maxScore < 90.0 && strlen($nameNorm) >= 3) {
            $prefix = substr($nameNorm, 0, min(4, strlen($nameNorm)));
            $candidates = DB::table('pty_sanctions_lists')
                ->where('is_active', true)
                ->where('name_normalized', 'LIKE', "{$prefix}%")
                ->select('id', 'name_normalized', 'identifier')
                ->limit(200)
                ->get();

            foreach ($candidates as $entry) {
                $score = $this->similarityScore($nameNorm, $entry->name_normalized);
                if ($score >= self::FUZZY_THRESHOLD) {
                    $hits[] = ['id' => $entry->id, 'score' => round($score, 2)];
                    $maxScore = max($maxScore, $score);
                }
            }
        }

        // 4. Fallback chunked evaluation for short names or if prefix yielded no hits
        if (empty($hits)) {
            DB::table('pty_sanctions_lists')
                ->where('is_active', true)
                ->select('id', 'name_normalized', 'identifier')
                ->orderBy('id')
                ->chunkById(500, function ($batch) use ($nameNorm, $party, &$hits, &$maxScore) {
                    foreach ($batch as $entry) {
                        $score = $this->similarityScore($nameNorm, $entry->name_normalized);

                        $identifierMatch = $entry->identifier && $party->npwp_hash
                            && hash('sha256', strtolower($entry->identifier)) === $party->npwp_hash;

                        if ($identifierMatch) {
                            $score = 100.0;
                        }

                        if ($score >= self::FUZZY_THRESHOLD) {
                            $hits[] = ['id' => $entry->id, 'score' => round($score, 2)];
                            $maxScore = max($maxScore, $score);
                        }
                    }
                });
        }

        if (empty($hits)) {
            return [SanctionCheckStatus::Clear, [], 0.0];
        }

        // Deduplicate hits by ID keeping highest score
        $uniqueHits = [];
        foreach ($hits as $hit) {
            $id = $hit['id'];
            if (! isset($uniqueHits[$id]) || $hit['score'] > $uniqueHits[$id]['score']) {
                $uniqueHits[$id] = $hit;
            }
        }
        $hits = array_values($uniqueHits);

        // Score >= 90 = definite hit, else manual review
        $status = $maxScore >= 90.0
            ? SanctionCheckStatus::Hit
            : SanctionCheckStatus::ManualReview;

        return [$status, $hits, $maxScore];
    }

    /**
     * Simple trigram-based similarity score (0-100).
     */
    private function similarityScore(string $a, string $b): float
    {
        similar_text($a, $b, $percent);

        return $percent;
    }
}
