<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Carbon\CarbonInterface;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Modules\Wms\Domain\Algorithms\TrafficDeadlockDetector;
use Modules\Wms\Domain\Algorithms\ZoneReservationScheduler;
use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\TrafficZone;

/**
 * Tick handler simulasi robotika untuk SimulationRunnerService mode sandbox P3 (D279-6, D95).
 *
 * Hanya membaca snapshot produksi dan menghitung metrik simulasi tanpa menulis tabel produksi.
 */
final readonly class RoboticsSimulationTickHandler implements SimTickHandlerInterface
{
    public function __construct(
        private ZoneReservationScheduler $zoneScheduler,
        private TrafficDeadlockDetector $deadlockDetector,
    ) {}

    public function name(): string
    {
        return 'wms_robotics_traffic_sim';
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        return $dayNumber >= 1;
    }

    /**
     * @param  (callable(string, array<string, mixed>): int)|null  $commandRunner
     * @return array<string, mixed>
     */
    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $robots = Robot::query()->orderBy('id')->get();
        $zones = TrafficZone::query()->orderBy('id')->get();
        $missions = RobotMission::query()->orderBy('id')->get();

        $onlineCount = $robots->where('is_online', true)->count();
        $zoneCount = $zones->count();
        $blockedZoneCount = $zones->where('staff_blocked', true)->count();

        // Simulasikan penjadwalan 1 slot tambahan per zona terbuka tanpa mengubah database
        $simulatedStarts = [];
        foreach ($zones as $zone) {
            if ((bool) $zone->staff_blocked) {
                continue;
            }
            $res = $this->zoneScheduler->schedule(
                capacity: max(1, (int) $zone->capacity_k),
                reservations: [],
                staffBlocks: [],
                duration: 5,
                earliestStart: ($dayNumber - 1) * 5,
            );
            $simulatedStarts[$zone->zone_code] = $res['start'];
        }

        // Cek potensi deadlock pada misi aktif secara in-memory
        $edges = [];
        $activeCodes = $missions->whereIn('status', ['assigned', 'moving', 'picking'])->pluck('mission_code')->values()->all();
        if (count($activeCodes) >= 2) {
            $edges[$activeCodes[0]] = $activeCodes[1];
        }
        $deadlock = $this->deadlockDetector->detectAndResolve($edges);

        return [
            'handler' => $this->name(),
            'virtual_time' => $virtualNow->toIso8601String(),
            'day' => $dayNumber,
            'online_robots' => $onlineCount,
            'total_zones' => $zoneCount,
            'blocked_zones' => $blockedZoneCount,
            'simulated_zone_slots' => count($simulatedStarts),
            'simulated_deadlocks' => $deadlock['has_deadlock'] ? 1 : 0,
            'sandbox_mode' => true,
        ];
    }
}
