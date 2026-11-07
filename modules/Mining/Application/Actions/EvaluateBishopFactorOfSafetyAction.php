<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;

/**
 * Domain Action: Kalkulasi Faktor Keamanan Lereng Metode Irisan Bishop Disederhanakan
 * (Simplified Bishop Method of Slices for Slope Stability Factor of Safety).
 * Melakukan iterasi konvergensi numerik non-linear untuk menentukan FoS kesetimbangan batas.
 */
class EvaluateBishopFactorOfSafetyAction
{
    /**
     * @param  array{
     *     cohesion_c_kpa: float,
     *     friction_angle_phi_deg: float,
     *     unit_weight_gamma_kn_m3: float,
     *     slope_height_meters: float,
     *     slope_angle_deg: float,
     *     ru_pore_pressure_ratio?: float,
     *     slices_count?: int
     * } $data
     * @return array{
     *     factor_of_safety_fos: float,
     *     is_stable_esdm: bool,
     *     failure_probability_est_pct: float,
     *     iterations: int,
     *     is_converged: bool,
     *     slices_summary: list<array{slice: int, weight_kn: float, alpha_deg: float, u_kpa: float}>
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $c = (float) $data['cohesion_c_kpa'];
        $phiDeg = (float) $data['friction_angle_phi_deg'];
        $phiRad = deg2rad($phiDeg);
        $tanPhi = tan($phiRad);
        $gamma = (float) $data['unit_weight_gamma_kn_m3'];
        $h = (float) $data['slope_height_meters'];
        $slopeAngleDeg = (float) $data['slope_angle_deg'];
        $ru = (float) ($data['ru_pore_pressure_ratio'] ?? 0.15); // Rasio tekanan pori ru = u / (gamma * z)
        $numSlices = (int) ($data['slices_count'] ?? 10);

        // Geometri busur gelincir silindris perkiraan:
        $radius = $h * 1.5;
        $arcLength = deg2rad(60.0) * $radius;
        $sliceWidthB = ($h / tan(deg2rad($slopeAngleDeg))) / $numSlices;

        $slices = [];
        $sumWsinAlpha = 0.0;

        for ($i = 0; $i < $numSlices; $i++) {
            // Sudut kemiringan dasar irisan alpha_i bervariasi dari -10 deg s/d +50 deg
            $alphaDeg = -10.0 + ($i + 0.5) * (60.0 / $numSlices);
            $alphaRad = deg2rad($alphaDeg);
            $sliceHeight = sin(deg2rad(min(60.0, ($i + 1) * (60.0 / $numSlices)))) * $h;
            $weightKn = max(5.0, round($gamma * $sliceWidthB * $sliceHeight, 2));
            $uKpa = round($ru * $gamma * $sliceHeight, 2);

            $sumWsinAlpha += $weightKn * sin($alphaRad);

            $slices[] = [
                'slice' => $i + 1,
                'weight_kn' => $weightKn,
                'alpha_deg' => round($alphaDeg, 1),
                'alpha_rad' => $alphaRad,
                'width_b' => $sliceWidthB,
                'u_kpa' => $uKpa,
            ];
        }

        if ($sumWsinAlpha <= 0) {
            $sumWsinAlpha = 1.0;
        }

        // Iterasi numerik Bishop:
        // FoS_(j+1) = \sum [ (c * b + (W - u * b) * tan(phi)) / m_alpha ] / \sum (W * sin(alpha))
        // m_alpha = cos(alpha) * (1 + (tan(alpha) * tan(phi)) / FoS_j)
        $fos = 1.50; // Tebakan awal
        $converged = false;
        $iteration = 0;
        $maxIter = 50;

        while ($iteration < $maxIter) {
            $iteration++;
            $sumNumerator = 0.0;

            foreach ($slices as $s) {
                $alphaRad = $s['alpha_rad'];
                $w = $s['weight_kn'];
                $u = $s['u_kpa'];
                $b = $s['width_b'];

                $mAlpha = cos($alphaRad) * (1.0 + (tan($alphaRad) * $tanPhi) / $fos);

                if (abs($mAlpha) < 0.0001) {
                    $mAlpha = 0.0001;
                }

                $numeratorSlice = ($c * $b + ($w - $u * $b) * $tanPhi) / $mAlpha;
                $sumNumerator += $numeratorSlice;
            }

            $newFos = $sumNumerator / $sumWsinAlpha;

            if (abs($newFos - $fos) < 0.001) {
                $fos = $newFos;
                $converged = true;
                break;
            }

            $fos = $newFos;
        }

        $finalFos = round(max(0.50, $fos), 3);

        // Regulasi ESDM Kepmen 1827/2018:
        // Lereng tunggal: FoS statis min 1.10
        // Lereng keseluruhan (overall slope): FoS statis min 1.30, dinamis min 1.10
        $isStable = $finalFos >= 1.30;

        // Estimasi probabilitas kelongsoran (PoF) empiris
        $pofPct = 0.0;
        if ($finalFos < 1.0) {
            $pofPct = round(min(99.0, (1.0 - $finalFos) * 100.0 + 50.0), 1);
        } elseif ($finalFos < 1.30) {
            $pofPct = round((1.30 - $finalFos) / 0.30 * 25.0, 1);
        } else {
            $pofPct = round(max(0.1, (2.0 - min(2.0, $finalFos)) * 1.5), 1);
        }

        return [
            'factor_of_safety_fos' => $finalFos,
            'is_stable_esdm' => $isStable,
            'failure_probability_est_pct' => $pofPct,
            'iterations' => $iteration,
            'is_converged' => $converged,
            'slices_summary' => array_map(fn ($s) => [
                'slice' => $s['slice'],
                'weight_kn' => $s['weight_kn'],
                'alpha_deg' => $s['alpha_deg'],
                'u_kpa' => $s['u_kpa'],
            ], $slices),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (($data['unit_weight_gamma_kn_m3'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Berat isi batuan (gamma) harus lebih besar dari 0 kN/m3.');
        }

        if (($data['slope_height_meters'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Tinggi lereng harus lebih besar dari 0 meter.');
        }

        if (($data['slope_angle_deg'] ?? 0) <= 0 || ($data['slope_angle_deg'] ?? 0) >= 90) {
            throw new InvalidArgumentException('Sudut lereng harus berada di antara 0 dan 90 derajat.');
        }
    }
}
