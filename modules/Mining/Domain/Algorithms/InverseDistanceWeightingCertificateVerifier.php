<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use InvalidArgumentException;

final class InverseDistanceWeightingCertificateVerifier
{
    /**
     * @param  array<int, array{x: int, y: int, z: int, grade_ppm: int}>  $samples
     */
    public function verify(array $samples, InverseDistanceWeightingResult $result): bool
    {
        if (count($samples) === 0) {
            throw new InvalidArgumentException('Daftar sampel tidak boleh kosong');
        }

        // 1. Verifikasi batas convex hull: min <= interpolated <= max
        $grades = array_column($samples, 'grade_ppm');
        $minGrade = min($grades);
        $maxGrade = max($grades);

        if ($result->interpolatedGradePpm < $minGrade || $result->interpolatedGradePpm > $maxGrade) {
            throw new InvalidArgumentException(
                "Kadar hasil interpolasi ({$result->interpolatedGradePpm} ppm) berada di luar rentang sampel [{$minGrade}, {$maxGrade}]"
            );
        }

        // 2. Verifikasi total bobot adalah tepat 10000 bps (+- 1 bps toleransi pembulatan)
        $totalWeightBps = array_sum(array_column($result->weightsBreakdown, 'weight_bps'));
        if (abs($totalWeightBps - 10000) > 2) {
            throw new InvalidArgumentException("Total bobot interpolasi ({$totalWeightBps} bps) harus mendekati 10000 bps");
        }

        // 3. Verifikasi hash sertifikat deterministik
        $expectedHash = hash('sha256', json_encode([
            'target' => $result->targetPoint,
            'grade' => $result->interpolatedGradePpm,
            'weights' => $result->weightsBreakdown,
        ], JSON_THROW_ON_ERROR));

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new InvalidArgumentException('Sertifikat verifikasi IDW korup atau tidak cocok');
        }

        return true;
    }
}
