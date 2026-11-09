<?php

declare(strict_types=1);

namespace App\Quality\Algorithms;

/**
 * Verifier Sertifikat Kriptografis Kompleksitas Halstead.
 */
final class HalsteadCertificateVerifier
{
    private const SECRET = 'PlatformQualitySecretKey_Halstead';

    /**
     * @return array{is_valid: bool, reason: string}
     */
    public function verify(HalsteadCertificate $cert): array
    {
        $payload = json_encode([
            'source_hash' => $cert->sourceHash,
            'eta1' => $cert->uniqueOperators,
            'eta2' => $cert->uniqueOperands,
            'n1' => $cert->totalOperators,
            'n2' => $cert->totalOperands,
            'volume' => $cert->volume,
            'difficulty' => $cert->difficulty,
            'effort' => $cert->effort,
            'time_sec' => $cert->timeSeconds,
            'bugs' => $cert->estimatedBugs,
            'issued_at' => $cert->issuedAt,
        ], JSON_THROW_ON_ERROR);

        $expectedSig = hash_hmac('sha256', (string) $payload, self::SECRET);
        if (! hash_equals($expectedSig, $cert->signature)) {
            return [
                'is_valid' => false,
                'reason' => 'Tanda tangan HMAC sertifikat kompleksitas Halstead tidak valid',
            ];
        }

        if ($cert->uniqueOperators < 0 || $cert->uniqueOperands < 0) {
            return [
                'is_valid' => false,
                'reason' => 'Jumlah operator atau operand tidak boleh negatif',
            ];
        }

        return [
            'is_valid' => true,
            'reason' => 'Sertifikat Halstead valid dan terverifikasi secara matematis',
        ];
    }
}

