<?php

declare(strict_types=1);

namespace Modules\Marine\UI\Console\Commands;

use Illuminate\Console\Command;
use Modules\Marine\Application\Services\MarineAquacultureService;

class AuditMarineCommand extends Command
{
    protected $signature = 'mar:audit';

    protected $description = 'Rekonsiliasi subledger budidaya marikultur dan payout ledger';

    public function handle(MarineAquacultureService $service): int
    {
        $this->info('Menjalankan audit marikultur & perikanan laut...');
        $result = $service->auditMarine();

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Status', $result['status']],
                ['Total Payouts', (string) $result['total_payouts']],
                ['Total Net Amount', 'Rp'.number_format($result['total_net_amount'], 0, ',', '.')],
                ['Diskrepansi', (string) $result['discrepancy_count']],
            ]
        );

        if ($result['discrepancy_count'] > 0 || $result['status'] !== 'HEALTHY') {
            $this->error('Audit menemukan diskrepansi!');

            return 1;
        }

        $this->info('Audit bersih: subledger dan ledger rekonsil.');

        return 0;
    }
}
