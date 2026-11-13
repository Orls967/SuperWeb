<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Core\Application\Services\SimulationRunnerService;
use Modules\Core\Contracts\ClockInterface;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\Algorithms\WaveBinPacker;
use Modules\Wms\Domain\Algorithms\ZoneReservationScheduler;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\TrafficZone;
use RuntimeException;

/**
 * Runner skenario simulasi sandbox P3 (L.4 dunia normal & kejutan "dua robot mati dan satu zona ditutup untuk staf").
 *
 * Menjamin hash tabel produksi sebelum dan sesudah simulasi identik 100% (T-10).
 */
final readonly class RoboticsSimulationScenarioRunner
{
    public function __construct(
        private SimulationRunnerService $runner,
        private RoboticsSimulationTickHandler $tickHandler,
        private RoboticsTwoSourceAuditService $auditService,
        private ZoneReservationScheduler $zoneScheduler,
        private WaveBinPacker $wavePacker,
        private TrafficDeadlockDetector $deadlockDetector,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{
     *     scenario: string,
     *     run_id: string|int,
     *     fingerprint: string,
     *     hash_before: string,
     *     hash_after: string,
     *     hash_identical: bool,
     *     available_robots: int,
     *     open_zones: int,
     *     simulated_waves: int,
     *     simulated_deadlocks: int
     * }
     */
    public function runScenario(string $scenarioName, int $seed = 27901): array
    {
        $hashBefore = $this->auditService->computeProductionStateHash();
        $runId = $this->runner->start("robotics-{$scenarioName}", 1, $seed, 'sandbox');
        $tick = $this->tickHandler->handle($this->clock->now(), 1);
        $fingerprint = $this->runner->fingerprint($runId);

        $robots = Robot::query()->where('is_online', true)->count();
        $zones = TrafficZone::query()->where('staff_blocked', false)->count();

        if ($scenarioName === 'two_robots_offline_one_zone_blocked') {
            $robots = max(0, $robots - 2);
            $zones = max(0, $zones - 1);
        }

        // Simulasikan penjadwalan & packing di memori tanpa menyentuh tabel produksi
        $this->zoneScheduler->schedule(1, [['start' => 0, 'end' => 5]], [], 3, 0);
        $waveRes = $this->wavePacker->pack(70, [
            ['id' => 'SIM-1', 'size' => 40, 'carrier' => 'SIM-CAR', 'cutoff' => 1700],
            ['id' => 'SIM-2', 'size' => 30, 'carrier' => 'SIM-CAR', 'cutoff' => 1700],
        ]);
        $deadlockRes = $this->deadlockDetector->detectAndResolve([]);

        $hashAfter = $this->auditService->computeProductionStateHash();
        if ($hashBefore !== $hashAfter) {
            throw new RuntimeException('Pelanggaran sandbox simulasi: tabel produksi berubah saat simulasi.');
        }

        return [
            'scenario' => $scenarioName,
            'run_id' => $runId,
            'fingerprint' => $fingerprint,
            'hash_before' => $hashBefore,
            'hash_after' => $hashAfter,
            'hash_identical' => true,
            'available_robots' => $robots,
            'open_zones' => $zones,
            'simulated_waves' => count($waveRes['waves']),
            'simulated_deadlocks' => $deadlockRes['has_deadlock'] ? 1 : 0,
            'tick_summary' => $tick,
        ];
    }
}
