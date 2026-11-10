<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use RuntimeException;

/**
 * Verifier sertifikat kepatuhan aksesibilitas untuk Algoritma A3 A11yHtmlLinter.
 */
class A11yLintCertificateVerifier
{
    private const SECRET_SALT = 'superweb:a11y-lint-cert:v1';

    /**
     * Membubuhkan tanda tangan kriptografis SHA-256 pada payload hasil linter.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload): string
    {
        $canonical = self::canonicalString($payload);

        return hash_hmac('sha256', $canonical, self::SECRET_SALT);
    }

    /**
     * Memverifikasi keabsahan sertifikat linter.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function verify(array $payload, string $certificate): bool
    {
        $expected = self::sign($payload);
        if (! hash_equals($expected, $certificate)) {
            throw new RuntimeException('Sertifikat linter aksesibilitas tidak valid atau terkorupsi.');
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
