<?php

declare(strict_types=1);

namespace Modules\Agency\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 45.9 Audit: akrual komisi = ledger `agy:commission_payable` (0 selisih),
 * payout konsisten, atribusi tidak kadaluarsa aktif, agent tier status valid.
 */
class AuditAgencyCommand extends Command
{
    protected $signature = 'agy:audit';

    protected $description = 'Audit akrual komisi & payout agen terhadap ledger (0 selisih)';

    public function handle(): int
    {
        $rows = [];
        $issues = 0;

        // 1. Σ akrual (hold+payable, tanpa reversed/paid) = ledger payable.
        $accrued = (int) DB::table('agy_commission_accruals')
            ->whereIn('status', ['hold', 'payable'])
            ->sum('amount_idr');
        $ledger = DB::table('bank_ledger_accounts')
            ->where('code', 'agy:commission_payable:IDR')
            ->value('cached_balance');
        if ($ledger !== null && abs((int) $ledger) !== abs($accrued)) {
            $issues++;
            $rows[] = ['accrual.payable', number_format($accrued), number_format((int) $ledger), number_format(abs($accrued) - abs((int) $ledger)), 'SELISIH'];
        }

        // 2. Payout net = gross − withheld.
        $badPayout = DB::table('agy_payouts')->whereRaw('net_idr != gross_idr - withheld_tax_idr')->count();
        if ($badPayout > 0) {
            $issues++;
            $rows[] = ['payout.net', '0', (string) $badPayout, (string) $badPayout, 'SELISIH'];
        }

        // 3. Payout paid tanpa ledger = rusak.
        $paidNoLedger = DB::table('agy_payouts')
            ->where('status', 'paid')->whereNull('ledger_transaction_id')->count();
        if ($paidNoLedger > 0) {
            $issues++;
            $rows[] = ['payout.paid_no_ledger', '0', (string) $paidNoLedger, (string) $paidNoLedger, 'SELISIH'];
        }

        // 4. Atribusi aktif tapi expires lewat hari ini.
        $staleAttribution = DB::table('agy_attributions')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->toDateString())
            ->count();
        if ($staleAttribution > 0) {
            $issues++;
            $rows[] = ['attribution.stale', '0', (string) $staleAttribution, (string) $staleAttribution, 'SELISIH'];
        }

        // 5. Status agent valid.
        $badAgent = DB::table('agy_agents')->whereNotIn('status', ['onboarding', 'active', 'suspended', 'terminated'])->count();
        if ($badAgent > 0) {
            $issues++;
            $rows[] = ['agent.status', '0', (string) $badAgent, (string) $badAgent, 'SELISIH'];
        }

        $this->table(
            ['Sumber', 'Seharusnya', 'Aktual', 'Selisih', 'Status'],
            $rows === [] ? [['—', '0', '0', '0', 'OK']] : $rows
        );

        if ($issues > 0) {
            $this->error("Ditemukan {$issues} temuan agensi.");

            return self::FAILURE;
        }

        $this->info('✓ agy:audit selesai: akrual komisi, payout, atribusi konsisten.');

        return self::SUCCESS;
    }
}
