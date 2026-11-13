<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\SimulationRunnerService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Application\Services\RoboticsSimulationTickHandler;
use Modules\Wms\Application\Services\RoboticsTwoSourceAuditService;
use RuntimeException;

/**
 * Command simulasi kepadatan lalu lintas & throughput robotika di atas SimulationRunnerService mode sandbox P3 (D279-6, T-10).
 */
final class RoboticsSimulateCommand extends Command
{
    protected $signature = 'robotics:simulate {scenario=traffic_density : Nama skenario simulasi} {--days=3 : Durasi hari simulasi} {--seed=27901 : Seed deterministik}';

    protected $description = 'Jalankan simulasi robotika gudang dalam mode sandbox P3 tanpa mengubah data produksi';

    public function handle(
        SimulationRunnerService $runner,
        RoboticsSimulationTickHandler $handler,
        RoboticsTwoSourceAuditService $audit,
        ClockInterface $clock,
    ): int {
        $scenario = (string) $this->argument('scenario');
        $days = max(1, (int) $this->option('days'));
        $seed = (int) $this->option('seed');

        $hashBefore = $audit->computeProductionStateHash();
        $runId = $runner->start("robotics-{$scenario}", $days, $seed, 'sandbox');
        $tickResult = $handler->handle($clock->now(), 1);
        $fingerprint = $runner->fingerprint($runId);
        $hashAfter = $audit->computeProductionStateHash();

        if ($hashBefore !== $hashAfter) {
            throw new RuntimeException('Pelanggaran sandbox simulasi: hash tabel produksi berubah.');
        }

        $this->info(sprintf(
            'robotics:simulate [%s] run=%s fingerprint=%s online_robots=%d simulated_slots=%d hash_identik=true',
            $scenario,
            (string) $runId,
            substr($fingerprint, 0, 12),
            (int) $tickResult['online_robots'],
            (int) $tickResult['simulated_zone_slots'],
        ));

        return self::SUCCESS;
    }
}
