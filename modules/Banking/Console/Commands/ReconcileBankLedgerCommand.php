<?php

declare(strict_types=1);

namespace Modules\Banking\Console\Commands;

use Brick\Math\BigDecimal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Models\LedgerAccount;

class ReconcileBankLedgerCommand extends Command
{
    protected $signature = 'bank:reconcile {--incremental : Jalankan rekonsiliasi inkremental berbasis checkpoint}';

    protected $description = 'Rekonsiliasi ledger: verifikasi cached_balance tiap akun dengan SUM(entries) dan SUM global per aset = 0';

    public function handle(): int
    {
        $isIncremental = (bool) $this->option('incremental');
        $this->info($isIncremental
            ? 'Memulai rekonsiliasi double-entry ledger inkremental...'
            : 'Memulai rekonsiliasi double-entry ledger penuh...');

        $discrepancies = [];

        $accountCount = LedgerAccount::count();
        $this->info("Memeriksa {$accountCount} akun ledger...");

        $checkpoint = null;
        $afterEntryId = 0;
        $sumsByAccount = [];
        $globalSumsByAsset = [];
        $latestEntryId = 0;

        $hashCtx = hash_init('sha256');

        if ($isIncremental) {
            $checkpoint = DB::table('bank_reconcile_checkpoints')->latest('id')->first();
            if ($checkpoint) {
                $afterEntryId = (int) $checkpoint->last_reconciled_entry_id;
                $latestEntryId = $afterEntryId;
                $this->info("Melanjutkan dari checkpoint #{$checkpoint->id} (Entry ID terakhir: {$afterEntryId})...");

                $savedAccounts = json_decode((string) $checkpoint->account_balances, true) ?: [];
                foreach ($savedAccounts as $accId => $balanceStr) {
                    $sumsByAccount[(int) $accId] = BigDecimal::of((string) $balanceStr);
                }

                $savedAssets = json_decode((string) $checkpoint->asset_balances, true) ?: [];
                foreach ($savedAssets as $asset => $balanceStr) {
                    $globalSumsByAsset[(string) $asset] = BigDecimal::of((string) $balanceStr);
                }

                hash_update($hashCtx, (string) $checkpoint->transaction_checksum);
            }
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' && ! $isIncremental) {
            // Native SUM di MySQL pada rekonsiliasi penuh
            $sumsByAccount = $this->mysqlSumsByAccount();
            $globalSumsByAsset = $this->mysqlGlobalSumsByAsset();
            $latestEntry = DB::table('bank_ledger_entries')->latest('id')->first();
            $latestEntryId = $latestEntry ? (int) $latestEntry->id : 0;
        } else {
            // Iterasi chunkById + BigDecimal untuk SQLite atau mode inkremental
            $query = DB::table('bank_ledger_entries');
            if ($afterEntryId > 0) {
                $query->where('id', '>', $afterEntryId);
            }

            $query->orderBy('id')->chunkById(1000, function ($entries) use (&$sumsByAccount, &$globalSumsByAsset, &$latestEntryId, $hashCtx): void {
                foreach ($entries as $entry) {
                    $entryId = (int) $entry->id;
                    if ($entryId > $latestEntryId) {
                        $latestEntryId = $entryId;
                    }

                    $accId = (int) $entry->account_id;
                    $asset = (string) $entry->asset_code;
                    $amountBd = BigDecimal::of((string) $entry->amount);

                    $sumsByAccount[$accId] = ($sumsByAccount[$accId] ?? BigDecimal::zero())->plus($amountBd);
                    $globalSumsByAsset[$asset] = ($globalSumsByAsset[$asset] ?? BigDecimal::zero())->plus($amountBd);

                    hash_update($hashCtx, "{$entryId}:{$entry->amount}:{$accId}:{$asset};");
                }
            });
        }

        $checksum = hash_final($hashCtx);

        // 1. Verifikasi tiap akun: cached_balance == SUM(entries.amount)
        foreach (LedgerAccount::query()->get() as $account) {
            $cachedBal = BigDecimal::of($account->cached_balance ?: '0');
            $calculatedSum = $sumsByAccount[$account->id] ?? BigDecimal::zero();

            if (! $cachedBal->isEqualTo($calculatedSum)) {
                $discrepancies[] = [
                    'type' => 'Account Balance Discrepancy',
                    'detail' => "Akun: {$account->code} ({$account->asset_code}) | Cached: {$cachedBal} | Sum Entries: {$calculatedSum}",
                ];
                $this->error("Ketidakcocokan saldo pada akun: {$account->code} ({$account->name})");
            }
        }

        // 2. Verifikasi SUM global per aset = 0
        foreach ($globalSumsByAsset as $asset => $globalSum) {
            if (! $globalSum->isZero()) {
                $discrepancies[] = [
                    'type' => 'Global Asset Sum Non-Zero',
                    'detail' => "Aset: {$asset} | Total Global: {$globalSum} (harus 0)",
                ];
                $this->error("Total global aset {$asset} tidak seimbang: {$globalSum}");
            }
        }

        if (! empty($discrepancies)) {
            $this->error('DITEMUKAN SELISIH PADA LEDGER:');
            $this->table(['Tipe Masalah', 'Detail'], $discrepancies);

            return self::FAILURE;
        }

        if ($isIncremental) {
            $accountPayload = [];
            foreach ($sumsByAccount as $accId => $bd) {
                $accountPayload[$accId] = (string) $bd;
            }

            $assetPayload = [];
            foreach ($globalSumsByAsset as $asset => $bd) {
                $assetPayload[$asset] = (string) $bd;
            }

            DB::table('bank_reconcile_checkpoints')->insert([
                'last_reconciled_entry_id' => $latestEntryId,
                'account_balances' => json_encode($accountPayload),
                'asset_balances' => json_encode($assetPayload),
                'transaction_checksum' => $checksum,
                'reconciled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("✓ Checkpoint rekonsiliasi tersimpan (Entry ID: {$latestEntryId}).");
        }

        $this->info('✓ Rekonsiliasi selesai: Semua akun seimbang dan total global per aset = 0.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, BigDecimal>
     */
    private function mysqlSumsByAccount(): array
    {
        $sums = [];

        DB::table('bank_ledger_entries')
            ->selectRaw('account_id, CAST(SUM(amount) AS CHAR) as total')
            ->groupBy('account_id')
            ->orderBy('account_id')
            ->get()
            ->each(function (object $row) use (&$sums): void {
                $sums[(int) $row->account_id] = BigDecimal::of((string) $row->total);
            });

        return $sums;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function mysqlGlobalSumsByAsset(): array
    {
        $sums = [];

        DB::table('bank_ledger_entries')
            ->selectRaw('asset_code, CAST(SUM(amount) AS CHAR) as total')
            ->groupBy('asset_code')
            ->orderBy('asset_code')
            ->get()
            ->each(function (object $row) use (&$sums): void {
                $sums[(string) $row->asset_code] = BigDecimal::of((string) $row->total);
            });

        return $sums;
    }
}
