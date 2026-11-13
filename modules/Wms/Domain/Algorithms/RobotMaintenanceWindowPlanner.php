<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\ValueObjects\IntervalWindow;

/**
 * Penjadwal jendela pemeliharaan preventif armada robotika agar tidak mengurangi rasio kesiapan armada di bawah ambang.
 */
final class RobotMaintenanceWindowPlanner
{
    public const int MAX_CONCURRENT_MAINTENANCE_PCT = 15;

    /**
     * @param  list<array{robot_id:int, health_score:int, preferred_start_min:int, duration_min:int}>  $candidates
     * @return array{
     *     scheduled:list<array{robot_id:int, window_start_min:int, window_end_min:int}>,
     *     deferred_robot_ids:list<int>,
     *     max_concurrent_allowed:int
     * }
     */
    public function plan(array $candidates, int $totalFleetSize): array
    {
        if ($totalFleetSize < 1) {
            throw new InvalidArgumentException('totalFleetSize minimal 1.');
        }

        $maxConcurrent = max(1, intdiv($totalFleetSize * self::MAX_CONCURRENT_MAINTENANCE_PCT, 100));

        $sorted = $candidates;
        usort($sorted, static function (array $a, array $b): int {
            $cmpHealth = (int) $a['health_score'] <=> (int) $b['health_score'];
            if ($cmpHealth !== 0) {
                return $cmpHealth;
            }

            return (int) $a['robot_id'] <=> (int) $b['robot_id'];
        });

        /** @var list<IntervalWindow> $activeWindows */
        $activeWindows = [];
        $scheduled = [];
        $deferred = [];

        foreach ($sorted as $candidate) {
            $robotId = (int) $candidate['robot_id'];
            $start = max(0, (int) $candidate['preferred_start_min']);
            $duration = max(1, (int) $candidate['duration_min']);
            $window = new IntervalWindow($start, $start + $duration);

            $overlaps = 0;
            foreach ($activeWindows as $existing) {
                if ($window->overlaps($existing)) {
                    $overlaps++;
                }
            }

            if ($overlaps >= $maxConcurrent) {
                $deferred[] = $robotId;

                continue;
            }

            $activeWindows[] = $window;
            $scheduled[] = [
                'robot_id' => $robotId,
                'window_start_min' => $window->start,
                'window_end_min' => $window->end,
            ];
        }

        return [
            'scheduled' => $scheduled,
            'deferred_robot_ids' => $deferred,
            'max_concurrent_allowed' => $maxConcurrent,
        ];
    }
}
