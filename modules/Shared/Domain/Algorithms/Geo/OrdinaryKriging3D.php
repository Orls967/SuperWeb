<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Geo;

use InvalidArgumentException;

/**
 * Algoritma Geostatistik 3D Ordinary Kriging untuk Estimasi Blok Model Tambang.
 *
 * Model Variogram: Spherical Variogram
 * gamma(h) = c0 + c * (1.5 * (h / a) - 0.5 * (h / a)^3) jika h <= a, else c0 + c
 *
 * Sistem Persamaan Kriging:
 * [ Gamma_ij   1 ] [ lambda_j ]   [ Gamma_i0 ]
 * [   1^T      0 ] [    mu    ] = [    1     ]
 */
final class OrdinaryKriging3D
{
    private OrdinaryKriging3DCertificateVerifier $verifier;

    public function __construct(?OrdinaryKriging3DCertificateVerifier $verifier = null)
    {
        $this->verifier = $verifier ?? new OrdinaryKriging3DCertificateVerifier();
    }

    /**
     * @param array{x: int, y: int, z: int} $targetPoint Target estimasi (mm)
     * @param array<int, array{x: int, y: int, z: int, grade_ppm: int}> $samples Titik sampel borehole
     * @param float $nuggetC0 Efek nugget (nugget effect)
     * @param float $sillC Varians struktur (sill - nugget)
     * @param float $rangeA Jarak pengaruh (range) dalam meter
     * @return OrdinaryKriging3DResult
     */
    public function estimate(
        array $targetPoint,
        array $samples,
        float $nuggetC0 = 0.05,
        float $sillC = 0.95,
        float $rangeA = 150.0,
    ): OrdinaryKriging3DResult {
        $n = count($samples);
        if ($n === 0) {
            throw new InvalidArgumentException('Ordinary Kriging membutuhkan minimal 1 sampel');
        }

        if ($n === 1) {
            $weights = [['sample_index' => 0, 'weight_bps' => 10000]];
            $hash = hash('sha256', json_encode([
                'target' => $targetPoint,
                'grade' => $samples[0]['grade_ppm'],
                'variance' => 0,
                'weights' => $weights,
                'lagrange' => 0,
            ], JSON_THROW_ON_ERROR));

            $res = new OrdinaryKriging3DResult(
                targetPoint: $targetPoint,
                estimatedGradePpm: $samples[0]['grade_ppm'],
                krigingVarianceBps: 0,
                weightsBreakdown: $weights,
                lagrangeMultiplierBps: 0,
                certificateHash: $hash,
            );
            $this->verifier->verify($samples, $res);
            return $res;
        }

        // Matriks sistem Kriging berukuran (n+1) x (n+1)
        $mSize = $n + 1;
        $matrix = array_fill(0, $mSize, array_fill(0, $mSize, 0.0));
        $rhs = array_fill(0, $mSize, 0.0);

        // Isi matriks Gamma_ij
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 0.0;
                } else {
                    $dist = $this->distanceM($samples[$i], $samples[$j]);
                    $matrix[$i][$j] = $this->sphericalGamma($dist, $nuggetC0, $sillC, $rangeA);
                }
            }
            // Batasan lagrange sum(lambda_i) = 1
            $matrix[$i][$n] = 1.0;
            $matrix[$n][$i] = 1.0;

            // Vektor sisi kanan Gamma_i0 (jarak sampel ke target)
            $distTarget = $this->distanceM($samples[$i], $targetPoint);
            $rhs[$i] = $this->sphericalGamma($distTarget, $nuggetC0, $sillC, $rangeA);
        }
        $matrix[$n][$n] = 0.0;
        $rhs[$n] = 1.0;

        // Selesaikan sistem linier A * x = rhs dengan Gaussian elimination dengan partial pivoting
        $solution = $this->solveLinearSystem($matrix, $rhs);

        // Ambil bobot lambda_i dan lagrange multiplier mu
        $rawWeights = array_slice($solution, 0, $n);
        $mu = $solution[$n];

        // Hitung estimasi kadar dan varians Kriging
        $estimatedGradeFloat = 0.0;
        $krigingVarFloat = $mu;
        $weightsBreakdown = [];
        $accumBps = 0;

        for ($i = 0; $i < $n; $i++) {
            $w = $rawWeights[$i];
            $wBps = (int) round($w * 10000);
            $accumBps += $wBps;

            $estimatedGradeFloat += $w * $samples[$i]['grade_ppm'];
            $krigingVarFloat += $w * $rhs[$i];

            $weightsBreakdown[] = [
                'sample_index' => $i,
                'weight_bps' => $wBps,
            ];
        }

        // Koreksi pembulatan bps
        $diffBps = 10000 - $accumBps;
        if ($diffBps !== 0) {
            $weightsBreakdown[0]['weight_bps'] += $diffBps;
        }

        $finalGradePpm = (int) round($estimatedGradeFloat);
        $krigingVarBps = max(0, (int) round($krigingVarFloat * 10000));
        $lagrangeBps = (int) round($mu * 10000);

        $certHash = hash('sha256', json_encode([
            'target' => $targetPoint,
            'grade' => $finalGradePpm,
            'variance' => $krigingVarBps,
            'weights' => $weightsBreakdown,
            'lagrange' => $lagrangeBps,
        ], JSON_THROW_ON_ERROR));

        $result = new OrdinaryKriging3DResult(
            targetPoint: $targetPoint,
            estimatedGradePpm: $finalGradePpm,
            krigingVarianceBps: $krigingVarBps,
            weightsBreakdown: $weightsBreakdown,
            lagrangeMultiplierBps: $lagrangeBps,
            certificateHash: $certHash,
        );

        $this->verifier->verify($samples, $result);

        return $result;
    }

    private function distanceM(array $p1, array $p2): float
    {
        $dx = ($p1['x'] - $p2['x']) / 1000.0;
        $dy = ($p1['y'] - $p2['y']) / 1000.0;
        $dz = ($p1['z'] - $p2['z']) / 1000.0;
        return sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
    }

    private function sphericalGamma(float $h, float $c0, float $c, float $a): float
    {
        if ($h <= 0.0) {
            return 0.0;
        }
        if ($h >= $a) {
            return $c0 + $c;
        }
        $ratio = $h / $a;
        return $c0 + $c * (1.5 * $ratio - 0.5 * pow($ratio, 3));
    }

    /**
     * @param array<int, array<int, float>> $a
     * @param array<int, float> $b
     * @return array<int, float>
     */
    private function solveLinearSystem(array $a, array $b): array
    {
        $n = count($b);

        for ($p = 0; $p < $n; $p++) {
            // Cari baris pivot terbesar
            $maxRow = $p;
            for ($i = $p + 1; $i < $n; $i++) {
                if (abs($a[$i][$p]) > abs($a[$maxRow][$p])) {
                    $maxRow = $i;
                }
            }

            // Tukar baris
            $tempA = $a[$p];
            $a[$p] = $a[$maxRow];
            $a[$maxRow] = $tempA;

            $tempB = $b[$p];
            $b[$p] = $b[$maxRow];
            $b[$maxRow] = $tempB;

            if (abs($a[$p][$p]) < 1e-12) {
                $a[$p][$p] = 1e-12; // Regularisasi numerik
            }

            // Eliminasi maju
            for ($i = $p + 1; $i < $n; $i++) {
                $alpha = $a[$i][$p] / $a[$p][$p];
                $b[$i] -= $alpha * $b[$p];
                for ($j = $p; $j < $n; $j++) {
                    $a[$i][$j] -= $alpha * $a[$p][$j];
                }
            }
        }

        // Substitusi mundur
        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = 0.0;
            for ($j = $i + 1; $j < $n; $j++) {
                $sum += $a[$i][$j] * $x[$j];
            }
            $x[$i] = ($b[$i] - $sum) / $a[$i][$i];
        }

        return $x;
    }
}

