<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Algoritma A2: Decision Density Counter (W8-D03).
 * Menghitung kepadatan keputusan logika (cyclomatic complexity density)
 * dan mendeteksi anemic data structures (M-3).
 */
final class DecisionDensityCounter
{
    public function analyze(string $source): DecisionMetrics
    {
        $tokens = @token_get_all($source);

        $decisionPoints = 0;
        $statements = 0;
        $methodCount = 0;
        $propertyCount = 0;

        $inClass = false;
        $inFunction = false;

        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];

                switch ($id) {
                    case T_IF:
                    case T_ELSEIF:
                    case T_FOR:
                    case T_FOREACH:
                    case T_WHILE:
                    case T_CASE:
                    case T_CATCH:
                    case T_BOOLEAN_AND:
                    case T_BOOLEAN_OR:
                    case T_COALESCE:
                        $decisionPoints++;
                        break;

                    case T_CLASS:
                        $inClass = true;
                        break;

                    case T_FUNCTION:
                        $methodCount++;
                        $inFunction = true;
                        break;

                    case T_VARIABLE:
                        // Jika di dalam class tapi di luar function, hitung sebagai property
                        if ($inClass && ! $inFunction) {
                            $propertyCount++;
                        }
                        break;

                    case T_RETURN:
                    case T_THROW:
                        $statements++;
                        break;
                }
            } else {
                $char = trim($token);
                if ($char === '?') {
                    // Cek apakah ini operator ternary atau nullable type hint (?string)
                    $nextNonWhitespace = null;
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j])) {
                            if ($tokens[$j][0] !== T_WHITESPACE && $tokens[$j][0] !== T_COMMENT && $tokens[$j][0] !== T_DOC_COMMENT) {
                                $nextNonWhitespace = $tokens[$j];
                                break;
                            }
                        } else {
                            $nextNonWhitespace = $tokens[$j];
                            break;
                        }
                    }

                    // Jika token berikutnya adalah identifier/type tanpa spasi atau dalam deklarasi tipe, abaikan nullable
                    $isNullableType = false;
                    if ($nextNonWhitespace !== null && is_array($nextNonWhitespace)) {
                        $nextId = $nextNonWhitespace[0];
                        if (in_array($nextId, [T_STRING, T_ARRAY], true)) {
                            // Cek jika jaraknya menempel langsung
                            if (isset($tokens[$i + 1]) && $tokens[$i + 1] === $nextNonWhitespace) {
                                $isNullableType = true;
                            }
                        }
                    }

                    if (! $isNullableType) {
                        $decisionPoints++;
                    }
                } elseif ($char === ';') {
                    $statements++;
                }
            }
        }

        $density = $statements > 0 ? (float) ($decisionPoints / $statements) : 0.0;
        $cert = hash('sha256', "dec:{$decisionPoints}:stmt:{$statements}:prop:{$propertyCount}");

        return new DecisionMetrics(
            decisionPoints: $decisionPoints,
            executableStatements: $statements,
            density: round($density, 4),
            methodCount: $methodCount,
            propertyCount: $propertyCount,
            certificate: $cert,
        );
    }
}
