<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Mesin Eliminasi Transaksi & Saldo Resiprokal Antar-Entitas Grup (54.2 - PSAK 65).
 * Menghitung dan menerbitkan pasangan ayat jurnal eliminasi konsolidasi:
 * 1. Eliminasi piutang-utang antar-perusahaan (AR vs AP Intercompany).
 * 2. Eliminasi penjualan dan beban pokok antar-perusahaan (Sales vs COGS).
 * 3. Eliminasi laba belum terealisasi dalam persediaan akhir (Unrealized Profit in Ending Inventory).
 * 4. Eliminasi dividen intra-grup dan penyertaan modal entitas anak.
 */
class IntercompanyEliminationEngine
{
    /**
     * Menghasilkan jurnal eliminasi saldo utang-piutang resiprokal antar-entitas.
     *
     * @return array{
     *     elimination_type: string,
     *     debit_account: string,
     *     debit_amount_minor: int,
     *     credit_account: string,
     *     credit_amount_minor: int,
     *     variance_minor: int,
     *     is_balanced: bool,
     *     description: string
     * }
     */
    public function eliminateReciprocalBalance(
        string $creditorEntity,
        string $debtorEntity,
        int $arAmountMinor,
        int $apAmountMinor,
        string $currency = 'IDR'
    ): array {
        if ($arAmountMinor < 0 || $apAmountMinor < 0) {
            throw new InvalidArgumentException('Saldo eliminasi tidak boleh bernilai negatif.');
        }

        // Saldo yang dapat dieliminasi adalah nilai minimum yang berpasangan
        $eliminationAmount = min($arAmountMinor, $apAmountMinor);
        $variance = abs($arAmountMinor - $apAmountMinor);

        return [
            'elimination_type' => 'ELIM_AR_AP_RECIPROCAL',
            'debit_account' => '2105-AP-INTERCOMPANY', // Debit AP untuk menghapus kewajiban
            'debit_amount_minor' => $eliminationAmount,
            'credit_account' => '1205-AR-INTERCOMPANY', // Kredit AR untuk menghapus hak tagih
            'credit_amount_minor' => $eliminationAmount,
            'variance_minor' => $variance,
            'is_balanced' => ($variance === 0),
            'description' => "Eliminasi saldo resiprokal utang-piutang {$creditorEntity} <-> {$debtorEntity} ({$currency})",
        ];
    }

    /**
     * Menghasilkan jurnal eliminasi penjualan internal antar-perusahaan (Revenue vs COGS).
     *
     * @return array{
     *     elimination_type: string,
     *     debit_account: string,
     *     debit_amount_minor: int,
     *     credit_account: string,
     *     credit_amount_minor: int,
     *     description: string
     * }
     */
    public function eliminateInternalSales(
        string $sellerEntity,
        string $buyerEntity,
        int $salesAmountMinor,
        string $currency = 'IDR'
    ): array {
        if ($salesAmountMinor <= 0) {
            throw new InvalidArgumentException('Nominal penjualan antar-entitas harus lebih besar dari nol.');
        }

        return [
            'elimination_type' => 'ELIM_INTERNAL_SALES_COGS',
            'debit_account' => '4105-REVENUE-INTERCOMPANY', // Debit Pendapatan Penjualan Internal
            'debit_amount_minor' => $salesAmountMinor,
            'credit_account' => '5105-COGS-INTERCOMPANY', // Kredit Beban Pokok Penjualan Internal
            'credit_amount_minor' => $salesAmountMinor,
            'description' => "Eliminasi omzet penjualan internal {$sellerEntity} ke {$buyerEntity} ({$currency})",
        ];
    }

    /**
     * Menghasilkan eliminasi laba persediaan antar-perusahaan yang belum terealisasi ke pihak ketiga.
     * Formula: Laba Belum Terealisasi = Nilai Persediaan Sisa * (Markup BPS / (10000 + Markup BPS))
     *
     * @return array{
     *     elimination_type: string,
     *     unrealized_profit_minor: int,
     *     debit_account: string,
     *     credit_account: string,
     *     description: string
     * }
     */
    public function eliminateUnrealizedInventoryProfit(
        string $holdingEntity,
        string $subsidiaryEntity,
        int $remainingInventoryMinor,
        int $markupBps
    ): array {
        if ($remainingInventoryMinor <= 0 || $markupBps <= 0) {
            return [
                'elimination_type' => 'ELIM_UNREALIZED_INVENTORY_PROFIT',
                'unrealized_profit_minor' => 0,
                'debit_account' => '5101-COGS-EXTERNAL',
                'credit_account' => '1301-INVENTORY',
                'description' => 'Tidak terdapat laba persediaan belum terealisasi.',
            ];
        }

        // Hitung margin keuntungan yang terkandung dalam persediaan
        // Contoh markup 25% (2500 bps): Profit = Inv * (2500 / 12500) = Inv * 20%
        $unrealizedProfit = (int) intdiv($remainingInventoryMinor * $markupBps, 10000 + $markupBps);

        return [
            'elimination_type' => 'ELIM_UNREALIZED_INVENTORY_PROFIT',
            'unrealized_profit_minor' => $unrealizedProfit,
            'debit_account' => '5101-COGS-EXTERNAL', // Menambah beban pokok (mengurangi laba konsolidasian)
            'credit_account' => '1301-INVENTORY', // Mengurangi nilai buku persediaan ke harga perolehan historis
            'description' => "Eliminasi laba intra-group belum terealisasi atas persediaan {$subsidiaryEntity} (Markup {$markupBps} bps)",
        ];
    }

    /**
     * Menghasilkan eliminasi dividen internal yang dibagikan anak perusahaan ke holding.
     */
    public function eliminateIntercompanyDividend(
        string $subsidiaryEntity,
        int $dividendMinor
    ): array {
        if ($dividendMinor <= 0) {
            throw new InvalidArgumentException('Nominal dividen harus lebih besar dari nol.');
        }

        return [
            'elimination_type' => 'ELIM_INTRA_GROUP_DIVIDEND',
            'debit_account' => 'ef:holding_dividend_income:IDR', // Menghapus pendapatan dividen holding
            'debit_amount_minor' => $dividendMinor,
            'credit_account' => '3201-RETAINED-EARNINGS', // Mengembalikan laba ditahan anak perusahaan
            'credit_amount_minor' => $dividendMinor,
            'description' => "Eliminasi dividen internal dari {$subsidiaryEntity} ke Holding",
        ];
    }
}
