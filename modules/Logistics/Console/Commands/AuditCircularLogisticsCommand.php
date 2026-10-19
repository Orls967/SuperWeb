<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Services\Reverse\ReverseLogisticsService;

class AuditCircularLogisticsCommand extends Command
{
    protected $signature = 'lgx:audit-circular';

    protected $description = 'Perform two-source verification of reverse logistics orders, chain of custody, and circular economy revenue ledger';

    public function handle(ReverseLogisticsService $service): int
    {
        $this->info('Memulai audit dua-sumber reverse logistics dan circular economy...');

        $result = $service->auditCircular();

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Reverse Orders Checked', $result['orders_checked']],
                ['Circular Items Checked', $result['items_checked']],
                ['Discrepancies Count', count($result['discrepancies'])],
                ['Status', $result['passed'] ? 'PASS' : 'FAIL'],
            ]
        );

        if (! $result['passed']) {
            $this->error('Ditemukan ketidaksesuaian integritas reverse logistics:');
            foreach ($result['discrepancies'] as $discrepancy) {
                $this->error("  ✗ {$discrepancy}");
            }

            return self::FAILURE;
        }

        $this->info("✓ Seluruh {$result['orders_checked']} order reverse logistics dan {$result['items_checked']} material sirkular valid dan sinkron dengan buku besar (0 selisih).");

        return self::SUCCESS;
    }
}
