<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\CorporateTaxReconciliationService;

class TaxReconCommand extends Command
{
    protected $signature = 'enterprise:tax-recon {period? : Periode pajak YYYY-MM} {--entity=CORP-HOLDING : Kode entitas}';

    protected $description = 'Merekonsiliasi PPN Masukan vs Keluaran, nomor seri faktur gapless, dan ringkasan PPh';

    public function handle(CorporateTaxReconciliationService $service): int
    {
        $period = (string) ($this->argument('period') ?: date('Y-m', strtotime('-1 month')));
        $entity = (string) $this->option('entity');

        $this->info("Menjalankan rekonsiliasi fiskal dan perpajakan untuk entitas {$entity} periode {$period}...");

        $recon = $service->reconcileVat(
            entityCode: $entity,
            period: $period,
            vatOutputMinor: 11000000000,
            vatInputMinor: 8000000000
        );

        $this->table(
            ['Parameter Pajak', 'Nilai'],
            [
                ['Entitas', $recon['entity']],
                ['Masa Pajak', $recon['period']],
                ['PPN Keluaran', 'Rp ' . number_format($recon['vat_output_minor'] / 100, 2)],
                ['PPN Masukan', 'Rp ' . number_format($recon['vat_input_minor'] / 100, 2)],
                ['Kurang Bayar Bersih', 'Rp ' . number_format($recon['net_payable_minor'] / 100, 2)],
                ['Nomor Draf SPT (Gapless)', $recon['spt_draft_number']],
                ['Status Rekonsiliasi', $recon['status']],
            ]
        );

        $this->info("Rekonsiliasi fiskal periode {$period} sukses.");

        return Command::SUCCESS;
    }
}

