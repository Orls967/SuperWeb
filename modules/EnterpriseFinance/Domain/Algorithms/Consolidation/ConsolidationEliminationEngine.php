<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Algorithms\Consolidation;

use InvalidArgumentException;

/**
 * Mesin Eliminasi Transaksi Antar-Perusahaan (Intercompany Eliminations) dan Konsolidasi Grup.
 * Seluruh kalkulasi beroperasi pada bilangan bulat minor unit (sen) tanpa tipe data float (KONSEP §A3).
 */
final class ConsolidationEliminationEngine
{
    /**
     * Mengeliminasi Penjualan Antar-Perusahaan dan Laba Belum Terealisasi pada Persediaan Akhir.
     *
     * @param  int  $intercompanySalesMinor  Total nilai transaksi penjualan antar entitas grup.
     * @param  int  $endingInventoryMinor  Nilai persediaan akhir pembeli yang berasal dari pembelian antar entitas.
     * @param  int  $sellerGrossMarginBps  Persentase margin kotor penjual dalam basis points (misal: 2500 untuk 25.00%).
     * @return array{
     *     sales_elimination_minor: int,
     *     cogs_reduction_minor: int,
     *     unrealized_inventory_profit_minor: int,
     *     adjusted_inventory_minor: int
     * }
     */
    public static function eliminateIntercompanyInventoryProfit(
        int $intercompanySalesMinor,
        int $endingInventoryMinor,
        int $sellerGrossMarginBps
    ): array {
        if ($intercompanySalesMinor < 0 || $endingInventoryMinor < 0 || $sellerGrossMarginBps < 0) {
            throw new InvalidArgumentException('Parameter transaksi antar-perusahaan tidak boleh negatif.');
        }

        // Laba belum terealisasi = Nilai persediaan akhir * Persentase margin kotor penjual
        $unrealizedProfit = intdiv($endingInventoryMinor * $sellerGrossMarginBps, 10000);
        $adjustedInventory = $endingInventoryMinor - $unrealizedProfit;

        // Eliminasi penjualan mendebit Penjualan dan mengkredit Beban Pokok Penjualan (HPP)
        $cogsReduction = $intercompanySalesMinor - $unrealizedProfit;

        return [
            'sales_elimination_minor' => $intercompanySalesMinor,
            'cogs_reduction_minor' => $cogsReduction,
            'unrealized_inventory_profit_minor' => $unrealizedProfit,
            'adjusted_inventory_minor' => $adjustedInventory,
        ];
    }

    /**
     * Mengeliminasi Saldo Timbal Balik Piutang-Utang Antar-Perusahaan (Intercompany Balance Netting).
     *
     * @param  int  $receivableBalanceMinor  Saldo piutang entitas A kepada entitas B.
     * @param  int  $payableBalanceMinor  Saldo utang entitas B kepada entitas A.
     * @return array{
     *     eliminated_amount_minor: int,
     *     discrepancy_minor: int,
     *     is_matched: bool
     * }
     */
    public static function eliminateReciprocalBalances(
        int $receivableBalanceMinor,
        int $payableBalanceMinor
    ): array {
        $eliminated = min($receivableBalanceMinor, $payableBalanceMinor);
        $discrepancy = abs($receivableBalanceMinor - $payableBalanceMinor);

        return [
            'eliminated_amount_minor' => $eliminated,
            'discrepancy_minor' => $discrepancy,
            'is_matched' => ($discrepancy === 0),
        ];
    }

    /**
     * Membagi Laba Bersih Konsolidasi antara Pemilik Entitas Induk dan Kepentingan Non-Pengendali (NCI).
     *
     * @param  int  $subsidiaryNetIncomeMinor  Laba bersih anak perusahaan dalam minor IDR.
     * @param  int  $parentOwnershipBps  Persentase kepemilikan induk dalam basis points (misal: 8000 untuk 80.00%).
     * @return array{
     *     parent_share_minor: int,
     *     nci_share_minor: int,
     *     parent_ownership_bps: int,
     *     nci_ownership_bps: int
     * }
     */
    public static function partitionNonControllingInterest(
        int $subsidiaryNetIncomeMinor,
        int $parentOwnershipBps
    ): array {
        if ($parentOwnershipBps < 0 || $parentOwnershipBps > 10000) {
            throw new InvalidArgumentException('Persentase kepemilikan induk harus antara 0 s.d. 10.000 bps (0-100%).');
        }

        $nciOwnershipBps = 10000 - $parentOwnershipBps;
        $parentShare = intdiv($subsidiaryNetIncomeMinor * $parentOwnershipBps, 10000);
        $nciShare = $subsidiaryNetIncomeMinor - $parentShare;

        return [
            'parent_share_minor' => $parentShare,
            'nci_share_minor' => $nciShare,
            'parent_ownership_bps' => $parentOwnershipBps,
            'nci_ownership_bps' => $nciOwnershipBps,
        ];
    }

    /**
     * Mengkalkulasi Selisih Kurs Translasi Laporan Keuangan Luar Negeri (Cumulative Translation Adjustment - CTA).
     *
     * @param  int  $foreignCurrencyEquityMinor  Ekuitas anak perusahaan dalam valuta asing.
     * @param  int  $historicalRateBps  Kurs historis modal (IDR per FX unit dalam basis points, misal: 1550000 untuk 15.500 IDR/USD).
     * @param  int  $closingRateBps  Kurs penutupan pada tanggal neraca (IDR per FX unit dalam basis points).
     * @return array{
     *     equity_at_historical_minor: int,
     *     equity_at_closing_minor: int,
     *     cta_gain_loss_minor: int,
     *     is_translation_gain: bool
     * }
     */
    public static function calculateTranslationAdjustment(
        int $foreignCurrencyEquityMinor,
        int $historicalRateBps,
        int $closingRateBps
    ): array {
        if ($foreignCurrencyEquityMinor < 0 || $historicalRateBps <= 0 || $closingRateBps <= 0) {
            throw new InvalidArgumentException('Parameter translasi valas tidak valid.');
        }

        // Konversi ke IDR minor
        $historicalIdr = intdiv($foreignCurrencyEquityMinor * $historicalRateBps, 10000);
        $closingIdr = intdiv($foreignCurrencyEquityMinor * $closingRateBps, 10000);
        $ctaDiff = $closingIdr - $historicalIdr;

        return [
            'equity_at_historical_minor' => $historicalIdr,
            'equity_at_closing_minor' => $closingIdr,
            'cta_gain_loss_minor' => $ctaDiff,
            'is_translation_gain' => ($ctaDiff >= 0),
        ];
    }
}
