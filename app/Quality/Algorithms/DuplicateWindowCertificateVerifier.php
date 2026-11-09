<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Verifier Sertifikat Matematika Duplikasi Kode A1 (W8-D02).
 */
final class DuplicateWindowCertificateVerifier
{
    private const SECRET = 'PlatformQualitySecretKey_A1';

    /**
     * @return array{is_valid: bool, reason: string}
     */
    public function verify(DuplicateWindowCertificate $cert): array
    {
        // 1. Verifikasi tanda tangan HMAC
        $payload = json_encode([
            'source_hash' => $cert->sourceHash,
            'window_size' => $cert->windowSize,
            'basis' => $cert->basis,
            'modulus' => $cert->modulus,
            'total_tokens' => $cert->totalTokensAnalyzed,
            'dup_tokens' => $cert->duplicateTokenCount,
            'dup_ratio' => $cert->duplicateRatio,
            'blocks' => $cert->blocks,
            'issued_at' => $cert->issuedAt,
        ], JSON_THROW_ON_ERROR);

        $expectedSig = hash_hmac('sha256', (string) $payload, self::SECRET);
        if (! hash_equals($expectedSig, $cert->signature)) {
            return [
                'is_valid' => false,
                'reason' => 'Tanda tangan kriptografis HMAC sertifikat tidak cocok (integritas rusak atau dipalsukan)',
            ];
        }

        // 2. Verifikasi invarian batas matematis
        if ($cert->totalTokensAnalyzed < 0 || $cert->duplicateTokenCount < 0) {
            return [
                'is_valid' => false,
                'reason' => 'Jumlah token tidak boleh bernilai negatif',
            ];
        }

        if ($cert->duplicateTokenCount > $cert->totalTokensAnalyzed) {
            return [
                'is_valid' => false,
                'reason' => 'Token duplikasi tidak boleh melebihi total token yang dianalisis',
            ];
        }

        $computedRatio = $cert->totalTokensAnalyzed > 0
            ? round((float) ($cert->duplicateTokenCount / $cert->totalTokensAnalyzed), 6)
            : 0.0;

        if (abs($computedRatio - $cert->duplicateRatio) > 0.0001) {
            return [
                'is_valid' => false,
                'reason' => sprintf('Rasio duplikasi tidak konsisten: tercatat %.6f vs hasil hitung %.6f', $cert->duplicateRatio, $computedRatio),
            ];
        }

        // 3. Verifikasi panjang blok
        foreach ($cert->blocks as $block) {
            if ($block['length'] < $cert->windowSize) {
                return [
                    'is_valid' => false,
                    'reason' => sprintf('Panjang blok duplikasi (%d) di bawah ukuran minimum window (%d)', $block['length'], $cert->windowSize),
                ];
            }
        }

        return [
            'is_valid' => true,
            'reason' => 'Sertifikat duplikasi kode A1 valid, utuh, dan terverifikasi secara matematis',
        ];
    }

    /**
     * Menghitung kemiripan string menggunakan Jaro-Winkler distance
     */
    public function computeJaroWinklerSimilarity(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $matchDistance = (int) (floor(max($len1, $len2) / 2) - 1);
        $matches1 = array_fill(0, $len1, false);
        $matches2 = array_fill(0, $len2, false);

        $matches = 0;
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($matches2[$j]) {
                    continue;
                }
                if ($str1[$i] !== $str2[$j]) {
                    continue;
                }
                $matches1[$i] = true;
                $matches2[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (! $matches1[$i]) {
                continue;
            }
            while (! $matches2[$k]) {
                $k++;
            }
            if ($str1[$i] !== $str2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        $jaro = (($matches / $len1) + ($matches / $len2) + (($matches - ($transpositions / 2)) / $matches)) / 3.0;

        // Prefix bonus (Winkler)
        $prefixLen = 0;
        $maxPrefix = min(4, min($len1, $len2));
        for ($i = 0; $i < $maxPrefix; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefixLen++;
            } else {
                break;
            }
        }

        return $jaro + ($prefixLen * 0.1 * (1.0 - $jaro));
    }
}
