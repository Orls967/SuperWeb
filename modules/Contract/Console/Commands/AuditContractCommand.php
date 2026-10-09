<?php

declare(strict_types=1);

namespace Modules\Contract\Console\Commands;

use Illuminate\Console\Command;
use Modules\Contract\Application\Services\ContractFinanceService;
use Modules\Contract\Application\Services\ContractReportService;
use Modules\Contract\Application\Services\ContractUsageService;
use Modules\Contract\Application\Services\ContractUsageSyncService;

/**
 * Rekonsiliasi plafon, jadwal bayar, dan akun kontrak (29.8).
 * Pajak & arbitrase adalah simulasi; command hanya memverifikasi subledger
 * pemakaian dan konsistensi jadwal pembayaran.
 */
class AuditContractCommand extends Command
{
    protected $signature = 'ctr:audit {--sync : Sinkronkan sumber transaksi riil sebelum audit}';

    protected $description = 'Audit pemakaian plafon dan jadwal finansial kontrak (harus 0 selisih)';

    public function handle(
        ContractReportService $reports,
        ContractUsageService $usage,
        ContractUsageSyncService $sync,
        ContractFinanceService $finance,
    ): int {
        $this->info('Memulai audit subledger kontrak...');

        if ((bool) $this->option('sync')) {
            $result = $sync->syncAll();
            $this->line('Disinkronkan: '.json_encode($result));
        }

        $finance->ensureAccounts();
        $audit = $reports->audit();
        $rows = [];

        foreach ($audit['discrepancies'] as $d) {
            $rows[] = [$d['contract_id'], $d['kind'], $d['ledger'], $d['cached'], $d['difference'], 'SELISIH'];
        }

        $this->table(
            ['Kontrak', 'Jenis', 'Subledger', 'Catatan/Cache', 'Selisih', 'Status'],
            $rows === [] ? [['—', 'Semua kontrak', '0', '0', '0', 'OK']] : $rows
        );

        $usageRows = [];
        foreach ($usage->contractsOverThreshold(80) as $contract) {
            $util = $usage->utilization($contract);
            $usageRows[] = [
                $contract->contract_number,
                $contract->status->value,
                number_format($util['used_idr']),
                number_format($util['total_idr']),
                $util['percent'].'%',
                strtoupper($util['status']),
            ];
        }

        if ($usageRows !== []) {
            $this->warn('Early warning kontrak (>=80% plafon):');
            $this->table(['Kontrak', 'Status', 'Terpakai', 'Plafon', 'Utilisasi', 'Flag'], $usageRows);
        }

        if (! $audit['balanced']) {
            $this->error('DITEMUKAN SELISIH PADA SUBLEDGER KONTRAK.');

            return self::FAILURE;
        }

        $this->info("✓ ctr:audit selesai: {$audit['checked']} kontrak diperiksa, 0 selisih.");

        return self::SUCCESS;
    }
}
