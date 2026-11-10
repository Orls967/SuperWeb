<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Search;

/**
 * Service redaksi dan sanitasi informasi sensitif (PII - Personally Identifiable Information)
 * untuk audit pencarian dan telemetri kepatuhan anti-kebocoran data.
 */
class PiiRedactorService
{
    /**
     * Pola regex deteksi PII sensitif:
     * - Nomor Kartu Kredit / Debit (13-16 digit)
     * - NIK / KTP Indonesia (16 digit)
     * - Nomor Telepon Seluler (+62 atau 08 diikuti 8-12 digit)
     * - Alamat Email
     */
    private const PATTERNS = [
        'credit_card' => '/\b(?:\d[ -]*?){13,16}\b/',
        'nik' => '/\b\d{16}\b/',
        'phone' => '/\b(?:\+62|62|08)[0-9]{8,12}\b/',
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
    ];

    /**
     * Melakukan masking terhadap teks kueri pencarian.
     */
    public function redact(string $text): string
    {
        $sanitized = $text;

        // Masking Email: e***l@domain.com
        $sanitized = preg_replace_callback(self::PATTERNS['email'], function ($matches) {
            $email = $matches[0];
            $parts = explode('@', $email);
            $name = $parts[0];
            $domain = $parts[1] ?? '';
            $len = strlen($name);

            if ($len <= 2) {
                return '**@'.$domain;
            }

            return substr($name, 0, 1).str_repeat('*', $len - 2).substr($name, -1).'@'.$domain;
        }, $sanitized);

        // Masking NIK: 3201************
        $sanitized = preg_replace_callback(self::PATTERNS['nik'], function ($matches) {
            $nik = $matches[0];

            return substr($nik, 0, 4).'************';
        }, $sanitized);

        // Masking Phone: 0812********
        $sanitized = preg_replace_callback(self::PATTERNS['phone'], function ($matches) {
            $phone = $matches[0];
            $len = strlen($phone);

            return substr($phone, 0, 4).str_repeat('*', max(4, $len - 4));
        }, $sanitized);

        // Masking Credit Card: 4111-****-****-1111
        $sanitized = preg_replace(self::PATTERNS['credit_card'], '[REDACTED_CARD]', $sanitized);

        return (string) $sanitized;
    }

    /**
     * Menghasilkan hash deterministik dari kueri asli untuk pengelompokan frekuensi telemetri
     * tanpa pernah menyimpan teks rahasia mentah.
     */
    public function hashQuery(string $rawQuery): string
    {
        return hash('sha256', mb_strtolower(trim($rawQuery)));
    }
}
