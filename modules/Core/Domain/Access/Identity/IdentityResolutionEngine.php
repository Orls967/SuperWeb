<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Identity;

use Modules\Core\Domain\Access\Values\IdentityLinkScore;
use Modules\Core\Domain\Algorithms\Access\WeightedIdentityLinker;

/**
 * End-to-End Multi-Party Identity Resolution Engine (W8-D56).
 *
 * Coordinates multi-source attribute extraction, weighted similarity scoring,
 * double-consent verification, and identity graph topology analysis.
 */
final class IdentityResolutionEngine
{
    public function __construct(
        private readonly WeightedIdentityLinker $linker,
        private readonly IdentityGraphTraversal $traversal,
        private readonly IdentityConflictResolver $conflictResolver
    ) {}

    /**
     * Resolve identity match across two candidate profile dictionaries.
     *
     * @param  string  $archetype  CITIZEN, PATIENT, SUPPLIER, CARRIER
     * @param  array<string, mixed>  $profileA
     * @param  array<string, mixed>  $profileB
     * @return array{
     *     score: IdentityLinkScore,
     *     is_linked: bool,
     *     status: string,
     *     reconciliation_suggestions: list<array<string, mixed>>
     * }
     */
    public function resolveMatch(
        string $archetype,
        array $profileA,
        array $profileB,
        bool $sourceConsent,
        bool $targetConsent
    ): array {
        $weights = IdentityScoreMatrix::getWeights($archetype);

        $eval = $this->linker->evaluateLinkage(
            subjectA: $profileA,
            subjectB: $profileB,
            sourceConsent: $sourceConsent,
            targetConsent: $targetConsent
        );

        $hasDirect = false;
        if (! empty($profileA['nik']) && ! empty($profileB['nik']) && $profileA['nik'] === $profileB['nik']) {
            $hasDirect = true;
        }

        $scores = [];
        foreach ($weights as $attr => $weight) {
            $scores[$attr] = (float) ($eval['breakdown'][$attr] ?? 0.0);
        }

        $linkScore = IdentityLinkScore::compute(
            scores: $scores,
            weights: $weights,
            hasDirectMatch: $hasDirect,
            sourceConsent: $sourceConsent,
            targetConsent: $targetConsent
        );

        $suggestions = [];
        foreach ($profileA as $key => $valA) {
            if (isset($profileB[$key]) && $valA !== $profileB[$key]) {
                $suggestions[] = $this->conflictResolver->resolveConflict(
                    attribute: $key,
                    profileA: ['value' => $valA, 'source' => 'PROFILE_A', 'updated_at' => time() - 3600],
                    profileB: ['value' => $profileB[$key], 'source' => 'PROFILE_B', 'updated_at' => time()]
                );
            }
        }

        return [
            'score' => $linkScore,
            'is_linked' => $eval['is_linked'],
            'status' => $eval['status'],
            'reconciliation_suggestions' => $suggestions,
        ];
    }
}
