<?php

declare(strict_types=1);

namespace Modules\Banking\Console\Commands;

use Brick\Math\BigDecimal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Models\LedgerAccount;

class ReconcileBankLedgerCommand extends Command
{
    protected $signature = 'bank:reconcile';

    protected $description = 'Rekonsiliasi ledger: verifikasi cached_balance tiap akun dengan SUM(entries) dan SUM global per aset = 0';

    public function handle(): int
    {
        $this->info('Memulai rekonsiliasi double-entry ledger...');

        $discrepancies = [];

        $accountCount = LedgerAccount::count();
        $this->info("Memeriksa {$accountCount} akun ledger...");

        // 1. Verifikasi tiap akun: cached_balance == SUM(entries.amount)
        //
        // Satu query agregat (GROUP BY account_id) menggantikan satu query per
        // akun. GROUP_CONCAT dipilih di atas SUM() karena kolom bertipe
        // decimal(36,18) tidak menjamin presisi penuh lewat agregat numerik
        // SQLite; penjumlahan tetap dilakukan di PHP dengan BigDecimal sehingga
        // presisi uang tidak berubah sedikit pun.
        $sumsByAccount = $this->sumsByAccount();

        foreach (LedgerAccount::query()->get() as $account) {
            $cachedBal = BigDecimal::of($account->cached_balance ?: '0');
            $calculatedSum = $sumsByAccount[$account->id] ?? BigDecimal::zero();

            if (! $cachedBal->isEqualTo($calculatedSum)) {
                $discrepancies[] = [
                    'type' => 'Account Balance Discrepancy',
                    'detail' => "Akun: {$account->code} ({$account->asset_code}) | Cached: {$cachedBal} | Sum Entries: {$calculatedSum}",
                ];
            }
        }

        // 2. Verifikasi SUM global per aset = 0
        foreach ($this->globalSumsByAsset() as $asset => $globalSum) {
            if (! $globalSum->isZero()) {
                $discrepancies[] = [
                    'type' => 'Global Asset Sum Non-Zero',
                    'detail' => "Aset: {$asset} | Total Global: {$globalSum} (harus 0)",
                ];
            }
        }

        if (! empty($discrepancies)) {
            $this->error('DITEMUKAN SELISIH PADA LEDGER:');
            $this->table(['Tipe Masalah', 'Detail'], $discrepancies);

            return self::FAILURE;
        }

        $this->info('✓ Rekonsiliasi selesai: Semua akun seimbang dan total global per aset = 0.');

        return self::SUCCESS;
    }

    /**
     * Jumlah entri per akun, dihitung penuh dengan BigDecimal dari string mentah.
     *
     * @return array<int, BigDecimal>
     */
    private function sumsByAccount(): array
    {
        $sums = [];

        DB::table('bank_ledger_entries')
            ->selectRaw("account_id, group_concat(amount, '§') as amounts")
            ->groupBy('account_id')
            ->orderBy('account_id')
            ->get()
            ->each(function (object $row) use (&$sums): void {
                $sums[(int) $row->account_id] = $this->sumAmounts((string) $row->amounts);
            });

        return $sums;
    }

    /**
     * Jumlah global per aset, dihitung penuh dengan BigDecimal dari string mentah.
     *
     * @return array<string, BigDecimal>
     */
    private function globalSumsByAsset(): array
    {
        $sums = [];

        DB::table('bank_ledger_entries')
            ->selectRaw("asset_code, group_concat(amount, '§') as amounts")
            ->groupBy('asset_code')
            ->orderBy('asset_code')
            ->get()
            ->each(function (object $row) use (&$sums): void {
                $sums[(string) $row->asset_code] = $this->sumAmounts((string) $row->amounts);
            });

        return $sums;
    }

    /**
     * @param  string  $amounts  String terpisah pemisah "§" berisi nilai entri.
     */
    private function sumAmounts(string $amounts): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach (explode('§', $amounts) as $amount) {
            if ($amount !== '') {
                $sum = $sum->plus(BigDecimal::of($amount));
            }
        }

        return $sum;
    }
}
