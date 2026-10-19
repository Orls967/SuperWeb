<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 83.6 Audit Cross-border Clearing House: reconcile escrow crypto ledger balances,
 * unverified releases, dispute window integrity, and CBAM certificates (0 discrepancies).
 */
class AuditClearingCommand extends Command
{
    protected $signature = 'clearing:audit';

    protected $description = 'Audit Cross-Border Clearing: rekonsiliasi saldo escrow kripto, pelepasan POD, dan sertifikasi CBAM (0 selisih)';

    public function handle(): int
    {
        $rows = [];
        $issues = 0;

        // 1. Released escrows without verified POD hash
        $unverifiedReleases = DB::table('tf_crossborder_escrows')
            ->where('status', 'RELEASED')
            ->whereNull('pod_chain_hash')
            ->count();
        if ($unverifiedReleases > 0) {
            $rows[] = ['clearing.unverified_pod_release', '0', (string) $unverifiedReleases, (string) $unverifiedReleases, 'SELISIH'];
            $issues++;
        }

        // 2. Escrows in DISPUTED status with expired hold (> 24 hours) without resolution
        $unresolvedDisputes = DB::table('tf_crossborder_escrows')
            ->where('status', 'DISPUTED')
            ->whereNotNull('hold_until')
            ->where('hold_until', '<', now())
            ->count();
        if ($unresolvedDisputes > 0) {
            $rows[] = ['clearing.overdue_dispute_hold', '0', (string) $unresolvedDisputes, (string) $unresolvedDisputes, 'SELISIH'];
            $issues++;
        }

        // 3. Ledger balance reconciliation: active escrow liability vs sum of DEPOSITED and DISPUTED escrows
        if (Schema::hasTable('bank_ledger_accounts')) {
            $expectedActiveEscrowIdr = (int) DB::table('tf_crossborder_escrows')
                ->whereIn('status', ['DEPOSITED', 'DISPUTED'])
                ->selectRaw('COALESCE(SUM(ROUND((amount_cents / 100.0) * locked_fx_rate_idr)), 0) as total')
                ->value('total');

            $actualEscrowRow = DB::selectOne("SELECT cached_balance FROM bank_ledger_accounts WHERE code = 'tf:crossborder_escrow:IDR' LIMIT 1");
            $actualEscrowLedgerBalance = (int) ($actualEscrowRow?->cached_balance ?? 0);

            if ($expectedActiveEscrowIdr !== $actualEscrowLedgerBalance) {
                $diff = abs($expectedActiveEscrowIdr - $actualEscrowLedgerBalance);
                $rows[] = ['clearing.escrow_ledger_discrepancy', (string) $expectedActiveEscrowIdr, (string) $actualEscrowLedgerBalance, (string) $diff, 'SELISIH'];
                $issues++;
            }
        }

        // 4. Invalid CBAM certificates (zero or negative emissions)
        $invalidCbam = DB::table('tf_cbam_certificates')
            ->where('embedded_emissions_tco2', '<=', 0)
            ->count();
        if ($invalidCbam > 0) {
            $rows[] = ['clearing.invalid_cbam_emissions', '0', (string) $invalidCbam, (string) $invalidCbam, 'SELISIH'];
            $issues++;
        }

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan audit cross-border clearing.");

            return self::FAILURE;
        }

        $this->info('✓ clearing:audit selesai: escrow stablecoin, POD custody, dan kepatuhan CBAM seimbang.');

        return self::SUCCESS;
    }
}
