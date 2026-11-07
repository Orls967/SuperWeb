<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Geo;

use InvalidArgumentException;

final class OrdinaryKriging3DCertificateVerifier
{
    /**
     * @param  array<int, array{x: int, y: int, z: int, grade_ppm: int}>  $samples
     */
    public function verify(array $samples, OrdinaryKriging3DResult $result): bool
    {
        if (count($samples) === 0) {
            throw new InvalidArgumentException('Daftar sampel Kriging tidak boleh kosong');
        }

        // 1. Kondisi Kriging Tidak Bias (Unbiasedness constraint): sum(lambda_i) == 1 (10000 bps +- 2)
        $totalWeightBps = array_sum(array_column($result->weightsBreakdown, 'weight_bps'));
        if (abs($totalWeightBps - 10000) > 2) {
            throw new InvalidArgumentException("Total bobot Kriging ({$totalWeightBps} bps) harus tepat 10000 bps (unbiased condition)");
        }

        // 2. Varians estimasi Kriging harus non-negatif
        if ($result->krigingVarianceBps < 0) {
            throw new InvalidArgumentException("Varians Kriging ({$result->krigingVarianceBps}) tidak boleh negatif");
        }

        // 3. Verifikasi hash sertifikat
        $expectedHash = hash('sha256', json_encode([
            'target' => $result->targetPoint,
            'grade' => $result->estimatedGradePpm,
            'variance' => $result->krigingVarianceBps,
            'weights' => $result->weightsBreakdown,
            'lagrange' => $result->lagrangeMultiplierBps,
        ], JSON_THROW_ON_ERROR));

        if (! hash_equals($expectedHash, $result->certificateHash)) {
            throw new InvalidArgumentException('Sertifikat verifikasi Ordinary Kriging korup');
        }

        return true;
    }
}
