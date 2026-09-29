<?php

declare(strict_types=1);

namespace Modules\Banking\Console\Commands;

use Brick\Math\BigDecimal;
use Illuminate\Console\Command;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerEntry;

class ReconcileBankLedgerCommand extends Command
{
    protected $signature = 'bank:reconcile';

    protected $description = 'Rekonsiliasi ledger: verifikasi cached_balance tiap akun dengan SUM(entries) dan SUM global per aset = 0';

    public function handle(): int
    {
        $this->info('Memulai rekonsiliasi double-entry ledger...');

        $discrepancies = [];

        // 1. Verifikasi tiap akun: cached_balance == SUM(entries.amount)
        $accounts = LedgerAccount::all();
        $this->info("Memeriksa {$accounts->count()} akun ledger...");

        foreach ($accounts as $account) {
            $cachedBal = BigDecimal::of($account->cached_balance ?: '0');

            // Sum all entries for this account
            $entries = LedgerEntry::where('account_id', $account->id)->pluck('amount');
            $calculatedSum = BigDecimal::zero();
            foreach ($entries as $amt) {
                $calculatedSum = $calculatedSum->plus(BigDecimal::of((string) $amt));
            }

            if (! $cachedBal->isEqualTo($calculatedSum)) {
                $discrepancies[] = [
                    'type' => 'Account Balance Discrepancy',
                    'detail' => "Akun: {$account->code} ({$account->asset_code}) | Cached: {$cachedBal} | Sum Entries: {$calculatedSum}",
                ];
            }
        }

        // 2. Verifikasi SUM global per aset = 0
        $assets = LedgerEntry::select('asset_code')->distinct()->pluck('asset_code');
        foreach ($assets as $asset) {
            $entries = LedgerEntry::where('asset_code', $asset)->pluck('amount');
            $globalSum = BigDecimal::zero();
            foreach ($entries as $amt) {
                $globalSum = $globalSum->plus(BigDecimal::of((string) $amt));
            }

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
}
