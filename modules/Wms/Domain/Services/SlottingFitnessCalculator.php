<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Services;

use InvalidArgumentException;

/**
 * Menghitung skor kesesuaian slotting ABC berdasarkan zona, jarak ke dock, dan frekuensi pick.
 */
final class SlottingFitnessCalculator
{
    /**
     * @param  list<array{sku: string, abc_class: string, zone_kind: string, distance_m: int, weekly_picks: int}>  $slots
     * @return array{
     *     average_fitness_bps: int,
     *     mismatch_count: int,
     *     details: list<array{sku: string, fitness_bps: int, is_mismatch: bool, recommendation: string}>
     * }
     */
    public function evaluate(array $slots): array
    {
        if ($slots === []) {
            return [
                'average_fitness_bps' => 10000,
                'mismatch_count' => 0,
                'details' => [],
            ];
        }

        $totalFitness = 0;
        $mismatches = 0;
        $details = [];

        foreach ($slots as $slot) {
            $class = strtoupper(trim($slot['abc_class']));
            if (! in_array($class, ['A', 'B', 'C'], true)) {
                throw new InvalidArgumentException("Kelas ABC tidak valid: {$slot['abc_class']}");
            }

            $distance = max(0, (int) $slot['distance_m']);
            $zoneKind = strtolower(trim($slot['zone_kind']));
            $fitness = 10000;
            $isMismatch = false;
            $recommendation = 'pertahankan_posisi';

            if ($class === 'A' && ($distance > 30 || $zoneKind === 'bulk')) {
                $fitness = max(2000, 10000 - (($distance - 30) * 100));
                $isMismatch = true;
                $recommendation = 'pindahkan_ke_pick_face_terdekat';
            } elseif ($class === 'B' && $distance > 80) {
                $fitness = max(4000, 10000 - (($distance - 80) * 80));
                $isMismatch = true;
                $recommendation = 'pindahkan_ke_zona_menengah';
            } elseif ($class === 'C' && $distance < 20 && $zoneKind === 'pick') {
                $fitness = 5500;
                $isMismatch = true;
                $recommendation = 'relokasi_ke_zona_storage_belakang';
            }

            if ($isMismatch) {
                $mismatches++;
            }

            $totalFitness += $fitness;
            $details[] = [
                'sku' => (string) $slot['sku'],
                'fitness_bps' => $fitness,
                'is_mismatch' => $isMismatch,
                'recommendation' => $recommendation,
            ];
        }

        return [
            'average_fitness_bps' => intdiv($totalFitness, count($slots)),
            'mismatch_count' => $mismatches,
            'details' => $details,
        ];
    }
}
