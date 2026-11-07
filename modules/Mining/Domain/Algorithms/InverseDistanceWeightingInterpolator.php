<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

use InvalidArgumentException;

/**
 * Algoritma 3D Inverse Distance Weighting (IDW) untuk Estimasi Kadar Blok Tambang (Voxel Interpolation).
 *
 * Rumus:
 * d_i = sqrt((x - x_i)^2 + (y - y_i)^2 + (z - z_i)^2)
 * w_i = (1 / d_i^power) / sum(1 / d_j^power)
 * Z = sum(w_i * z_i)
 */
final class InverseDistanceWeightingInterpolator
{
    private InverseDistanceWeightingCertificateVerifier $verifier;

    public function __construct(?InverseDistanceWeightingCertificateVerifier $verifier = null)
    {
        $this->verifier = $verifier ?? new InverseDistanceWeightingCertificateVerifier();
    }

    /**
     * @param array{x: int, y: int, z: int} $targetPoint Koordinat target dalam mm
     * @param array<int, array{x: int, y: int, z: int, grade_ppm: int}> $samples Titik sampel borehole
     * @param int $power Pangkat jarak (biasanya 2)
     * @return InverseDistanceWeightingResult
     */
    public function interpolate(array $targetPoint, array $samples, int $power = 2): InverseDistanceWeightingResult
    {
        if (count($samples) === 0) {
            throw new InvalidArgumentException('Interpolasi IDW memerlukan minimal 1 titik sampel borehole');
        }

        // Cek apakah target tepat berimpit dengan salah satu sampel (jarak 0 mm)
        foreach ($samples as $idx => $sample) {
            $dx = $targetPoint['x'] - $sample['x'];
            $dy = $targetPoint['y'] - $sample['y'];
            $dz = $targetPoint['z'] - $sample['z'];
            $distSquared = ($dx * $dx) + ($dy * $dy) + ($dz * $dz);

            if ($distSquared === 0) {
                $weights = [[
                    'sample_index' => $idx,
                    'distance_mm' => 0,
                    'weight_bps' => 10000,
                ]];
                $hash = hash('sha256', json_encode([
                    'target' => $targetPoint,
                    'grade' => $sample['grade_ppm'],
                    'weights' => $weights,
                ], JSON_THROW_ON_ERROR));

                $result = new InverseDistanceWeightingResult(
                    targetPoint: $targetPoint,
                    interpolatedGradePpm: $sample['grade_ppm'],
                    sampleCount: count($samples),
                    weightsBreakdown: $weights,
                    certificateHash: $hash,
                );
                $this->verifier->verify($samples, $result);
                return $result;
            }
        }

        // Hitung jarak dan bobot invers jarak berpangkat
        $rawInvDistances = [];
        $sumInvDist = 0.0;
        $distances = [];

        foreach ($samples as $idx => $sample) {
            $dx = ($targetPoint['x'] - $sample['x']) / 1000.0; // meter
            $dy = ($targetPoint['y'] - $sample['y']) / 1000.0;
            $dz = ($targetPoint['z'] - $sample['z']) / 1000.0;
            $distM = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
            $distMm = (int) round($distM * 1000);

            $inv = 1.0 / pow($distM, $power);
            $rawInvDistances[$idx] = $inv;
            $distances[$idx] = $distMm;
            $sumInvDist += $inv;
        }

        $weightsBreakdown = [];
        $accumulatedWeightBps = 0;
        $estimatedGradeFloat = 0.0;

        foreach ($samples as $idx => $sample) {
            $normalizedWeight = $rawInvDistances[$idx] / $sumInvDist;
            $weightBps = (int) round($normalizedWeight * 10000);
            $accumulatedWeightBps += $weightBps;

            $estimatedGradeFloat += $normalizedWeight * $sample['grade_ppm'];

            $weightsBreakdown[] = [
                'sample_index' => $idx,
                'distance_mm' => $distances[$idx],
                'weight_bps' => $weightBps,
            ];
        }

        // Koreksi pembulatan selisih bps ke sampel dengan bobot terbesar
        $diffBps = 10000 - $accumulatedWeightBps;
        if ($diffBps !== 0 && count($weightsBreakdown) > 0) {
            $maxWeightIdx = 0;
            $maxW = -1;
            foreach ($weightsBreakdown as $k => $item) {
                if ($item['weight_bps'] > $maxW) {
                    $maxW = $item['weight_bps'];
                    $maxWeightIdx = $k;
                }
            }
            $weightsBreakdown[$maxWeightIdx]['weight_bps'] += $diffBps;
        }

        $finalGradePpm = (int) round($estimatedGradeFloat);

        $certHash = hash('sha256', json_encode([
            'target' => $targetPoint,
            'grade' => $finalGradePpm,
            'weights' => $weightsBreakdown,
        ], JSON_THROW_ON_ERROR));

        $result = new InverseDistanceWeightingResult(
            targetPoint: $targetPoint,
            interpolatedGradePpm: $finalGradePpm,
            sampleCount: count($samples),
            weightsBreakdown: $weightsBreakdown,
            certificateHash: $certHash,
        );

        $this->verifier->verify($samples, $result);

        return $result;
    }
}

