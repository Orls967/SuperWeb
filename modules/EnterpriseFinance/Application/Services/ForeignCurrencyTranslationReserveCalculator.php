<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Kalkulator Cadangan Translasi Mata Uang Asing (FCTR Engine) (54.2 - PSAK 10 / IAS 21).
 * Mengelola penyesuaian translasi laporan keuangan entitas anak luar negeri ke mata uang pelaporan IDR:
 * 1. Pos Aset dan Liabilitas ditranslasikan menggunakan Kurs Penutup (Closing Rate).
 * 2. Pos Ekuitas (Modal Saham & Saldo Laba Awal) ditranslasikan menggunakan Kurs Historis (Historical Rate).
 * 3. Pos Pendapatan dan Beban (Laba Rugi) ditranslasikan menggunakan Kurs Rata-Rata Tertimbang (Average Rate).
 * 4. Selisih bersih penyeimbang neraca dialokasikan ke Cadangan Translasi (FCTR) di pos Ekuitas.
 */
class ForeignCurrencyTranslationReserveCalculator
{
    /**
     * Menghitung translasi laporan keuangan entitas asing dan saldo cadangan FCTR.
     *
     * @param  int  $closingRateIdr  Kurs penutup akhir periode per 1 unit valas
     * @param  int  $averageRateIdr  Kurs rata-rata tertimbang per 1 unit valas
     * @param  int  $historicalRateIdr  Kurs historis perolehan per 1 unit valas
     * @return array{
     *     entity_code: string,
     *     foreign_currency: string,
     *     assets_idr: int,
     *     liabilities_idr: int,
     *     equity_historical_idr: int,
     *     net_income_idr: int,
     *     fctr_reserve_idr: int,
     *     is_fctr_surplus: bool,
     *     is_balanced: bool
     * }
     */
    public function calculateFctr(
        string $entityCode,
        string $foreignCurrency,
        int $foreignAssetsMinor,
        int $foreignLiabilitiesMinor,
        int $foreignBeginningEquityMinor,
        int $foreignNetIncomeMinor,
        int $closingRateIdr,
        int $averageRateIdr,
        int $historicalRateIdr
    ): array {
        if ($closingRateIdr <= 0 || $averageRateIdr <= 0 || $historicalRateIdr <= 0) {
            throw new InvalidArgumentException('Nilai kurs translasi mata uang harus bernilai positif.');
        }

        // 1. Translasi Aset & Liabilitas pada Kurs Penutup (Closing Rate)
        $assetsIdr = (int) intdiv($foreignAssetsMinor * $closingRateIdr, 100);
        $liabilitiesIdr = (int) intdiv($foreignLiabilitiesMinor * $closingRateIdr, 100);

        // Aset Bersih pada Kurs Penutup
        $netAssetsAtClosingRate = $assetsIdr - $liabilitiesIdr;

        // 2. Translasi Ekuitas Historis pada Kurs Historis (Historical Rate)
        $equityHistoricalIdr = (int) intdiv($foreignBeginningEquityMinor * $historicalRateIdr, 100);

        // 3. Translasi Laba Bersih Tahun Berjalan pada Kurs Rata-Rata (Average Rate)
        $netIncomeIdr = (int) intdiv($foreignNetIncomeMinor * $averageRateIdr, 100);

        // 4. Saldo FCTR adalah penyeimbang antara Aset Bersih (Closing Rate) dengan Ekuitas Total (Historical + Average)
        // Formula: Net Assets (Closing) = Equity (Hist) + Net Income (Avg) + FCTR Reserve
        $fctrReserveIdr = $netAssetsAtClosingRate - ($equityHistoricalIdr + $netIncomeIdr);

        // Neraca translasi: Total Aset IDR == Total Liabilitas IDR + Ekuitas Historis IDR + Laba Berjalan IDR + FCTR IDR
        $totalLiabAndEquity = $liabilitiesIdr + $equityHistoricalIdr + $netIncomeIdr + $fctrReserveIdr;
        $isBalanced = ($assetsIdr === $totalLiabAndEquity);

        return [
            'entity_code' => $entityCode,
            'foreign_currency' => $foreignCurrency,
            'assets_idr' => $assetsIdr,
            'liabilities_idr' => $liabilitiesIdr,
            'equity_historical_idr' => $equityHistoricalIdr,
            'net_income_idr' => $netIncomeIdr,
            'fctr_reserve_idr' => $fctrReserveIdr,
            'is_fctr_surplus' => ($fctrReserveIdr >= 0),
            'is_balanced' => $isBalanced,
        ];
    }
}
