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

        $entries = DB::table('pty_sanctions_lists')
            ->where('is_active', true)
            ->select('id', 'name_normalized', 'identifier')
            ->get();

        $hits = [];
        $maxScore = 0.0;

        foreach ($entries as $entry) {
            $score = $this->similarityScore($nameNorm, $entry->name_normalized);

            // Also check identifier match (NPWP hash)
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

        if (empty($hits)) {
            return [SanctionCheckStatus::Clear, [], 0.0];
        }

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
