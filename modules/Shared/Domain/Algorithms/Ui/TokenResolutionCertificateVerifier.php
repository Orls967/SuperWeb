<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use RuntimeException;

/**
 * Verifier sertifikat resolusi token untuk Algoritma A2 ThemeTokenResolver.
 */
class TokenResolutionCertificateVerifier
{
    private const SECRET_SALT = 'superweb:token-resolution-cert:v1';

    /**
     * Membubuhkan tanda tangan kriptografis SHA-256 pada payload hasil resolusi token.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload): string
    {
        $canonical = self::canonicalString($payload);

        return hash_hmac('sha256', $canonical, self::SECRET_SALT);
    }

    /**
     * Memverifikasi keabsahan sertifikat resolusi token.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function verify(array $payload, string $certificate): bool
    {
        $expected = self::sign($payload);
        if (! hash_equals($expected, $certificate)) {
            throw new RuntimeException('Sertifikat resolusi token tidak valid atau terkorupsi.');
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function canonicalString(array $payload): string
    {
        ksort($payload);
        unset($payload['certificate']);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
