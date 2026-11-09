<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Algoritma Pengukur Kompleksitas Software Halstead (Halstead Software Science Metrics 1977).
 * Menghitung volume program, tingkat kesulitan, usaha komputasi, dan estimasi potensi defek.
 */
final class HalsteadMetricsCalculator
{
    /**
     * @return array{
     *     unique_operators: int,
     *     unique_operands: int,
     *     total_operators: int,
     *     total_operands: int,
     *     program_vocabulary: int,
     *     program_length: int,
     *     calculated_length: float,
     *     volume: float,
     *     difficulty: float,
     *     effort: float,
     *     time_seconds: float,
     *     estimated_bugs: float
     * }
     */
    public function calculate(string $source): array
    {
        $tokens = @token_get_all($source);

        $operators = [];
        $operands = [];
        $totalOps = 0;
        $totalOpnds = 0;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $id = $token[0];
                $text = $token[1];

                if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                if (in_array($id, [
                    T_IF, T_ELSE, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_CASE,
                    T_DEFAULT, T_BREAK, T_CONTINUE, T_RETURN, T_THROW, T_TRY, T_CATCH, T_FINALLY,
                    T_NEW, T_CLONE, T_INSTANCEOF, T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE,
                ], true)) {
                    $operators[$text] = true;
                    $totalOps++;
                } elseif (in_array($id, [
                    T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER,
                ], true)) {
                    $operands[$text] = true;
                    $totalOpnds++;
                }
            } else {
                $char = trim($token);
                if ($char === '') {
                    continue;
                }

                if (in_array($char, ['+', '-', '*', '/', '%', '=', '!', '<', '>', '&', '|', '^', '~', '?', ':', '.'], true)) {
                    $operators[$char] = true;
                    $totalOps++;
                } elseif (in_array($char, [';', ',', '(', ')', '[', ']', '{', '}'], true)) {
                    $operators[$char] = true;
                    $totalOps++;
                }
            }
        }

        $eta1 = count($operators);
        $eta2 = count($operands);
        $n1 = $totalOps;
        $n2 = $totalOpnds;

        $vocabulary = $eta1 + $eta2;
        $length = $n1 + $n2;

        $calculatedLength = 0.0;
        if ($eta1 > 0 && $eta2 > 0) {
            $calculatedLength = ($eta1 * log($eta1, 2)) + ($eta2 * log($eta2, 2));
        }

        $volume = 0.0;
        if ($vocabulary > 0 && $length > 0) {
            $volume = $length * log($vocabulary, 2);
        }

        $difficulty = 0.0;
        if ($eta2 > 0) {
            $difficulty = ($eta1 / 2.0) * ($n2 / (float) $eta2);
        }

        $effort = $difficulty * $volume;
        $timeSeconds = $effort / 18.0;
        $estimatedBugs = $volume / 3000.0;

        return [
            'unique_operators' => $eta1,
            'unique_operands' => $eta2,
            'total_operators' => $n1,
            'total_operands' => $n2,
            'program_vocabulary' => $vocabulary,
            'program_length' => $length,
            'calculated_length' => round($calculatedLength, 2),
            'volume' => round($volume, 2),
            'difficulty' => round($difficulty, 2),
            'effort' => round($effort, 2),
            'time_seconds' => round($timeSeconds, 2),
            'estimated_bugs' => round($estimatedBugs, 4),
        ];
    }
}
