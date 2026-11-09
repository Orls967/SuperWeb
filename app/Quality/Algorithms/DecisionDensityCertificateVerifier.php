<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Verifier Sertifikat Kepadatan Keputusan Logika A2 (W8-D03).
 */
final class DecisionDensityCertificateVerifier
{
    private const SECRET = 'PlatformQualitySecretKey_A2';

    /**
     * @return array{is_valid: bool, reason: string}
     */
    public function verify(DecisionDensityCertificate $cert): array
    {
        // 1. Verifikasi tanda tangan HMAC
        $payload = json_encode([
            'source_hash' => $cert->sourceHash,
            'decision_points' => $cert->decisionPoints,
            'statements' => $cert->executableStatements,
            'density' => $cert->density,
            'methods' => $cert->methodCount,
            'properties' => $cert->propertyCount,
            'is_anemic' => $cert->isAnemic,
            'branches' => $cert->branchDistribution,
            'issued_at' => $cert->issuedAt,
        ], JSON_THROW_ON_ERROR);

        $expectedSig = hash_hmac('sha256', (string) $payload, self::SECRET);
        if (! hash_equals($expectedSig, $cert->signature)) {
            return [
                'is_valid' => false,
                'reason' => 'Tanda tangan HMAC sertifikat kepadatan keputusan tidak valid',
            ];
        }

        // 2. Verifikasi batas matematis
        if ($cert->decisionPoints < 0 || $cert->executableStatements < 0) {
            return [
                'is_valid' => false,
                'reason' => 'Decision points dan statements tidak boleh bernilai negatif',
            ];
        }

        $expectedDensity = $cert->executableStatements > 0
            ? round((float) ($cert->decisionPoints / $cert->executableStatements), 4)
            : 0.0;

        if (abs($expectedDensity - $cert->density) > 0.0001) {
            return [
                'is_valid' => false,
                'reason' => sprintf('Rasio kepadatan tidak konsisten: tercatat %.4f vs hasil komputasi %.4f', $cert->density, $expectedDensity),
            ];
        }

        // 3. Verifikasi kriteria anemik
        $expectedAnemic = $cert->decisionPoints === 0 && ($cert->propertyCount >= 3 || $cert->methodCount === 0);
        if ($expectedAnemic !== $cert->isAnemic) {
            return [
                'is_valid' => false,
                'reason' => 'Kategori anemik tidak konsisten dengan profil struktur data',
            ];
        }

        return [
            'is_valid' => true,
            'reason' => 'Sertifikat kepadatan keputusan A2 valid dan terverifikasi',
        ];
    }
}

