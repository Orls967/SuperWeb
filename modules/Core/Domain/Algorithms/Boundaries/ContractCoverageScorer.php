<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic Contract Coverage Scorer using integer permille (0–1000).
 * Prevents IEEE 754 float precision errors; pure integer floor division via intdiv (R4-ALG-03).
 *
 * Complexity: O(M log M + V) on M modules and V violations (M <= 1,000). Pure integer arithmetic.
 */
final class ContractCoverageScorer
{
    /**
     * Calculate contract coverage scores from contract calls and boundary violations.
     *
     * @param  array<string, int>  $contractCalls  "Caller:Target" => count
     * @param  list<array{client?: string, caller?: string, owner?: string, target?: string, rule_id?: string}>  $violations
     * @return array{perModuleScores: array<string, int>, pairScores: array<string, int>, globalScore: int, totalContracts: int, totalViolations: int}
     */
    public function calculate(array $contractCalls, array $violations): array
    {
        // 1. Aggregate violations by pair "Caller:Target"
        $violationCounts = [];
        $callerViolations = [];

        foreach ($violations as $v) {
            $client = (string) ($v['client'] ?? $v['caller'] ?? 'Unknown');
            $owner = (string) ($v['owner'] ?? $v['target'] ?? 'Unknown');
            $pair = "{$client}:{$owner}";

            $violationCounts[$pair] = ($violationCounts[$pair] ?? 0) + 1;
            $callerViolations[$client] = ($callerViolations[$client] ?? 0) + 1;
        }

        // 2. Identify all unique caller modules
        $callers = [];
        foreach (array_keys($contractCalls) as $pair) {
            $parts = explode(':', $pair, 2);
            $callers[$parts[0]] = true;
        }
        foreach (array_keys($violationCounts) as $pair) {
            $parts = explode(':', $pair, 2);
            $callers[$parts[0]] = true;
        }

        $allCallers = array_keys($callers);
        sort($allCallers, SORT_STRING);

        // 3. Compute pairwise scores
        $allPairs = array_unique([...array_keys($contractCalls), ...array_keys($violationCounts)]);
        sort($allPairs, SORT_STRING);

        $pairScores = [];
        $totalContracts = 0;
        $totalViolations = 0;

        foreach ($allPairs as $pair) {
            $c = $contractCalls[$pair] ?? 0;
            $v = $violationCounts[$pair] ?? 0;
            $sum = $c + $v;

            $pairScores[$pair] = $sum === 0 ? 1000 : (int) intdiv($c * 1000, $sum);
            $totalContracts += $c;
            $totalViolations += $v;
        }

        // 4. Compute per-module outgoing scores
        $perModuleScores = [];
        foreach ($allCallers as $caller) {
            $cSum = 0;
            foreach ($contractCalls as $pair => $count) {
                if (str_starts_with($pair, "{$caller}:")) {
                    $cSum += $count;
                }
            }
            $vSum = $callerViolations[$caller] ?? 0;
            $sum = $cSum + $vSum;

            $perModuleScores[$caller] = $sum === 0 ? 1000 : (int) intdiv($cSum * 1000, $sum);
        }

        // 5. Compute global score
        $globalSum = $totalContracts + $totalViolations;
        $globalScore = $globalSum === 0 ? 1000 : (int) intdiv($totalContracts * 1000, $globalSum);

        return [
            'perModuleScores' => $perModuleScores,
            'pairScores' => $pairScores,
            'globalScore' => $globalScore,
            'totalContracts' => $totalContracts,
            'totalViolations' => $totalViolations,
        ];
    }

    /**
     * Compute integer permille scores and sort deterministically ascending by score, then ascending module name.
     *
     * @param  array<string, array{via_kontrak: int, langsung: int}>  $moduleStats
     * @return list<array{module: string, via_kontrak: int, langsung: int, score: int}>
     */
    public function rankModules(array $moduleStats): array
    {
        $ranked = [];

        foreach ($moduleStats as $module => $counts) {
            $via = max(0, (int) ($counts['via_kontrak'] ?? 0));
            $direct = max(0, (int) ($counts['langsung'] ?? 0));
            $total = $via + $direct;
            $score = $total === 0 ? 1000 : (int) intdiv($via * 1000, $total);

            $ranked[] = [
                'module' => (string) $module,
                'via_kontrak' => $via,
                'langsung' => $direct,
                'score' => $score,
            ];
        }

        usort($ranked, static fn (array $a, array $b): int => ($a['score'] <=> $b['score']) ?: strcmp($a['module'], $b['module']));

        return $ranked;
    }
}
