<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;

class AggregateTrialBalanceCommand extends Command
{
    protected $signature = 'ef:aggregate-trial-balance {--period= : Periode laporan konsolidasi (YYYY-MM)}';

    protected $description = 'Agregasi dan konsolidasi neraca saldo multi-entitas anak ke neraca grup (D8)';

    public function handle(EnterpriseFinanceService $service): int
    {
        $period = (string) ($this->option('period') ?: date('Y-m'));

        $this->info("Menjalankan siklus agregasi neraca saldo konsolidasi periode: {$period}...");

        $entitiesData = [
            [
                'entity_code' => 'HOLDING',
                'entity_name' => 'PT Nusantara Holding Corp',
                'currency' => 'IDR',
                'accounts' => [
                    ['account_code' => '1101-CASH', 'account_name' => 'Kas dan Setara Kas Holding', 'debit' => 800_000_000_000, 'credit' => 0],
                    ['account_code' => '3101-EQUITY', 'account_name' => 'Modal Saham Disetor', 'debit' => 0, 'credit' => 800_000_000_000],
                ],
            ],
            [
                'entity_code' => 'SUB_TECH',
                'entity_name' => 'PT Nusantara Solusi Cloud',
                'currency' => 'IDR',
                'accounts' => [
                    ['account_code' => '1201-AR', 'account_name' => 'Piutang Usaha Pelanggan', 'debit' => 450_000_000_000, 'credit' => 0],
                    ['account_code' => '2101-AP', 'account_name' => 'Utang Usaha Vendor', 'debit' => 0, 'credit' => 450_000_000_000],
                ],
            ],
        ];

        $run = $service->consolidateTrialBalance($period, $entitiesData);

        $this->line("Run Code: {$run->run_code}");
        $this->line("Total Entities: {$run->total_entities_count}");
        $this->line("Total Debit: IDR {$run->total_debit_minor}");
        $this->line("Total Credit: IDR {$run->total_credit_minor}");
        $this->line("Status: {$run->status} (Balanced: ".($run->is_balanced ? 'YES' : 'NO').')');
        $this->line("SHA-256: {$run->sha256_signature}");

        if (! $run->is_balanced) {
            $this->error('Konsolidasi neraca saldo TIDAK SEIMBANG!');

            return self::FAILURE;
        }

        $this->info('Konsolidasi neraca saldo grup berhasil diselesaikan dengan saldo zero-sum.');

        return self::SUCCESS;
    }
}
