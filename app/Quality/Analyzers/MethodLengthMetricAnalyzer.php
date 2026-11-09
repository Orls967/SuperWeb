<?php

declare(strict_types=1);

namespace App\Quality\Analyzers;

/**
 * Penganalisis panjang baris metode (Method Length) dan deteksi fungsi raksasa (Long Method Smell).
 */
final class MethodLengthMetricAnalyzer
{
    public const MAX_RECOMMENDED_LINES = 60;

    /**
     * @return array{method_count: int, max_lines: int, long_methods: list<array{name: string, lines: int}>}
     */
    public function analyze(string $source): array
    {
        $tokens = @token_get_all($source);
        $count = count($tokens);

        $methods = [];
        $currentName = null;
        $startLine = 0;
        $braceDepth = 0;
        $inFunction = false;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $id = $token[0];
                if ($id === T_FUNCTION) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $currentName = $tokens[$j][1];
                            $startLine = $tokens[$j][2];
                            break;
                        }
                    }
                }
            } else {
                $char = $token;
                if ($char === '{') {
                    if ($currentName !== null && ! $inFunction) {
                        $inFunction = true;
                        $braceDepth = 1;
                    } elseif ($inFunction) {
                        $braceDepth++;
                    }
                } elseif ($char === '}') {
                    if ($inFunction) {
                        $braceDepth--;
                        if ($braceDepth === 0) {
                            // Akhir fungsi
                            $endLine = $startLine;
                            if (isset($tokens[$i - 1]) && is_array($tokens[$i - 1])) {
                                $endLine = $tokens[$i - 1][2];
                            }
                            $len = max(1, $endLine - $startLine + 1);
                            $methods[] = ['name' => (string) $currentName, 'lines' => $len];

                            $inFunction = false;
                            $currentName = null;
                        }
                    }
                }
            }
        }

        $max = 0;
        $long = [];
        foreach ($methods as $m) {
            if ($m['lines'] > $max) {
                $max = $m['lines'];
            }
            if ($m['lines'] > self::MAX_RECOMMENDED_LINES) {
                $long[] = $m;
            }
        }

        return [
            'method_count' => count($methods),
            'max_lines' => $max,
            'long_methods' => $long,
        ];
    }
}

