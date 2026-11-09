<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Rabin–Karp duplicate window detector (W8-D03, A1).
 *
 * Detects duplicated blocks of ≥ WINDOW_SIZE normalized lines across multiple files
 * using rolling hash (Horner mod P, basis 257). Candidates with matching hashes are
 * verified against actual content to eliminate false positives.
 *
 * Complexity: O(n) average per file where n = number of normalized lines.
 *
 * Normalization: tokens without whitespace/comments, variables → $v,
 * string literals → S, numeric literals → N. Hash per line = crc32.
 * Window hash = Horner(fp[0..WINDOW-1], basis=257, mod=P).
 *
 * Limits: minimum window = 10 lines. Blocks below window are not reported.
 * Consecutive overlapping windows with matching hashes are merged into a single block.
 *
 * @see DuplicateWindowCertificate
 * @see DuplicateWindowCertificateVerifier
 */
final class DuplicateWindowDetector
{
    public const WINDOW_SIZE = 10;

    public const DEFAULT_MODULUS = 2_147_483_647;

    public const BASIS = 257;

    private int $windowSize;

    public function __construct(int $windowSize = self::WINDOW_SIZE)
    {
        $this->windowSize = max(4, $windowSize);
    }

    /**
     * Analisis kode untuk mendeteksi duplikasi token antar fungsi/blok (Rabin-Karp Horner basis 257).
     */
    public function analyzeCode(string $code, string $filePath = 'input.php'): DuplicateReport
    {
        $tokens = @token_get_all($code);
        $normalizedTokens = [];

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $id = $token[0];
                $text = $token[1];

                if ($id === T_OPEN_TAG || $id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                    continue;
                }

                if ($id === T_VARIABLE) {
                    $normalizedTokens[] = '$v';
                } elseif ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
                    $normalizedTokens[] = 'S';
                } elseif ($id === T_LNUMBER || $id === T_DNUMBER) {
                    $normalizedTokens[] = 'N';
                } else {
                    $normalizedTokens[] = $text;
                }
            } else {
                $trimmed = trim($token);
                if ($trimmed !== '') {
                    $normalizedTokens[] = $trimmed;
                }
            }
        }

        $totalTokens = count($normalizedTokens);
        $w = $this->windowSize;

        if ($totalTokens < $w) {
            return new DuplicateReport(0, 0.0, [], $totalTokens, hash('sha256', $code));
        }

        $basis = self::BASIS;
        $modulus = 1_000_000_007;

        // basis^(w-1) mod P
        $highPower = 1;
        for ($i = 0; $i < $w - 1; $i++) {
            $highPower = ($highPower * $basis) % $modulus;
        }

        $hashes = [];
        $hash = 0;
        for ($i = 0; $i < $w; $i++) {
            $val = crc32($normalizedTokens[$i]) % $modulus;
            $hash = (($hash * $basis) % $modulus + $val) % $modulus;
        }
        $hashes[$hash][] = 0;

        for ($i = 1; $i <= $totalTokens - $w; $i++) {
            $oldVal = crc32($normalizedTokens[$i - 1]) % $modulus;
            $newVal = crc32($normalizedTokens[$i + $w - 1]) % $modulus;
            $hash = ((($hash - ($oldVal * $highPower % $modulus) + $modulus) % $modulus) * $basis % $modulus + $newVal) % $modulus;
            $hashes[$hash][] = $i;
        }

        $duplicatePositions = [];
        $duplicateBlocks = [];

        foreach ($hashes as $h => $positions) {
            if (count($positions) < 2) {
                continue;
            }

            for ($p1 = 0; $p1 < count($positions); $p1++) {
                for ($p2 = $p1 + 1; $p2 < count($positions); $p2++) {
                    $idx1 = $positions[$p1];
                    $idx2 = $positions[$p2];

                    if (abs($idx1 - $idx2) < $w) {
                        continue; // Overlapping window
                    }

                    // Verifikasi token
                    $matched = true;
                    for ($k = 0; $k < $w; $k++) {
                        if ($normalizedTokens[$idx1 + $k] !== $normalizedTokens[$idx2 + $k]) {
                            $matched = false;
                            break;
                        }
                    }

                    if ($matched) {
                        // Verifikasi bahwa blok token memuat logika komputasi nyata
                        // (mengeliminasi false positive dari deklarasi properti/konstruktor monoton)
                        $hasComputation = false;
                        for ($k = 0; $k < $w; $k++) {
                            $tok = $normalizedTokens[$idx1 + $k];
                            if (in_array($tok, ['if', 'for', 'foreach', 'while', 'return', '+', '-', '*', '/', '%', '==', '!=', '===', '!==', '<', '>', '&&', '||'], true)) {
                                $hasComputation = true;
                                break;
                            }
                        }

                        if (! $hasComputation) {
                            continue;
                        }

                        $duplicateBlocks[] = new DuplicateBlock($filePath, $idx1, $filePath, $idx2, $w);
                        for ($k = 0; $k < $w; $k++) {
                            $duplicatePositions[$idx1 + $k] = true;
                            $duplicatePositions[$idx2 + $k] = true;
                        }
                    }
                }
            }
        }

        $duplicateTokenCount = count($duplicatePositions);
        $ratio = $totalTokens > 0 ? min(1.0, $duplicateTokenCount / $totalTokens) : 0.0;
        $cert = hash('sha256', json_encode(array_map(fn (DuplicateBlock $b) => $b->toArray(), $duplicateBlocks)));

        return new DuplicateReport(
            duplicateCount: count($duplicateBlocks),
            duplicateRatio: $ratio,
            blocks: $duplicateBlocks,
            totalLinesAnalyzed: $totalTokens,
            sha256Certificate: $cert,
        );
    }

    /**
     * @param  array<string, list<string>>  $files  map of filename → list of normalized line strings
     */
    public function detect(array $files, int $modulus = self::DEFAULT_MODULUS): DuplicateWindowResult
    {
        $windowSize = $this->windowSize;
        $basis = self::BASIS;

        // Precompute basis^(windowSize-1) mod P
        $highPower = 1;
        for ($i = 0; $i < $windowSize - 1; $i++) {
            $highPower = ($highPower * $basis) % $modulus;
        }

        // Phase 1: compute line fingerprints and rolling window hashes
        /** @var array<int, list<array{file: string, index: int}>> $windowHashIndex */
        $windowHashIndex = [];

        /** @var array<string, list<int>> $lineFingerprints */
        $lineFingerprints = [];

        foreach ($files as $filename => $lines) {
            $lineCount = count($lines);
            if ($lineCount < $windowSize) {
                continue;
            }

            // Compute crc32 fingerprint per line
            $fps = [];
            foreach ($lines as $line) {
                $fps[] = crc32($line);
            }
            $lineFingerprints[$filename] = $fps;

            // Rolling hash via Horner
            $hash = 0;
            for ($i = 0; $i < $windowSize; $i++) {
                $fp = (($fps[$i] % $modulus) + $modulus) % $modulus;
                $hash = (($hash * $basis) % $modulus + $fp) % $modulus;
            }

            $windowHashIndex[$hash][] = ['file' => $filename, 'index' => 0];

            for ($i = 1; $i <= $lineCount - $windowSize; $i++) {
                $oldFp = (($fps[$i - 1] % $modulus) + $modulus) % $modulus;
                $newFp = (($fps[$i + $windowSize - 1] % $modulus) + $modulus) % $modulus;

                $hash = ((($hash - $oldFp * $highPower % $modulus + $modulus) % $modulus) * $basis % $modulus + $newFp) % $modulus;

                $windowHashIndex[$hash][] = ['file' => $filename, 'index' => $i];
            }
        }

        // Phase 2: find candidates (hashes with ≥ 2 occurrences)
        $candidates = [];
        foreach ($windowHashIndex as $hash => $locations) {
            if (count($locations) >= 2) {
                $candidates[$hash] = $locations;
            }
        }

        // Phase 3: verify candidates by comparing actual line content
        /** @var list<DuplicateBlock> $verifiedBlocks */
        $verifiedBlocks = [];
        $seen = [];

        foreach ($candidates as $hash => $locations) {
            // Group by actual content to separate true duplicates from collisions
            $contentGroups = [];
            foreach ($locations as $loc) {
                $file = $loc['file'];
                $idx = $loc['index'];
                $content = implode("\n", array_slice($files[$file], $idx, $windowSize));
                $contentGroups[$content][] = $loc;
            }

            foreach ($contentGroups as $content => $groupLocs) {
                if (count($groupLocs) < 2) {
                    continue;
                }

                // Skip if all locations are from the same file at the same index
                // (shouldn't happen, but defensive)
                foreach ($groupLocs as $i => $locA) {
                    for ($j = $i + 1; $j < count($groupLocs); $j++) {
                        $locB = $groupLocs[$j];
                        $pairKey = $this->pairKey($locA, $locB);
                        if (isset($seen[$pairKey])) {
                            continue;
                        }
                        $seen[$pairKey] = true;

                        // Try to extend block beyond the window
                        $blockLen = $this->extendBlock(
                            $files[$locA['file']],
                            $locA['index'],
                            $files[$locB['file']],
                            $locB['index'],
                            $windowSize
                        );

                        $verifiedBlocks[] = new DuplicateBlock(
                            fileA: $locA['file'],
                            indexA: $locA['index'],
                            fileB: $locB['file'],
                            indexB: $locB['index'],
                            length: $blockLen,
                        );
                    }
                }
            }
        }

        // Phase 4: merge overlapping blocks
        $merged = $this->mergeOverlapping($verifiedBlocks);

        return new DuplicateWindowResult(
            candidateCount: count($candidates),
            blocks: $merged,
        );
    }

    /**
     * Normalize a line of PHP code: tokenize, strip whitespace/comments,
     * replace variables with $v, string literals with S, numeric literals with N.
     */
    public static function normalizeLine(string $line): string
    {
        $tokens = @\PhpToken::tokenize('<?php '.$line);
        if ($tokens === false) {
            return $line;
        }

        $parts = [];
        foreach ($tokens as $token) {
            if ($token->is(T_OPEN_TAG)) {
                continue;
            }
            if ($token->isIgnorable()) {
                continue;
            }

            if ($token->is(T_VARIABLE)) {
                $parts[] = '$v';
            } elseif ($token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) {
                $parts[] = 'S';
            } elseif ($token->is([T_LNUMBER, T_DNUMBER])) {
                $parts[] = 'N';
            } else {
                $parts[] = $token->text;
            }
        }

        return implode('', $parts);
    }

    /**
     * Normalize all lines of a PHP file for duplication detection.
     *
     * @return list<string>
     */
    public static function normalizeFileLines(string $source): array
    {
        $rawLines = explode("\n", $source);
        $normalized = [];

        foreach ($rawLines as $raw) {
            $trimmed = trim($raw);
            if ($trimmed === '' || $trimmed === '{' || $trimmed === '}') {
                continue;
            }

            // Skip pure comment lines
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')
                || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, '/**')) {
                continue;
            }

            // Skip open/close tags and declare
            if (str_starts_with($trimmed, '<?php') || str_starts_with($trimmed, 'declare(')
                || str_starts_with($trimmed, 'namespace ') || str_starts_with($trimmed, 'use ')) {
                continue;
            }

            $normalized[] = self::normalizeLine($trimmed);
        }

        return $normalized;
    }

    private function extendBlock(array $linesA, int $startA, array $linesB, int $startB, int $minLen): int
    {
        $maxExtend = min(count($linesA) - $startA, count($linesB) - $startB);
        $len = $minLen;

        while ($len < $maxExtend && $linesA[$startA + $len] === $linesB[$startB + $len]) {
            $len++;
        }

        return $len;
    }

    /**
     * @param  array{file: string, index: int}  $a
     * @param  array{file: string, index: int}  $b
     */
    private function pairKey(array $a, array $b): string
    {
        $items = [
            $a['file'].':'.$a['index'],
            $b['file'].':'.$b['index'],
        ];
        sort($items);

        return implode('|', $items);
    }

    /**
     * @param  list<DuplicateBlock>  $blocks
     * @return list<DuplicateBlock>
     */
    private function mergeOverlapping(array $blocks): array
    {
        if (count($blocks) <= 1) {
            return $blocks;
        }

        // Sort by fileA, indexA, fileB, indexB
        usort($blocks, static function (DuplicateBlock $a, DuplicateBlock $b): int {
            return [$a->fileA, $a->indexA, $a->fileB, $a->indexB]
                <=> [$b->fileA, $b->indexA, $b->fileB, $b->indexB];
        });

        $merged = [$blocks[0]];

        for ($i = 1; $i < count($blocks); $i++) {
            $last = $merged[count($merged) - 1];
            $curr = $blocks[$i];

            if ($last->fileA === $curr->fileA
                && $last->fileB === $curr->fileB
                && $curr->indexA <= $last->indexA + $last->length
                && $curr->indexB <= $last->indexB + $last->length) {
                // Merge
                $endA = max($last->indexA + $last->length, $curr->indexA + $curr->length);
                $merged[count($merged) - 1] = new DuplicateBlock(
                    fileA: $last->fileA,
                    indexA: $last->indexA,
                    fileB: $last->fileB,
                    indexB: $last->indexB,
                    length: $endA - $last->indexA,
                );
            } else {
                $merged[] = $curr;
            }
        }

        return $merged;
    }
}
