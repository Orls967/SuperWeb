<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Services;

use InvalidArgumentException;

/**
 * Engine Translasi Mata Uang Asing & Cadangan FCTR (PSAK 65 / IAS 21).
 * Melakukan translasi laporan keuangan entitas anak luar negeri ke mata uang pelaporan IDR:
 * - Pos Aset & Liabilitas ditranslasi menggunakan Kurs Penutup (Closing Rate)
 * - Pos Pendapatan & Beban ditranslasi menggunakan Kurs Rata-Rata Berbobot (Average Rate)
 * - Pos Modal Saham & Ekuitas Awal ditranslasi menggunakan Kurs Historis (Historical Rate)
 * - Selisih translasi dialokasikan ke Foreign Currency Translation Reserve (FCTR) di Ekuitas Konsolidasi.
 */
class FctrTranslationEngine
{
    /**
     * @param array{
     *     assets_foreign_minor: int,
     *     liabilities_foreign_minor: int,
     *     beginning_equity_foreign_minor: int,
     *     revenue_foreign_minor: int,
     *     expenses_foreign_minor: int,
     *     closing_rate_scaled: int,
     *     average_rate_scaled: int,
     *     historical_rate_scaled: int,
     *     rate_scale?: int
     * } $data
     * @return array{
     *     assets_idr_minor: int,
     *     liabilities_idr_minor: int,
     *     net_assets_foreign_minor: int,
     *     net_assets_idr_minor: int,
     *     beginning_equity_idr_minor: int,
     *     net_income_foreign_minor: int,
     *     net_income_idr_minor: int,
     *     fctr_reserve_idr_minor: int,
     *     total_equity_and_liabilities_idr_minor: int,
     *     is_balanced: bool
     * }
     */
    public function translateSubsidiary(array $data): array
    {
        $scale = $data['rate_scale'] ?? 10000;
        if ($scale <= 0) {
            throw new InvalidArgumentException('Skala kurs harus lebih besar dari nol.');
        }

        $closingRate = $data['closing_rate_scaled'];
        $avgRate = $data['average_rate_scaled'];
        $histRate = $data['historical_rate_scaled'];

        if ($closingRate <= 0 || $avgRate <= 0 || $histRate <= 0) {
            throw new InvalidArgumentException('Nilai kurs translasi harus positif.');
        }

        $assetsForeign = $data['assets_foreign_minor'];
        $liabilitiesForeign = $data['liabilities_foreign_minor'];
        $begEquityForeign = $data['beginning_equity_foreign_minor'];
        $revenueForeign = $data['revenue_foreign_minor'];
        $expensesForeign = $data['expenses_foreign_minor'];

        $netIncomeForeign = $revenueForeign - $expensesForeign;
        $netAssetsForeign = $assetsForeign - $liabilitiesForeign;

        // 1. Translasi Aset & Liabilitas pada Kurs Penutup (Closing Rate)
        $assetsIdr = (int) intdiv($assetsForeign * $closingRate, $scale);
        $liabilitiesIdr = (int) intdiv($liabilitiesForeign * $closingRate, $scale);
        $netAssetsIdr = $assetsIdr - $liabilitiesIdr;

        // 2. Translasi Laba Bersih pada Kurs Rata-Rata (Average Rate)
        $revenueIdr = (int) intdiv($revenueForeign * $avgRate, $scale);
        $expensesIdr = (int) intdiv($expensesForeign * $avgRate, $scale);
        $netIncomeIdr = $revenueIdr - $expensesIdr;

        // 3. Translasi Ekuitas Awal pada Kurs Historis (Historical Rate)
        $begEquityIdr = (int) intdiv($begEquityForeign * $histRate, $scale);

        // 4. Perhitungan FCTR (Foreign Currency Translation Reserve)
        // Aset Bersih IDR = Ekuitas Awal IDR + Laba Bersih IDR + FCTR Reserve IDR
        $fctrReserveIdr = $netAssetsIdr - ($begEquityIdr + $netIncomeIdr);

        $totalEquityAndLiabilitiesIdr = $liabilitiesIdr + $begEquityIdr + $netIncomeIdr + $fctrReserveIdr;
        $isBalanced = ($assetsIdr === $totalEquityAndLiabilitiesIdr);

        return [
            'assets_idr_minor' => $assetsIdr,
            'liabilities_idr_minor' => $liabilitiesIdr,
            'net_assets_foreign_minor' => $netAssetsForeign,
            'net_assets_idr_minor' => $netAssetsIdr,
            'beginning_equity_idr_minor' => $begEquityIdr,
            'net_income_foreign_minor' => $netIncomeForeign,
            'net_income_idr_minor' => $netIncomeIdr,
            'fctr_reserve_idr_minor' => $fctrReserveIdr,
            'total_equity_and_liabilities_idr_minor' => $totalEquityAndLiabilitiesIdr,
            'is_balanced' => $isBalanced,
        ];
    }
}
