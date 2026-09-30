<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\ValueObjects;

use InvalidArgumentException;

class Iso6346Validator
{
    /**
     * Peta nilai karakter ISO 6346 (A=10 s/d Z=38, melewati kelipatan 11: 11, 22, 33).
     */
    protected const CHAR_VALUES = [
        'A' => 10, 'B' => 12, 'C' => 13, 'D' => 14, 'E' => 15,
        'F' => 16, 'G' => 17, 'H' => 18, 'I' => 19, 'J' => 20,
        'K' => 21, 'L' => 23, 'M' => 24, 'N' => 25, 'O' => 26,
        'P' => 27, 'Q' => 28, 'R' => 29, 'S' => 30, 'T' => 31,
        'U' => 32, 'V' => 34, 'W' => 35, 'X' => 36, 'Y' => 37,
        'Z' => 38,
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
    ];

    /**
     * Memvalidasi format ISO 6346 dan check digit.
     * Format: 3 huruf kode pemilik + 1 huruf kategori (U, J, atau Z) + 6 digit nomor seri + 1 digit check digit.
     */
    public static function isValid(string $containerNumber): bool
    {
        $clean = strtoupper(trim(str_replace([' ', '-'], '', $containerNumber)));

        if (! preg_match('/^[A-Z]{3}[UJZ]\d{6}\d$/', $clean)) {
            return false;
        }

        $calculated = self::calculateCheckDigit(substr($clean, 0, 10));

        return $calculated === ((int) $clean[10]);
    }

    /**
     * Memvalidasi atau melempar InvalidArgumentException.
     */
    public static function validate(string $containerNumber): string
    {
        $clean = strtoupper(trim(str_replace([' ', '-'], '', $containerNumber)));

        if (! self::isValid($clean)) {
            throw new InvalidArgumentException("Nomor kontainer '{$containerNumber}' tidak valid menurut spesifikasi ISO 6346.");
        }

        return $clean;
    }

    /**
     * Menghitung check digit ISO 6346 dari 10 karakter pertama (4 huruf + 6 digit).
     * Bobot: 2^posisi (2^0, 2^1, ..., 2^9).
     * Jumlah mod 11, jika hasil 10 maka check digit bernilai 0.
     */
    public static function calculateCheckDigit(string $tenChars): int
    {
        $clean = strtoupper(trim($tenChars));
        if (! preg_match('/^[A-Z]{3}[UJZ]\d{6}$/', $clean)) {
            throw new InvalidArgumentException('Input harus 10 karakter: 3 huruf pemilik + U/J/Z + 6 digit.');
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $char = $clean[$i];
            $val = self::CHAR_VALUES[$char] ?? 0;
            $weight = 2 ** $i;
            $sum += $val * $weight;
        }

        $mod = $sum % 11;

        return $mod === 10 ? 0 : $mod;
    }

    /**
     * Membuat nomor kontainer lengkap dengan check digit yang valid.
     */
    public static function generate(string $ownerCode, string $category, int|string $serial): string
    {
        $owner = strtoupper(substr($ownerCode, 0, 3));
        $cat = strtoupper(substr($category, 0, 1));
        $ser = sprintf('%06d', (int) $serial);

        $ten = $owner.$cat.$ser;
        $check = self::calculateCheckDigit($ten);

        return $ten.$check;
    }
}
