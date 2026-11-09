<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Estimator Kompleksitas NPath (NPath Complexity) berbasis Token AST.
 * Menghitung perkiraan jumlah jalur eksekusi asiklik independen dalam fungsi/metode.
 */
final class NPathComplexityEstimator
{
    /**
     * @return array{total_npath: int, functions: array<string, int>}
     */
    public function estimate(string $source): array
    {
        $tokens = @token_get_all($source);
        $count = count($tokens);

        $functions = [];
        $currentFunction = 'global';
        $currentNPath = 1;
        $inFunction = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];

                if ($id === T_FUNCTION) {
                    if ($inFunction) {
                        $functions[$currentFunction] = $currentNPath;
                    }
                    $inFunction = true;
                    $currentNPath = 1;

                    // Ambil nama fungsi
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $currentFunction = $tokens[$j][1];
                            break;
                        }
                    }
                } elseif ($id === T_IF || $id === T_ELSEIF) {
                    $currentNPath *= 2;
                } elseif ($id === T_CASE) {
                    $currentNPath += 1;
                } elseif ($id === T_FOR || $id === T_FOREACH || $id === T_WHILE) {
                    $currentNPath *= 2;
                } elseif ($id === T_CATCH) {
                    $currentNPath += 1;
                } elseif ($id === T_BOOLEAN_AND || $id === T_BOOLEAN_OR || $id === T_COALESCE) {
                    $currentNPath += 1;
                }
            }
        }

        if ($inFunction) {
            $functions[$currentFunction] = $currentNPath;
        }

        $total = 0;
        foreach ($functions as $npath) {
            $total += $npath;
        }

        return [
            'total_npath' => max(1, $total),
            'functions' => $functions,
        ];
    }
}

