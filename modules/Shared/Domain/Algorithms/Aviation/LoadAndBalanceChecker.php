<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

/**
 * A3: Aviation\LoadAndBalanceChecker
 *
 * Perhitungan titik berat dan amplop muat pesawat:
 * - Berat total = Σ berat stasiun + bahan bakar.
 * - Momen = Σ berat × lengan.
 * - CG = ⌊momen / berat⌋.
 * - Lolos bila berat <= MTOW dan CG ∈ [depan, belakang].
 * - Alasan penolakan diurutkan alfabet: CG_AFT_LIMIT, CG_FWD_LIMIT, MTOW_EXCEEDED.
 */
final class LoadAndBalanceChecker
{
    /**
     * @param  list<array{name: string, weight_kg: int, arm_mm: int}>  $stations
     */
    public function check(
        int $mtowKg,
        int $cgForwardLimitMm,
        int $cgAftLimitMm,
        int $fuelWeightKg,
        int $fuelArmMm,
        array $stations
    ): LoadAndBalanceResult {
        $totalWeight = $fuelWeightKg;
        $totalMoment = $fuelWeightKg * $fuelArmMm;

        foreach ($stations as $st) {
            $totalWeight += $st['weight_kg'];
            $totalMoment += ($st['weight_kg'] * $st['arm_mm']);
        }

        $cgMm = ($totalWeight > 0) ? (int) floor($totalMoment / $totalWeight) : 0;

        $violations = [];
        if ($totalWeight > $mtowKg) {
            $violations[] = 'MTOW_EXCEEDED';
        }
        if ($cgMm < $cgForwardLimitMm) {
            $violations[] = 'CG_FWD_LIMIT';
        } elseif ($cgMm > $cgAftLimitMm) {
            $violations[] = 'CG_AFT_LIMIT';
        }

        sort($violations);
        $isWithinEnvelope = (count($violations) === 0);

        $certificate = new LoadAndBalanceCertificate(
            mtowKg: $mtowKg,
            totalWeightKg: $totalWeight,
            totalMoment: $totalMoment,
            cgMm: $cgMm,
            cgForwardLimitMm: $cgForwardLimitMm,
            cgAftLimitMm: $cgAftLimitMm,
            isWithinEnvelope: $isWithinEnvelope,
            violations: $violations
        );

        $result = new LoadAndBalanceResult(
            totalWeightKg: $totalWeight,
            totalMoment: $totalMoment,
            cgMm: $cgMm,
            isWithinEnvelope: $isWithinEnvelope,
            violations: $violations,
            certificate: $certificate
        );

        LoadAndBalanceCertificateVerifier::verify($result);

        return $result;
    }
}
