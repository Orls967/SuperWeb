<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use RuntimeException;

/**
 * Verifier sertifikat matematis untuk Algoritma A1 WcagContrastCalculator.
 */
class ContrastCertificateVerifier
{
    private const SECRET_SALT = 'superweb:wcag-contrast-cert:v1';

    /**
     * Membubuhkan tanda tangan SHA-256 pada payload hasil kalkulasi kontras.
     *
     * @param array<string, mixed> $payload
     */
    public static function sign(array $payload): string
    {
        $canonical = self::canonicalString($payload);

        return hash_hmac('sha256', $canonical, self::SECRET_SALT);
    }

    /**
     * Memverifikasi validitas sertifikat kontras.
     * Melempar RuntimeException bila sertifikat rusak atau tidak cocok.
     *
     * @param array<string, mixed> $payload
     */
    public static function verify(array $payload, string $certificate): bool
    {
        $expected = self::sign($payload);
        if (! hash_equals($expected, $certificate)) {
            throw new RuntimeException('Sertifikat kontras warna tidak valid atau terkorupsi.');
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function canonicalString(array $payload): string
    {
        ksort($payload);
        unset($payload['certificate']);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
