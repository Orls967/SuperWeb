<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Penganalisis Jalur Keputusan Siklomatis (Cyclomatic Path Analyzer).
 */
final class CyclomaticPathAnalyzer
{
    /**
     * @return array{mccabe_metric: int, risk_level: string, branch_count: int}
     */
    public function analyzePaths(string $source): array
    {
        $tokens = @token_get_all($source);
        $decisionNodes = 0;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $id = $token[0];
                if (in_array($id, [
                    T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE,
                    T_CASE, T_CATCH, T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE,
                ], true)) {
                    $decisionNodes++;
                }
            }
        }

        // Rumus McCabe M = E - N + 2P (untuk graf terhubung tunggal M = D + 1)
        $mccabe = $decisionNodes + 1;

        $risk = match (true) {
            $mccabe > 20 => 'high_risk',
            $mccabe > 10 => 'moderate_risk',
            default => 'low_risk',
        };

        return [
            'mccabe_metric' => $mccabe,
            'risk_level' => $risk,
            'branch_count' => $decisionNodes,
        ];
    }
}
