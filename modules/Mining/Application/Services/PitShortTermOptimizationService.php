<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningPlan;

/**
 * Layanan Optimasi Penjadwalan Penggalian Bench Tambang Jangka Pendek (Grade Blending Constraint).
 * Menyeimbangkan rasio penggalian bijih kadar tinggi (High-Grade Saprolite > 1.8% Ni)
 * dan bijih kadar rendah (Low-Grade Limonite < 1.4% Ni) untuk mencapai spesifikasi feed pabrik (1.65% Ni).
 */
final class PitShortTermOptimizationService
{
    /**
     * @param string $pitId
     * @param int $targetBlendTonnageKg
     * @param int $targetGradePpm Target kadar campuran (misal 16500 ppm = 1.65%)
     * @param array<int, array{bench_id: string, bench_rl_m: int, available_tonnage_kg: int, grade_ppm: int, ore_type: string}> $availableBenches
     * @return array{
     *     status: string,
     *     target_grade_ppm: int,
     *     achieved_grade_ppm: int,
     *     total_blend_tonnage_kg: int,
     *     bench_allocations: list<array{
     *         bench_id: string,
     *         bench_rl_m: int,
     *         ore_type: string,
     *         allocated_tonnage_kg: int,
     *         portion_bps: int
     *     }>,
     *     stripping_ratio_estimated: float
     * }
     */
    public function optimizeBenchCut(
        string $pitId,
        int $targetBlendTonnageKg,
        int $targetGradePpm,
        array $availableBenches,
    ): array {
        if (count($availableBenches) === 0) {
            throw new InvalidArgumentException('Daftar bench aktif untuk blending tidak boleh kosong.');
        }

        // Pisahkan menjadi bench High Grade (HG) dan Low Grade (LG)
        $hgBenches = [];
        $lgBenches = [];

        foreach ($availableBenches as $b) {
            if ($b['grade_ppm'] >= $targetGradePpm) {
                $hgBenches[] = $b;
            } else {
                $lgBenches[] = $b;
            }
        }

        // Jika salah satu kosong, alokasikan proporsional dari bench yang tersedia
        $allocations = [];
        $allocatedTonnage = 0;
        $weightedGradeSum = 0;

        if (count($hgBenches) > 0 && count($lgBenches) > 0) {
            // Blending linier dua komponen sederhana
            $avgHg = array_sum(array_column($hgBenches, 'grade_ppm')) / count($hgBenches);
            $avgLg = array_sum(array_column($lgBenches, 'grade_ppm')) / count($lgBenches);

            // Rasio f_hg * avgHg + (1 - f_hg) * avgLg = targetGrade
            $fHg = ($avgHg !== $avgLg) ? max(0.1, min(0.9, ($targetGradePpm - $avgLg) / ($avgHg - $avgLg))) : 0.5;
            $targetHgKg = (int) round($targetBlendTonnageKg * $fHg);
            $targetLgKg = $targetBlendTonnageKg - $targetHgKg;

            // Bagi rata ke masing-masing bench HG
            $perHg = (int) round($targetHgKg / count($hgBenches));
            foreach ($hgBenches as $b) {
                $qty = min($b['available_tonnage_kg'], $perHg);
                $allocations[] = [
                    'bench_id' => $b['bench_id'],
                    'bench_rl_m' => $b['bench_rl_m'],
                    'ore_type' => $b['ore_type'],
                    'allocated_tonnage_kg' => $qty,
                    'portion_bps' => (int) round(($qty / max(1, $targetBlendTonnageKg)) * 10000),
                ];
                $allocatedTonnage += $qty;
                $weightedGradeSum += ($qty * $b['grade_ppm']);
            }

            // Bagi rata ke masing-masing bench LG
            $perLg = (int) round($targetLgKg / count($lgBenches));
            foreach ($lgBenches as $b) {
                $qty = min($b['available_tonnage_kg'], $perLg);
                $allocations[] = [
                    'bench_id' => $b['bench_id'],
                    'bench_rl_m' => $b['bench_rl_m'],
                    'ore_type' => $b['ore_type'],
                    'allocated_tonnage_kg' => $qty,
                    'portion_bps' => (int) round(($qty / max(1, $targetBlendTonnageKg)) * 10000),
                ];
                $allocatedTonnage += $qty;
                $weightedGradeSum += ($qty * $b['grade_ppm']);
            }
        } else {
            // Fallback: alokasikan proporsional kapasitas
            $totalAvail = array_sum(array_column($availableBenches, 'available_tonnage_kg'));
            foreach ($availableBenches as $b) {
                $qty = (int) round(($b['available_tonnage_kg'] / max(1, $totalAvail)) * $targetBlendTonnageKg);
                $allocations[] = [
                    'bench_id' => $b['bench_id'],
                    'bench_rl_m' => $b['bench_rl_m'],
                    'ore_type' => $b['ore_type'],
                    'allocated_tonnage_kg' => $qty,
                    'portion_bps' => (int) round(($qty / max(1, $targetBlendTonnageKg)) * 10000),
                ];
                $allocatedTonnage += $qty;
                $weightedGradeSum += ($qty * $b['grade_ppm']);
            }
        }

        $achievedGrade = $allocatedTonnage > 0 ? (int) round($weightedGradeSum / $allocatedTonnage) : $targetGradePpm;

        return [
            'status' => abs($achievedGrade - $targetGradePpm) <= 500 ? 'OPTIMAL' : 'GRADE_DEVIATION',
            'target_grade_ppm' => $targetGradePpm,
            'achieved_grade_ppm' => $achievedGrade,
            'total_blend_tonnage_kg' => $allocatedTonnage,
            'bench_allocations' => $allocations,
            'stripping_ratio_estimated' => 3.2,
        ];
    }
}
