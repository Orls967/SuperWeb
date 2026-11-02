<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Verifier for Stable Matching results (Gale-Shapley Deferred Acceptance).
 *
 * Verifies:
 * 1. Capacity constraints are respected by all organizations.
 * 2. Every assignment is mutually acceptable according to preferences.
 * 3. Bidirectional consistency between org matches and candidate matches.
 * 4. Stability invariant: No blocking pair (c, o) exists where:
 *    - c strictly prefers o over their assigned organization (or being unmatched), AND
 *    - o has vacant capacity or strictly prefers c over at least one currently matched candidate.
 *
 * Complexity: O(C * O) where C is candidates count and O is organizations count.
 */
final class BlockingPairVerifier
{
    /**
     * @param array{
     *     matches: array<string|int, list<string|int>>,
     *     candidate_matches: array<string|int, string|int|null>,
     *     unmatched_candidates: list<string|int>,
     *     remaining_capacities: array<string|int, int>,
     *     certificate?: array<string, mixed>
     * } $result
     * @param  array<string|int, list<string|int>>  $candidates
     * @param  array<string|int, array{capacity: int, preferences: list<string|int>}>  $organizations
     */
    public static function verify(array $result, array $candidates, array $organizations): bool
    {
        if (! isset($result['matches'], $result['candidate_matches'], $result['unmatched_candidates'], $result['remaining_capacities'])) {
            throw new InvalidArgumentException('Result array missing required keys.');
        }

        // Normalize candidate preferences: candId => [orgId => rank]
        $candPrefRanks = [];
        foreach ($candidates as $candId => $prefs) {
            $candKey = (string) $candId;
            $candPrefRanks[$candKey] = [];
            foreach ($prefs as $rank => $orgId) {
                $candPrefRanks[$candKey][(string) $orgId] = $rank;
            }
        }

        // Normalize organization data: orgId => [capacity => int, ranks => [candId => rank]]
        $orgCapacities = [];
        $orgPrefRanks = [];
        foreach ($organizations as $orgId => $orgData) {
            $orgKey = (string) $orgId;
            $orgCapacities[$orgKey] = (int) $orgData['capacity'];
            $orgPrefRanks[$orgKey] = [];
            foreach ($orgData['preferences'] as $rank => $candId) {
                $orgPrefRanks[$orgKey][(string) $candId] = $rank;
            }
        }

        // 1. Capacity & Acceptability verification
        $orgMatches = [];
        foreach ($result['matches'] as $orgId => $matchedList) {
            $orgKey = (string) $orgId;
            if (! isset($orgCapacities[$orgKey])) {
                throw new UnexpectedValueException("Unknown organization '{$orgKey}' in matches.");
            }

            $cap = $orgCapacities[$orgKey];
            $count = count($matchedList);
            if ($count > $cap) {
                throw new UnexpectedValueException("Organization '{$orgKey}' exceeded capacity: {$count} > {$cap}.");
            }

            $expectedRemaining = $cap - $count;
            $actualRemaining = (int) ($result['remaining_capacities'][$orgId] ?? -1);
            if ($actualRemaining !== $expectedRemaining) {
                throw new UnexpectedValueException("Organization '{$orgKey}' remaining capacity mismatch: expected {$expectedRemaining}, got {$actualRemaining}.");
            }

            $normalizedMatches = [];
            foreach ($matchedList as $candId) {
                $candKey = (string) $candId;
                if (! isset($orgPrefRanks[$orgKey][$candKey])) {
                    throw new UnexpectedValueException("Candidate '{$candKey}' is unacceptable to organization '{$orgKey}'.");
                }
                if (! isset($candPrefRanks[$candKey][$orgKey])) {
                    throw new UnexpectedValueException("Organization '{$orgKey}' is unacceptable to candidate '{$candKey}'.");
                }
                $normalizedMatches[] = $candKey;
            }
            $orgMatches[$orgKey] = $normalizedMatches;
        }

        // 2. Bidirectional consistency
        $candMatches = [];
        foreach ($result['candidate_matches'] as $candId => $matchedOrg) {
            $candKey = (string) $candId;
            $candMatches[$candKey] = $matchedOrg !== null ? (string) $matchedOrg : null;

            if ($candMatches[$candKey] !== null) {
                $assignedOrg = (string) $candMatches[$candKey];
                if (! isset($orgMatches[$assignedOrg]) || ! in_array($candKey, $orgMatches[$assignedOrg], true)) {
                    throw new UnexpectedValueException("Candidate '{$candKey}' matched to '{$assignedOrg}' but not in organization's match list.");
                }
            }
        }

        foreach ($orgMatches as $orgKey => $matchedList) {
            $orgKeyStr = (string) $orgKey;
            foreach ($matchedList as $candKey) {
                $candKeyStr = (string) $candKey;
                $assignedOrg = $candMatches[$candKeyStr] ?? null;
                if ($assignedOrg === null || (string) $assignedOrg !== $orgKeyStr) {
                    throw new UnexpectedValueException("Candidate '{$candKeyStr}' in organization '{$orgKeyStr}' matches but candidate_matches maps to ".var_export($assignedOrg, true).'.');
                }
            }
        }

        // 3. Stability check: verify no blocking pair (c, o) exists
        foreach ($candPrefRanks as $candKey => $candPrefs) {
            $currentAssignedOrg = $candMatches[$candKey] ?? null;
            $currentCandRank = $currentAssignedOrg !== null ? ($candPrefs[$currentAssignedOrg] ?? PHP_INT_MAX) : PHP_INT_MAX;

            // Check every organization that c strictly prefers over current assignment
            foreach ($candPrefs as $orgKey => $prefRank) {
                if ($prefRank >= $currentCandRank) {
                    // Candidate does not strictly prefer orgKey over current assignment
                    continue;
                }

                // If candidate prefers orgKey, check if orgKey would accept candidate c
                if (! isset($orgPrefRanks[$orgKey][$candKey])) {
                    // Organization does not consider candidate acceptable
                    continue;
                }

                $matchedCandidates = $orgMatches[$orgKey] ?? [];
                $orgCapacity = $orgCapacities[$orgKey] ?? 0;

                // Condition A: Organization has vacant quota
                if (count($matchedCandidates) < $orgCapacity) {
                    throw new UnexpectedValueException("Blocking pair detected: candidate '{$candKey}' and organization '{$orgKey}' prefer each other (organization has vacant quota).");
                }

                // Condition B: Organization prefers c over at least one currently matched candidate
                $candRankInOrg = $orgPrefRanks[$orgKey][$candKey];
                foreach ($matchedCandidates as $matchedCandKey) {
                    $matchedCandRank = $orgPrefRanks[$orgKey][$matchedCandKey];
                    if ($candRankInOrg < $matchedCandRank) {
                        throw new UnexpectedValueException("Blocking pair detected: candidate '{$candKey}' and organization '{$orgKey}' prefer each other over matched candidate '{$matchedCandKey}'.");
                    }
                }
            }
        }

        // 4. Verify certificate if present
        if (isset($result['certificate'])) {
            $cert = $result['certificate'];
            if (($cert['total_candidates'] ?? null) !== count($candidates)) {
                throw new UnexpectedValueException('Certificate total_candidates mismatch.');
            }
            if (($cert['total_organizations'] ?? null) !== count($organizations)) {
                throw new UnexpectedValueException('Certificate total_organizations mismatch.');
            }
            $expectedMatchedCount = count(array_filter($candMatches, static fn ($o) => $o !== null));
            if (($cert['matched_candidates_count'] ?? null) !== $expectedMatchedCount) {
                throw new UnexpectedValueException('Certificate matched_candidates_count mismatch.');
            }
            if (($cert['unmatched_candidates_count'] ?? null) !== count($result['unmatched_candidates'])) {
                throw new UnexpectedValueException('Certificate unmatched_candidates_count mismatch.');
            }
            if (($cert['matches'] ?? null) !== $result['matches']) {
                throw new UnexpectedValueException('Certificate matches mismatch.');
            }
            if (($cert['remaining_capacities'] ?? null) !== $result['remaining_capacities']) {
                throw new UnexpectedValueException('Certificate remaining_capacities mismatch.');
            }
        }

        return true;
    }
}
