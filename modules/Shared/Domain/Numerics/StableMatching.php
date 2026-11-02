<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Numerics;

use InvalidArgumentException;

/**
 * Gale-Shapley (Deferred Acceptance) Many-to-One Stable Matching.
 *
 * Implements the candidate-proposing algorithm to find candidate-optimal stable matching
 * between candidates and organizations with capacity quotas.
 *
 * Guarantees zero blocking pairs:
 * No pair (c, o) exists where c strictly prefers o over their assigned organization,
 * and o has vacant capacity or strictly prefers c over an accepted candidate.
 *
 * Complexity: O(C * O) where C is candidates count and O is organizations count.
 */
final class StableMatching
{
    /**
     * @param  array<string|int, list<string|int>>|list<array{id: string|int, preferences: list<string|int>}>  $candidates
     * @param  array<string|int, array{capacity: int, preferences: list<string|int>}>|list<array{id: string|int, capacity: int, preferences: list<string|int>}>  $organizations
     * @return array{
     *     matches: array<string|int, list<string|int>>,
     *     candidate_matches: array<string|int, string|int|null>,
     *     unmatched_candidates: list<string|int>,
     *     remaining_capacities: array<string|int, int>,
     *     certificate: array{
     *         total_candidates: int,
     *         total_organizations: int,
     *         matched_candidates_count: int,
     *         unmatched_candidates_count: int,
     *         matches: array<string|int, list<string|int>>,
     *         remaining_capacities: array<string|int, int>
     *     }
     * }
     */
    public static function match(array $candidates, array $organizations): array
    {
        // Normalize candidates: candId => list<orgId>
        $candPrefs = [];
        foreach ($candidates as $key => $val) {
            if (is_array($val) && isset($val['id'], $val['preferences'])) {
                $candId = (string) $val['id'];
                $candPrefs[$candId] = array_map('strval', $val['preferences']);
            } elseif (is_array($val)) {
                $candId = (string) $key;
                $candPrefs[$candId] = array_map('strval', $val);
            } else {
                throw new InvalidArgumentException("Invalid candidate format near key '{$key}'");
            }
        }

        // Normalize organizations: orgId => ['capacity' => int, 'preferences' => list<candId>]
        $orgData = [];
        foreach ($organizations as $key => $val) {
            if (is_array($val) && isset($val['id'], $val['capacity'], $val['preferences'])) {
                $orgId = (string) $val['id'];
                $cap = (int) $val['capacity'];
                $prefs = array_map('strval', $val['preferences']);
            } elseif (is_array($val) && isset($val['capacity'], $val['preferences'])) {
                $orgId = (string) $key;
                $cap = (int) $val['capacity'];
                $prefs = array_map('strval', $val['preferences']);
            } else {
                throw new InvalidArgumentException("Invalid organization format near key '{$key}'");
            }

            if ($cap <= 0) {
                throw new InvalidArgumentException("Organization '{$orgId}' capacity must be positive, got: {$cap}");
            }

            $orgData[$orgId] = [
                'capacity' => $cap,
                'preferences' => $prefs,
            ];
        }

        // Build organization ranking lookup: orgId => [candId => rank] (lower rank = more preferred)
        $orgCandRank = [];
        foreach ($orgData as $orgId => $data) {
            foreach ($data['preferences'] as $rank => $candId) {
                $orgCandRank[$orgId][$candId] = $rank;
            }
        }

        // Queue of free candidates who still have organizations to propose to
        $freeCandidates = array_keys($candPrefs);
        sort($freeCandidates); // Deterministic tie break

        // Next organization index to propose to for each candidate
        $nextProposalIdx = array_fill_keys($freeCandidates, 0);

        // Current matches per organization: orgId => list<candId>
        $orgMatches = [];
        foreach (array_keys($orgData) as $orgId) {
            $orgMatches[$orgId] = [];
        }

        // Candidate matches: candId => orgId|null
        $candMatches = array_fill_keys($freeCandidates, null);

        $head = 0;
        while ($head < count($freeCandidates)) {
            $candId = $freeCandidates[$head++];

            $prefs = $candPrefs[$candId];
            $pIdx = $nextProposalIdx[$candId];

            if ($pIdx >= count($prefs)) {
                // Exhausted all preferred organizations, remains unmatched
                continue;
            }

            $targetOrgId = $prefs[$pIdx];
            $nextProposalIdx[$candId]++;

            if (! isset($orgData[$targetOrgId])) {
                // Target organization does not exist, candidate tries next
                $freeCandidates[] = $candId;

                continue;
            }

            // Check if organization considers this candidate acceptable
            if (! isset($orgCandRank[$targetOrgId][$candId])) {
                // Organization does not accept this candidate, candidate tries next
                $freeCandidates[] = $candId;

                continue;
            }

            $candRank = $orgCandRank[$targetOrgId][$candId];
            $capacity = $orgData[$targetOrgId]['capacity'];
            $currentMatched = $orgMatches[$targetOrgId];

            if (count($currentMatched) < $capacity) {
                // Organization has vacant seat, accept candidate
                $orgMatches[$targetOrgId][] = $candId;
                $candMatches[$candId] = $targetOrgId;
            } else {
                // Organization is full: find the worst currently matched candidate
                $worstCandId = null;
                $worstRank = -1;
                $worstIndex = -1;

                foreach ($currentMatched as $idx => $matchedCandId) {
                    $rank = $orgCandRank[$targetOrgId][$matchedCandId];
                    if ($rank > $worstRank) {
                        $worstRank = $rank;
                        $worstCandId = $matchedCandId;
                        $worstIndex = $idx;
                    }
                }

                if ($candRank < $worstRank) {
                    // New candidate is preferred over worst matched!
                    // Evict worst candidate
                    unset($currentMatched[$worstIndex]);
                    $currentMatched = array_values($currentMatched);
                    $currentMatched[] = $candId;
                    $orgMatches[$targetOrgId] = $currentMatched;

                    $candMatches[$candId] = $targetOrgId;
                    $candMatches[$worstCandId] = null;

                    // Evicted candidate becomes free again
                    $freeCandidates[] = $worstCandId;
                } else {
                    // Candidate is rejected, remains free and tries next
                    $freeCandidates[] = $candId;
                }
            }
        }

        // Sort matches per organization deterministically
        foreach ($orgMatches as $orgId => &$matchedList) {
            usort($matchedList, function ($a, $b) use ($orgCandRank, $orgId) {
                $rankA = $orgCandRank[$orgId][$a] ?? PHP_INT_MAX;
                $rankB = $orgCandRank[$orgId][$b] ?? PHP_INT_MAX;
                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }

                return strcmp($a, $b);
            });
        }
        unset($matchedList);

        $unmatched = [];
        $matchedCount = 0;
        foreach ($candMatches as $candId => $orgId) {
            if ($orgId === null) {
                $unmatched[] = $candId;
            } else {
                $matchedCount++;
            }
        }
        sort($unmatched);

        $remainingCapacities = [];
        foreach ($orgData as $orgId => $data) {
            $remainingCapacities[$orgId] = $data['capacity'] - count($orgMatches[$orgId]);
        }

        $certificate = [
            'total_candidates' => count($candPrefs),
            'total_organizations' => count($orgData),
            'matched_candidates_count' => $matchedCount,
            'unmatched_candidates_count' => count($unmatched),
            'matches' => $orgMatches,
            'remaining_capacities' => $remainingCapacities,
        ];

        $result = [
            'matches' => $orgMatches,
            'candidate_matches' => $candMatches,
            'unmatched_candidates' => $unmatched,
            'remaining_capacities' => $remainingCapacities,
            'certificate' => $certificate,
        ];

        BlockingPairVerifier::verify($result, $candPrefs, $orgData);

        return $result;
    }
}
