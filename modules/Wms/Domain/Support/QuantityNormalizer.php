<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Support;

use InvalidArgumentException;

/**
 * Normalisasi kuantitas ke integer base unit (D81).
 * Seluruh aritmetika kuantitas di Wms bebas floating-point.
 */
final class QuantityNormalizer
{
    private const array FACTOR_TO_BASE = [
        'pcs' => 1,
        'unit' => 1,
        'box' => 12,
        'pack' => 6,
        'carton' => 24,
        'pallet' => 480,
        'g' => 1,
        'kg' => 1000,
        'ml' => 1,
        'l' => 1000,
    ];

    /**
     * Mengonversi kuantitas integer dan UOM ke integer base unit.
     */
    public static function toBaseUnits(int $qty, string $uom = 'pcs', ?int $customPackSize = null): int
    {
        if ($qty < 0) {
            throw new InvalidArgumentException('Kuantitas tidak boleh negatif.');
        }

        if ($customPackSize !== null) {
            if ($customPackSize <= 0) {
                throw new InvalidArgumentException('Ukuran kemasan kustom harus lebih dari 0.');
            }

            return $qty * $customPackSize;
        }

        $key = strtolower(trim($uom));
        if (! isset(self::FACTOR_TO_BASE[$key])) {
            throw new InvalidArgumentException("Satuan UOM '{$uom}' tidak dikenali.");
        }

        return $qty * self::FACTOR_TO_BASE[$key];
    }

    /**
     * Mengonversi string desimal (mis. dari kolom legacy decimal(18,6)) ke integer base unit tanpa float.
     */
    public static function fromDecimalString(string $decimal): int
    {
        $trimmed = trim($decimal);
        if ($trimmed === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $trimmed)) {
            throw new InvalidArgumentException("Format kuantitas desimal tidak valid: '{$decimal}'.");
        }

        $parts = explode('.', $trimmed, 2);
        $whole = (int) $parts[0];
        if (isset($parts[1]) && rtrim($parts[1], '0') !== '') {
            // Pembulatan integer deterministik hanya bila ada pecahan non-nol pada data legacy
            $frac = substr(str_pad($parts[1], 6, '0'), 0, 6);
            $sign = $whole < 0 ? -1 : 1;
            $absWhole = abs($whole);
            if ((int) $frac >= 500000) {
                $absWhole++;
            }

            return $sign * $absWhole;
        }

        return $whole;
    }

    /**
     * Menghitung nilai total dalam IDR minor units (integer murni).
     */
    public static function computeValueIdr(int $qtyBase, int $unitCostIdr): int
    {
        if ($qtyBase < 0 || $unitCostIdr < 0) {
            throw new InvalidArgumentException('Kuantitas dan biaya satuan tidak boleh negatif.');
        }

        return $qtyBase * $unitCostIdr;
    }

    /**
     * Menghitung selisih dalam basis points (100% = 10000 bps) menggunakan aritmetika integer.
     */
    public static function varianceBps(int $expected, int $actual): int
    {
        if ($expected <= 0) {
            return $actual === 0 ? 0 : 10000;
        }

        $diff = $actual - $expected;

        return intdiv($diff * 10000, $expected);
    }
}
