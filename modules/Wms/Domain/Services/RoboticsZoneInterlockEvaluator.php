<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use Modules\Wms\Domain\ValueObjects\IntervalWindow;

/**
 * Evaluator domain murni untuk aturan interlock keselamatan zona robotika (kapasitas k, blokir staf, batas kecepatan).
 */
final class RoboticsZoneInterlockEvaluator
{
    public const int MAX_COEXISTENCE_SPEED_MM_S = 800;

    public const int STANDARD_SPEED_MM_S = 1800;

    /**
     * Mengevaluasi apakah misi baru boleh memasuki zona pada jendela waktu tertentu.
     *
     * @param  list<array{mission_id:int, start_min:int, end_min:int, robot_id:int}>  $activeReservations
     * @return array{allowed:bool, reason_code:string, recommended_speed_mm_s:int, concurrent_count:int, message:string}
     */
    public function evaluateEntry(
        string $zoneCode,
        int $capacityK,
        bool $staffBlocked,
        ?int $blockStartMin,
        ?int $blockEndMin,
        IntervalWindow $requestedWindow,
        array $activeReservations,
        int $robotBatteryPct,
        int $minBatteryPct = 15,
    ): array {
        if ($robotBatteryPct < $minBatteryPct) {
            return [
                'allowed' => false,
                'reason_code' => 'LOW_BATTERY_INTERLOCK',
                'recommended_speed_mm_s' => 0,
                'concurrent_count' => count($activeReservations),
                'message' => "Baterai robot {$robotBatteryPct}% di bawah batas minimum {$minBatteryPct}%.",
            ];
        }

        if ($staffBlocked) {
            $blockOverlaps = true;
            if ($blockStartMin !== null && $blockEndMin !== null && $blockEndMin > $blockStartMin) {
                $blockWindow = new IntervalWindow($blockStartMin, $blockEndMin);
                $blockOverlaps = $requestedWindow->overlaps($blockWindow);
            }

            if ($blockOverlaps) {
                return [
                    'allowed' => false,
                    'reason_code' => 'STAFF_ZONE_BLOCKED',
                    'recommended_speed_mm_s' => 0,
                    'concurrent_count' => count($activeReservations),
                    'message' => "Zona {$zoneCode} sedang diblokir untuk aktivitas staf pada jendela [{$requestedWindow->start}, {$requestedWindow->end}).",
                ];
            }
        }

        $overlappingCount = 0;
        foreach ($activeReservations as $res) {
            $resWindow = new IntervalWindow((int) $res['start_min'], (int) $res['end_min']);
            if ($requestedWindow->overlaps($resWindow)) {
                $overlappingCount++;
            }
        }

        if ($overlappingCount >= max(1, $capacityK)) {
            return [
                'allowed' => false,
                'reason_code' => 'ZONE_CAPACITY_FULL',
                'recommended_speed_mm_s' => 0,
                'concurrent_count' => $overlappingCount,
                'message' => "Zona {$zoneCode} penuh ({$overlappingCount}/{$capacityK}) pada jendela [{$requestedWindow->start}, {$requestedWindow->end}).",
            ];
        }

        $recommendedSpeed = $overlappingCount > 0 ? self::MAX_COEXISTENCE_SPEED_MM_S : self::STANDARD_SPEED_MM_S;

        return [
            'allowed' => true,
            'reason_code' => 'CLEAR',
            'recommended_speed_mm_s' => $recommendedSpeed,
            'concurrent_count' => $overlappingCount,
            'message' => "Zona {$zoneCode} aman dimasuki (okupansi {$overlappingCount}/{$capacityK}).",
        ];
    }
}
