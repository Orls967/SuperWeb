<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Blind Index generator using HMAC-SHA256 with secret pepper (KONSEP §A13, DoR R3.2 / D2).
 * Enables exact-match database queries over encrypted PII fields (NIK, NPWP)
 * without exposing plaintext values or susceptible unkeyed SHA-256 hashes.
 */
final class BlindIndex
{
    /**
     * Normalize string by trimming and optionally removing spaces, dots, and hyphens (standard for NIK / NPWP).
     */
    public static function normalize(?string $value, bool $stripSeparators = true): string
    {
        if ($value === null) {
            return '';
        }

        $trimmed = trim($value);

        if ($stripSeparators) {
            return preg_replace('/[\s.\-]+/u', '', $trimmed) ?? '';
        }

        return $trimmed;
    }

    /**
     * Resolve the active pepper key.
     * Enforces non-empty pepper in production environment.
     */
    public static function pepper(?string $override = null): string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        $pepper = function_exists('config')
            ? (string) config('app.blind_index_pepper', '')
            : (getenv('BLIND_INDEX_PEPPER') ?: ($_ENV['BLIND_INDEX_PEPPER'] ?? ''));

        if ($pepper !== '') {
            return $pepper;
        }

        // Gagal jika di production lingkungan
        $isProduction = function_exists('app')
            ? (app()->environment('production') || config('app.env') === 'production')
            : ((getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')) === 'production');

        if ($isProduction) {
            throw new RuntimeException('BLIND_INDEX_PEPPER is required and cannot be empty in production environment.');
        }

        return '';
    }

    /**
     * Generate HMAC-SHA256 blind index for an arbitrary string value.
     */
    public static function generate(?string $value, ?string $pepper = null, bool $stripSeparators = true): ?string
    {
        $normalized = self::normalize($value, $stripSeparators);

        if ($normalized === '') {
            return null;
        }

        $activePepper = self::pepper($pepper);

        return hash_hmac('sha256', $normalized, $activePepper);
    }

    /**
     * Specialized blind index helper for NIK (Nomor Induk Kependudukan).
     */
    public static function forNik(?string $nik, ?string $pepper = null): ?string
    {
        return self::generate($nik, $pepper, stripSeparators: true);
    }

    /**
     * Specialized blind index helper for NPWP (Nomor Pokok Wajib Pajak).
     */
    public static function forNpwp(?string $npwp, ?string $pepper = null): ?string
    {
        return self::generate($npwp, $pepper, stripSeparators: true);
    }
}
