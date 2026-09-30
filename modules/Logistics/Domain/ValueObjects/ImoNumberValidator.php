<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\ValueObjects;

use InvalidArgumentException;

class ImoNumberValidator
{
    /**
     * Memvalidasi format dan check digit nomor IMO (International Maritime Organization).
     * Format: 7 digit numerik.
     * Algoritma: 6 digit pertama dikalikan bobot berturut-turut [7, 6, 5, 4, 3, 2].
     * Jumlah hasil perkalian mod 10 harus sama dengan digit ke-7 (check digit).
     */
    public static function isValid(string $imo): bool
    {
        $clean = strtoupper(trim($imo));
        if (str_starts_with($clean, 'IMO')) {
            $clean = trim(substr($clean, 3));
        }

        if (! preg_match('/^\d{7}$/', $clean)) {
            return false;
        }

        $weights = [7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 6; $i++) {
            $sum += ((int) $clean[$i]) * $weights[$i];
        }

        $checkDigit = $sum % 10;

        return $checkDigit === ((int) $clean[6]);
    }

    /**
     * Memvalidasi atau melempar InvalidArgumentException.
     */
    public static function validate(string $imo): string
    {
        $clean = strtoupper(trim($imo));
        if (str_starts_with($clean, 'IMO')) {
            $clean = trim(substr($clean, 3));
        }

        if (! self::isValid($clean)) {
            throw new InvalidArgumentException("Nomor IMO '{$imo}' tidak valid berdasarkan kalkulasi check digit IMO.");
        }

        return $clean;
    }

    /**
     * Menghitung check digit untuk 6 digit awal IMO.
     */
    public static function calculateCheckDigit(string $sixDigits): int
    {
        if (! preg_match('/^\d{6}$/', $sixDigits)) {
            throw new InvalidArgumentException('Input harus tepat 6 digit numerik.');
        }

        $weights = [7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 6; $i++) {
            $sum += ((int) $sixDigits[$i]) * $weights[$i];
        }

        return $sum % 10;
    }
}
