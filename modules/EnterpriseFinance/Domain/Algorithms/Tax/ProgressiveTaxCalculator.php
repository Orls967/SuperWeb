<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Tax;

use InvalidArgumentException;

/**
 * Kalkulator Pajak Penghasilan Progresif dan Tarif Efektif Rata-Rata (TER) Nasional.
 * Seluruh kalkulasi beroperasi pada bilangan bulat minor unit (sen) tanpa tipe data float (KONSEP §A3).
 */
final class ProgressiveTaxCalculator
{
    /**
     * Ambang Batas Lapisan Tarif Pajak Penghasilan Orang Pribadi (UU HPP No. 7/2021 Pasal 17 ayat 1a).
     * Seluruh nilai dalam minor unit IDR (1 Rupiah = 100 minor unit).
     */
    public const BRACKET_1_CAP_MINOR = 60_000_000_00;   // s.d. Rp 60.000.000 (5%)
    public const BRACKET_2_CAP_MINOR = 250_000_000_00;  // > Rp 60 jt s.d. Rp 250 jt (15%)
    public const BRACKET_3_CAP_MINOR = 500_000_000_00;  // > Rp 250 jt s.d. Rp 500 jt (25%)
    public const BRACKET_4_CAP_MINOR = 5_000_000_000_00;// > Rp 500 jt s.d. Rp 5 Miliar (30%)
    // > Rp 5 Miliar (35%)

    public const RATE_BRACKET_1_BPS = 500;  // 5.00% = 500 basis points
    public const RATE_BRACKET_2_BPS = 1500; // 15.00% = 1500 basis points
    public const RATE_BRACKET_3_BPS = 2500; // 25.00% = 2500 basis points
    public const RATE_BRACKET_4_BPS = 3000; // 30.00% = 3000 basis points
    public const RATE_BRACKET_5_BPS = 3500; // 35.00% = 3500 basis points

    /**
     * Nilai Penghasilan Tidak Kena Pajak (PTKP) Tahunan Standar Nasional.
     */
    public const PTKP_TK0_MINOR = 54_000_000_00;
    public const PTKP_TANGGUNGAN_TAMBAHAN_MINOR = 4_500_000_00;

    /**
     * Menghitung PTKP Tahunan berdasarkan kode status keluarga (TK/0 s.d. K/3).
     */
    public static function calculatePtkpAnnual(string $ptkpCode): int
    {
        $code = strtoupper(trim($ptkpCode));
        $base = self::PTKP_TK0_MINOR;

        return match ($code) {
            'TK/0', 'TK0' => $base,
            'TK/1', 'TK1' => $base + (1 * self::PTKP_TANGGUNGAN_TAMBAHAN_MINOR),
            'TK/2', 'TK2' => $base + (2 * self::PTKP_TANGGUNGAN_TAMBAHAN_MINOR),
            'TK/3', 'TK3' => $base + (3 * self::PTKP_TANGGUNGAN_TAMBAHAN_MINOR),
            'K/0', 'K0'   => $base + self::PTKP_TANGGUNGAN_TAMBAHAN_MINOR, // Status kawin
            'K/1', 'K1'   => $base + (2 * self::PTKP_TANGGUNGAN_TAMBAHAN_MINOR),
            'K/2', 'K2'   => $base + (3 * self::PTKP_TANGGUNGAN_TAMBAHAN_MINOR),
            'K/3', 'K3'   => $base + (4 * self::PTKP_TANGGUNGAN_TAMBAHAN_MINOR),
            default       => throw new InvalidArgumentException("Kode status PTKP tidak valid: [{$ptkpCode}]"),
        };
    }

    /**
     * Menghitung Pajak Penghasilan Tahunan Progresif Pasal 17 UU HPP atas Penghasilan Kena Pajak (PKP).
     *
     * @param int $pkpAnnualMinor Nilai PKP dalam minor IDR (dibulatkan ke ribuan Rupiah ke bawah).
     * @param bool $hasNpwp Apakah wajib pajak memiliki NPWP sah (bila tidak, tarif 20% lebih tinggi).
     * @return array{
     *     tax_total_minor: int,
     *     bracket_1_minor: int,
     *     bracket_2_minor: int,
     *     bracket_3_minor: int,
     *     bracket_4_minor: int,
     *     bracket_5_minor: int,
     *     effective_rate_bps: int
     * }
     */
    public static function calculateArticle17AnnualTax(int $pkpAnnualMinor, bool $hasNpwp = true): array
    {
        if ($pkpAnnualMinor <= 0) {
            return [
                'tax_total_minor' => 0,
                'bracket_1_minor' => 0,
                'bracket_2_minor' => 0,
                'bracket_3_minor' => 0,
                'bracket_4_minor' => 0,
                'bracket_5_minor' => 0,
                'effective_rate_bps' => 0,
            ];
        }

        $remaining = $pkpAnnualMinor;
        $b1Tax = 0;
        $b2Tax = 0;
        $b3Tax = 0;
        $b4Tax = 0;
        $b5Tax = 0;

        // Lapisan 1: 0 s.d. 60 Juta (5%)
        $b1Amount = min($remaining, self::BRACKET_1_CAP_MINOR);
        if ($b1Amount > 0) {
            $b1Tax = intdiv($b1Amount * self::RATE_BRACKET_1_BPS, 10000);
            $remaining -= $b1Amount;
        }

        // Lapisan 2: > 60 Juta s.d. 250 Juta (15%)
        $b2Span = self::BRACKET_2_CAP_MINOR - self::BRACKET_1_CAP_MINOR;
        $b2Amount = min($remaining, $b2Span);
        if ($b2Amount > 0) {
            $b2Tax = intdiv($b2Amount * self::RATE_BRACKET_2_BPS, 10000);
            $remaining -= $b2Amount;
        }

        // Lapisan 3: > 250 Juta s.d. 500 Juta (25%)
        $b3Span = self::BRACKET_3_CAP_MINOR - self::BRACKET_2_CAP_MINOR;
        $b3Amount = min($remaining, $b3Span);
        if ($b3Amount > 0) {
            $b3Tax = intdiv($b3Amount * self::RATE_BRACKET_3_BPS, 10000);
            $remaining -= $b3Amount;
        }

        // Lapisan 4: > 500 Juta s.d. 5 Miliar (30%)
        $b4Span = self::BRACKET_4_CAP_MINOR - self::BRACKET_3_CAP_MINOR;
        $b4Amount = min($remaining, $b4Span);
        if ($b4Amount > 0) {
            $b4Tax = intdiv($b4Amount * self::RATE_BRACKET_4_BPS, 10000);
            $remaining -= $b4Amount;
        }

        // Lapisan 5: > 5 Miliar (35%)
        if ($remaining > 0) {
            $b5Tax = intdiv($remaining * self::RATE_BRACKET_5_BPS, 10000);
        }

        $subtotal = $b1Tax + $b2Tax + $b3Tax + $b4Tax + $b5Tax;

        // Penalti Non-NPWP: 120% dari tarif normal
        $totalTax = $hasNpwp ? $subtotal : intdiv($subtotal * 120, 100);

        $effectiveRateBps = $pkpAnnualMinor > 0
            ? intdiv($totalTax * 10000, $pkpAnnualMinor)
            : 0;

        return [
            'tax_total_minor' => $totalTax,
            'bracket_1_minor' => $b1Tax,
            'bracket_2_minor' => $b2Tax,
            'bracket_3_minor' => $b3Tax,
            'bracket_4_minor' => $b4Tax,
            'bracket_5_minor' => $b5Tax,
            'effective_rate_bps' => $effectiveRateBps,
        ];
    }

    /**
     * Mengkategorikan status PTKP ke dalam Kategori TER (A, B, atau C) sesuai PP 58/2023.
     */
    public static function resolveTerCategory(string $ptkpCode): string
    {
        $code = strtoupper(trim($ptkpCode));

        return match ($code) {
            'TK/0', 'TK0', 'TK/1', 'TK1', 'K/0', 'K0' => 'A',
            'TK/2', 'TK2', 'TK/3', 'TK3', 'K/1', 'K1', 'K/2', 'K2' => 'B',
            'K/3', 'K3' => 'C',
            default => 'A',
        };
    }

    /**
     * Mencari Tarif Efektif Bulanan (TER) PP 58/2023 berdasarkan penghasilan bruto bulanan (dalam basis points).
     */
    public static function lookupTerMonthlyRateBps(string $terCategory, int $monthlyGrossMinor): int
    {
        if ($monthlyGrossMinor <= 0) {
            return 0;
        }

        return match (strtoupper($terCategory)) {
            'A' => self::lookupTerCategoryARate($monthlyGrossMinor),
            'B' => self::lookupTerCategoryBRate($monthlyGrossMinor),
            'C' => self::lookupTerCategoryCRate($monthlyGrossMinor),
            default => throw new InvalidArgumentException("Kategori TER tidak dikenal: [{$terCategory}]"),
        };
    }

    /**
     * Tabel TER Kategori A (TK/0, TK/1, K/0).
     */
    private static function lookupTerCategoryARate(int $gross): int
    {
        // Nilai dalam minor IDR
        if ($gross <= 5_400_000_00) return 0;
        if ($gross <= 5_650_000_00) return 25;   // 0.25%
        if ($gross <= 5_950_000_00) return 50;   // 0.50%
        if ($gross <= 6_300_000_00) return 75;   // 0.75%
        if ($gross <= 6_750_000_00) return 100;  // 1.00%
        if ($gross <= 7_500_000_00) return 125;  // 1.25%
        if ($gross <= 8_550_000_00) return 150;  // 1.50%
        if ($gross <= 9_650_000_00) return 175;  // 1.75%
        if ($gross <= 10_050_000_00) return 200; // 2.00%
        if ($gross <= 10_350_000_00) return 225; // 2.25%
        if ($gross <= 10_700_000_00) return 250; // 2.50%
        if ($gross <= 11_050_000_00) return 300; // 3.00%
        if ($gross <= 11_600_000_00) return 350; // 3.50%
        if ($gross <= 12_500_000_00) return 400; // 4.00%
        if ($gross <= 13_750_000_00) return 500; // 5.00%
        if ($gross <= 15_100_000_00) return 600; // 6.00%
        if ($gross <= 16_950_000_00) return 700; // 7.00%
        if ($gross <= 19_750_000_00) return 800; // 8.00%
        if ($gross <= 24_100_000_00) return 900; // 9.00%
        if ($gross <= 26_450_000_00) return 1000;// 10.00%
        if ($gross <= 28_000_000_00) return 1100;// 11.00%
        if ($gross <= 30_050_000_00) return 1200;// 12.00%
        if ($gross <= 32_400_000_00) return 1300;// 13.00%
        if ($gross <= 35_400_000_00) return 1400;// 14.00%
        if ($gross <= 39_100_000_00) return 1500;// 15.00%
        if ($gross <= 43_850_000_00) return 1600;// 16.00%
        if ($gross <= 47_800_000_00) return 1700;// 17.00%
        if ($gross <= 51_400_000_00) return 1800;// 18.00%
        if ($gross <= 56_300_000_00) return 1900;// 19.00%
        if ($gross <= 62_200_000_00) return 2000;// 20.00%
        if ($gross <= 68_600_000_00) return 2100;// 21.00%
        if ($gross <= 77_500_000_00) return 2200;// 22.00%
        if ($gross <= 89_000_000_00) return 2300;// 23.00%
        if ($gross <= 103_000_000_00) return 2400;// 24.00%
        if ($gross <= 125_000_000_00) return 2500;// 25.00%
        if ($gross <= 157_000_000_00) return 2600;// 26.00%
        if ($gross <= 206_000_000_00) return 2700;// 27.00%
        if ($gross <= 337_000_000_00) return 2800;// 28.00%
        if ($gross <= 454_000_000_00) return 2900;// 29.00%
        if ($gross <= 550_000_000_00) return 3000;// 30.00%
        if ($gross <= 695_000_000_00) return 3100;// 31.00%
        if ($gross <= 910_000_000_00) return 3200;// 32.00%
        if ($gross <= 1_400_000_000_00) return 3300;// 33.00%

        return 3400; // 34.00%
    }

    /**
     * Tabel TER Kategori B (TK/2, TK/3, K/1, K/2).
     */
    private static function lookupTerCategoryBRate(int $gross): int
    {
        if ($gross <= 6_200_000_00) return 0;
        if ($gross <= 6_500_000_00) return 25;
        if ($gross <= 6_850_000_00) return 50;
        if ($gross <= 7_300_000_00) return 75;
        if ($gross <= 9_200_000_00) return 100;
        if ($gross <= 10_750_000_00) return 150;
        if ($gross <= 11_250_000_00) return 200;
        if ($gross <= 12_600_000_00) return 300;
        if ($gross <= 13_600_000_00) return 400;
        if ($gross <= 14_950_000_00) return 500;
        if ($gross <= 16_400_000_00) return 600;
        if ($gross <= 18_450_000_00) return 700;
        if ($gross <= 21_850_000_00) return 800;
        if ($gross <= 26_000_000_00) return 900;
        if ($gross <= 27_700_000_00) return 1000;
        if ($gross <= 29_350_000_00) return 1100;
        if ($gross <= 31_450_000_00) return 1200;
        if ($gross <= 33_950_000_00) return 1300;
        if ($gross <= 37_100_000_00) return 1400;
        if ($gross <= 41_100_000_00) return 1500;
        if ($gross <= 45_800_000_00) return 1600;
        if ($gross <= 49_500_000_00) return 1700;
        if ($gross <= 53_800_000_00) return 1800;
        if ($gross <= 58_500_000_00) return 1900;
        if ($gross <= 64_000_000_00) return 2000;
        if ($gross <= 71_000_000_00) return 2100;
        if ($gross <= 80_000_000_00) return 2200;
        if ($gross <= 93_000_000_00) return 2300;
        if ($gross <= 109_000_000_00) return 2400;
        if ($gross <= 129_000_000_00) return 2500;
        if ($gross <= 163_000_000_00) return 2600;
        if ($gross <= 211_000_000_00) return 2700;
        if ($gross <= 374_000_000_00) return 2800;
        if ($gross <= 459_000_000_00) return 2900;
        if ($gross <= 555_000_000_00) return 3000;
        if ($gross <= 704_000_000_00) return 3100;
        if ($gross <= 957_000_000_00) return 3200;
        if ($gross <= 1_405_000_000_00) return 3300;

        return 3400;
    }

    /**
     * Tabel TER Kategori C (K/3).
     */
    private static function lookupTerCategoryCRate(int $gross): int
    {
        if ($gross <= 6_600_000_00) return 0;
        if ($gross <= 6_950_000_00) return 25;
        if ($gross <= 7_350_000_00) return 50;
        if ($gross <= 7_800_000_00) return 75;
        if ($gross <= 8_850_000_00) return 100;
        if ($gross <= 9_800_000_00) return 125;
        if ($gross <= 10_950_000_00) return 150;
        if ($gross <= 11_200_000_00) return 175;
        if ($gross <= 12_050_000_00) return 200;
        if ($gross <= 12_950_000_00) return 300;
        if ($gross <= 14_150_000_00) return 400;
        if ($gross <= 15_550_000_00) return 500;
        if ($gross <= 17_050_000_00) return 600;
        if ($gross <= 19_500_000_00) return 700;
        if ($gross <= 22_700_000_00) return 800;
        if ($gross <= 24_700_000_00) return 900;
        if ($gross <= 27_300_000_00) return 1000;
        if ($gross <= 29_050_000_00) return 1100;
        if ($gross <= 31_000_000_00) return 1200;
        if ($gross <= 33_300_000_00) return 1300;
        if ($gross <= 36_000_000_00) return 1400;
        if ($gross <= 39_600_000_00) return 1500;
        if ($gross <= 43_800_000_00) return 1600;
        if ($gross <= 47_800_000_00) return 1700;
        if ($gross <= 51_400_000_00) return 1800;
        if ($gross <= 56_300_000_00) return 1900;
        if ($gross <= 62_200_000_00) return 2000;
        if ($gross <= 68_600_000_00) return 2100;
        if ($gross <= 77_500_000_00) return 2200;
        if ($gross <= 89_000_000_00) return 2300;
        if ($gross <= 103_000_000_00) return 2400;
        if ($gross <= 125_000_000_00) return 2500;
        if ($gross <= 157_000_000_00) return 2600;
        if ($gross <= 206_000_000_00) return 2700;
        if ($gross <= 337_000_000_00) return 2800;
        if ($gross <= 454_000_000_00) return 2900;
        if ($gross <= 550_000_000_00) return 3000;
        if ($gross <= 719_000_000_00) return 3100;
        if ($gross <= 1_015_000_000_00) return 3200;
        if ($gross <= 1_410_000_000_00) return 3300;

        return 3400;
    }
}

