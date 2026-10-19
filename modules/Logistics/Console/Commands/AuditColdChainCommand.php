<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Services\Autonomous\AutonomousLogisticsService;

class AuditColdChainCommand extends Command
{
    protected $signature = 'lgx:audit-coldchain';

    protected $description = 'Perform two-source verification of cold-chain temperature breach holds, drone missions, and dispute escrow ledger';

    public function handle(AutonomousLogisticsService $service): int
    {
        $this->info('Memulai audit dua-sumber cold-chain blockchain dan drone robotics...');

        $result = $service->auditColdChain();

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Breach Holds Checked', $result['holds_checked']],
                ['Drone Missions Checked', $result['missions_checked']],
                ['Drone Units Checked', $result['drones_checked']],
                ['Discrepancies Count', count($result['discrepancies'])],
                ['Status', $result['passed'] ? 'PASS' : 'FAIL'],
            ]
        );

        if (! $result['passed']) {
            $this->error('Ditemukan ketidaksesuaian integritas cold-chain:');
            foreach ($result['discrepancies'] as $discrepancy) {
                $this->error("  ✗ {$discrepancy}");
            }

            return self::FAILURE;
        }

        $this->info("✓ Seluruh {$result['holds_checked']} breach holds dan {$result['missions_checked']} drone missions valid dan sinkron dengan buku besar (0 selisih).");

        return self::SUCCESS;
    }
}
