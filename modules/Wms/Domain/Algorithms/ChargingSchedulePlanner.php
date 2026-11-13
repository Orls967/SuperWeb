<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Algoritma penjadwalan pengisian daya armada robot dengan syarat minimum armada tersedia (X279-1).
 *
 * Kompleksitas: O(R log R + R * S) dengan R = jumlah robot dan S = jumlah slot jendela pengisian.
 * Aritmetika: 100% bilangan bulat (baterai %, menit, jumlah robot).
 */
final class ChargingSchedulePlanner
{
    /**
     * @param  list<array{robot_id: int, robot_code: string, battery_pct: int, is_online: bool}>  $robots
     * @param  int  $lowBatteryThresholdPct  Ambang baterai yang memicu kebutuhan pengisian
     * @param  int  $minFleetAvailable  Minimum robot online yang harus tetap siaga di tiap slot
     * @param  int  $chargeWindowStartMin  Awal horizon penjadwalan (menit)
     * @param  int  $slotDurationMin  Durasi 1 sesi pengisian (menit)
     * @param  int  $horizonSlots  Jumlah slot jendela berurutan
     * @return array{
     *     schedules: list<array{
     *         robot_id: int,
     *         robot_code: string,
     *         initial_battery_pct: int,
     *         target_battery_pct: int,
     *         window_start_min: int,
     *         window_end_min: int
     *     }>,
     *     deferred_robot_ids: list<int>,
     *     max_concurrent_charging: int
     * }
     */
    public function plan(
        array $robots,
        int $lowBatteryThresholdPct = 35,
        int $minFleetAvailable = 2,
        int $chargeWindowStartMin = 0,
        int $slotDurationMin = 30,
        int $horizonSlots = 4,
    ): array {
        if ($slotDurationMin <= 0 || $horizonSlots <= 0 || $minFleetAvailable < 0) {
            throw new InvalidArgumentException('Parameter jadwal pengisian daya tidak valid.');
        }

        $onlineRobots = array_values(array_filter($robots, static fn (array $r): bool => (bool) $r['is_online']));
        $totalOnline = count($onlineRobots);
        $maxConcurrent = max(0, $totalOnline - $minFleetAvailable);

        // Urutkan robot butuh isi daya: baterai terendah dulu, tie-break robot_code menaik
        $candidates = array_values(array_filter(
            $onlineRobots,
            static fn (array $r): bool => (int) $r['battery_pct'] <= $lowBatteryThresholdPct,
        ));
        usort($candidates, static function (array $a, array $b): int {
            if ($a['battery_pct'] !== $b['battery_pct']) {
                return $a['battery_pct'] <=> $b['battery_pct'];
            }

            return $a['robot_code'] <=> $b['robot_code'];
        });

        $slotCounts = array_fill(0, $horizonSlots, 0);
        $schedules = [];
        $deferred = [];

        foreach ($candidates as $robot) {
            $assignedSlot = null;
            for ($s = 0; $s < $horizonSlots; $s++) {
                if ($slotCounts[$s] < $maxConcurrent) {
                    $assignedSlot = $s;
                    $slotCounts[$s]++;
                    break;
                }
            }

            if ($assignedSlot === null) {
                $deferred[] = (int) $robot['robot_id'];

                continue;
            }

            $start = $chargeWindowStartMin + ($assignedSlot * $slotDurationMin);
            $schedules[] = [
                'robot_id' => (int) $robot['robot_id'],
                'robot_code' => (string) $robot['robot_code'],
                'initial_battery_pct' => (int) $robot['battery_pct'],
                'target_battery_pct' => 95,
                'window_start_min' => $start,
                'window_end_min' => $start + $slotDurationMin,
            ];
        }

        return [
            'schedules' => $schedules,
            'deferred_robot_ids' => $deferred,
            'max_concurrent_charging' => $maxConcurrent,
        ];
    }
}
