<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Insurance\Application\Services\LifeHealthAuditor;
use Throwable;

final class AuditLifeHealthCommand extends Command
{
    protected $signature = 'ins:life-health-audit';

    protected $description = 'Menjalankan audit dua-sumber untuk asuransi jiwa, kesehatan & portofolio unit link (Fase 159)';

    public function handle(LifeHealthAuditor $auditor): int
    {
        $this->info('Memulai audit dua-sumber asuransi jiwa, kesehatan & unit link...');

        try {
            $report = $auditor->audit();

            $this->info('Audit Life & Health BERSIH (exit 0). Seluruh invarian aktuaria & klaim terpenuhi.');
            $this->table(['Metrik', 'Jumlah'], [
                ['Polis Jiwa', $report['total_life_policies']],
                ['Klaim Kesehatan', $report['total_health_claims']],
                ['Dana Unit Link', $report['total_funds']],
                ['Mismatch Alokasi Beneficiary', $report['beneficiary_allocation_mismatches']],
                ['Mismatch Incurred vs Approved', $report['claim_amount_mismatches']],
                ['Mismatch Unit Link AUM', $report['unit_link_aum_mismatches']],
            ]);

            return 0;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}

