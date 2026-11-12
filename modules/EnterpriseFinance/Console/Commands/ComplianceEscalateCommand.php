<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\ComplianceEscalationService;

class ComplianceEscalateCommand extends Command
{
    protected $signature = 'enterprise:compliance-escalate {--as-of= : Tanggal rujukan YYYY-MM-DD}';

    protected $description = 'Memindai kewajiban regulasi dan memicu eskalasi berjenjang (H-30, H-14, H-7, H-1, Overdue)';

    public function handle(ComplianceEscalationService $service): int
    {
        $asOf = $this->option('as-of') ? (string) $this->option('as-of') : null;
        $this->info('Memulai pemindaian kalender kepatuhan dan eskalasi tenggat waktu...');

        $result = $service->scanAndEscalate($asOf);

        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Kewajiban Dipindai', $result['total_scanned']],
                ['Total Eskalasi Diterbitkan', $result['escalations_sent']],
                ['Tier H-30', $result['tiers']['H-30']],
                ['Tier H-14', $result['tiers']['H-14']],
                ['Tier H-7', $result['tiers']['H-7']],
                ['Tier H-1', $result['tiers']['H-1']],
                ['Tier Overdue', $result['tiers']['OVERDUE']],
            ]
        );

        $this->info('Eskalasi kepatuhan selesai diproses.');

        return Command::SUCCESS;
    }
}
