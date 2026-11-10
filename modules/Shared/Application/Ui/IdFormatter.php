<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * IdFormatter
 *
 * Pemformatan lokalisasi baku Bahasa Indonesia (id-ID):
 * mata uang Rupiah integer, angka ribuan titik, tanggal lokal, dan zona waktu.
 */
class IdFormatter
{
    /**
     * Memformat bilangan bulat Rupiah: Rp 1.500.000.
     * Tidak menerima float sesuai PRINSIP U-1.
     */
    public static function formatRupiah(int $amount, bool $withSymbol = true): string
    {
        $formatted = number_format($amount, 0, ',', '.');

        return $withSymbol ? "Rp {$formatted}" : $formatted;
    }

    /**
     * Memformat angka integer standar dengan pemisah ribuan titik.
     */
    public static function formatNumber(int $number): string
    {
        return number_format($number, 0, ',', '.');
    }

    /**
     * Memformat persentase basis points (bps) ke string persen desimal: 4500 bps -> 45.00%.
     */
    public static function formatBps(int $bps, int $decimals = 2): string
    {
        $percent = $bps / 100.0;

        return number_format($percent, $decimals, ',', '.').'%';
    }

    /**
     * Memformat tanggal ke standar Indonesia: 10 November 2026 atau 10/11/2026.
     */
    public static function formatDate(
        DateTimeInterface|string|null $date,
        string $format = 'd/m/Y',
        string $timezone = 'Asia/Jakarta'
    ): string {
        if ($date === null) {
            return '-';
        }

        if (is_string($date)) {
            $parsed = \Carbon\Carbon::parse($date)->setTimezone($timezone);
        } elseif ($date instanceof CarbonInterface) {
            $parsed = $date->copy()->setTimezone($timezone);
        } else {
            $parsed = \Carbon\Carbon::instance($date)->setTimezone($timezone);
        }

        if ($format === 'human') {
            return $parsed->translatedFormat('d F Y, H:i').' WIB';
        }

        return $parsed->format($format);
    }
}

