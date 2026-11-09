<?php

declare(strict_types=1);

namespace App\Quality\Analyzers;

/**
 * Penganalisis Kompleksitas Kognitif (Cognitive Complexity) berstandar SonarSource.
 * Menghitung tingkat kesulitan mental pengembang dalam memahami alur kontrol kode.
 */
final class CognitiveComplexityAnalyzer
{
    /**
     * @return array{cognitive_score: int, nesting_depth_max: int}
     */
    public function calculate(string $source): array
    {
        $tokens = @token_get_all($source);
        $count = count($tokens);

        $score = 0;
        $currentNesting = 0;
        $maxNesting = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];

                if (in_array($id, [T_IF, T_FOR, T_FOREACH, T_WHILE, T_CATCH, T_SWITCH], true)) {
                    // Penambahan kompleksitas kognitif = 1 + nesting depth
                    $score += (1 + $currentNesting);
                } elseif ($id === T_ELSE || $id === T_ELSEIF) {
                    $score += 1; // Else tidak menaikkan penalti nesting, hanya +1
                } elseif ($id === T_BOOLEAN_AND || $id === T_BOOLEAN_OR) {
                    $score += 1;
                }
            } else {
                $char = $token;
                if ($char === '{') {
                    $currentNesting++;
                    if ($currentNesting > $maxNesting) {
                        $maxNesting = $currentNesting;
                    }
                } elseif ($char === '}') {
                    $currentNesting = max(0, $currentNesting - 1);
                } elseif ($char === '?') {
                    $score += (1 + $currentNesting);
                }
            }
        }

        return [
            'cognitive_score' => $score,
            'nesting_depth_max' => $maxNesting,
        ];
    }
}

