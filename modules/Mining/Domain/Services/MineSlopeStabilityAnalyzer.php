<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Penganalisis Kestabilan Lereng Tambang & Faktor Keamanan (Factor of Safety - FoS) (D94-5).
 *
 * Mengimplementasikan kriteria kestabilan lereng Kepmen ESDM 1827/2018 Lampiran II:
 * - Lereng Tunggal (Single Slope): FoS Statis minimal 1.10; FoS Dinamis (gempa) minimal 1.00.
 * - Lereng Keseluruhan (Overall Slope): FoS Statis minimal 1.30; FoS Dinamis minimal 1.10.
 * - Laju Deformasi Radar SSR:
 *   Laju < 2 mm/jam -> Stabil
 *   Laju 2 - 10 mm/jam -> Deformasi Progresif (Siaga 2)
 *   Laju > 10 mm/jam -> Regresi Akselerasi Keruntuhan Lereng (Siaga 1 - Evakuasi Excavator/Dump Truck).
 */
final class MineSlopeStabilityAnalyzer
{
    /**
     * @param array{
     *     pit_bench_code: string,
     *     slope_type: string,
     *     slope_angle_degrees: float,
     *     slope_height_meters: float,
     *     cohesion_kpa: float,
     *     friction_angle_degrees: float,
     *     unit_weight_kn_m3: float,
     *     pore_pressure_ratio_ru?: float,
     *     seismic_coefficient_kh?: float,
     *     radar_displacement_rate_mm_per_hour?: float
     * } $data
     * @return array{
     *     bench_code: string,
     *     slope_type: string,
     *     factor_of_safety_static: float,
     *     factor_of_safety_dynamic: float,
     *     minimum_required_fos: float,
     *     radar_displacement_rate_mm_h: float,
     *     stability_verdict: string,
     *     alert_level: string,
     *     geotechnical_recommendation: string
     * }
     */
    public function analyze(array $data): array
    {
        $code = trim((string) $data['pit_bench_code']);
        $slopeType = strtoupper((string) ($data['slope_type'] ?? 'SINGLE_SLOPE'));
        $angleDeg = (float) $data['slope_angle_degrees'];
        $heightM = (float) $data['slope_height_meters'];
        $cohesion = (float) $data['cohesion_kpa'];
        $phiDeg = (float) $data['friction_angle_degrees'];
        $gamma = (float) $data['unit_weight_kn_m3'];
        $ru = (float) ($data['pore_pressure_ratio_ru'] ?? 0.15); // Rasio tekanan air pori
        $kh = (float) ($data['seismic_coefficient_kh'] ?? 0.05); // Koefisien percepatan gempa
        $radarRate = (float) ($data['radar_displacement_rate_mm_per_hour'] ?? 0.8);

        if ($angleDeg <= 0 || $angleDeg >= 90 || $heightM <= 0 || $gamma <= 0) {
            throw new InvalidArgumentException('Sudut lereng (1-89 derajat), tinggi lereng, dan berat isi batuan harus bernilai positif.');
        }

        $angleRad = deg2rad($angleDeg);
        $phiRad = deg2rad($phiDeg);

        // Model Pendekatan Taylor & Hoek-Bray Kesetimbangan Batas
        // Gaya penahan (Resisting Force): c' + (sigma - u) * tan(phi)
        // Gaya penggerak (Driving Force): W * sin(alpha)
        $drivingBase = sin($angleRad);
        $resistingFriction = (1.0 - $ru) * cos($angleRad) * tan($phiRad);
        $cohesionFactor = $cohesion / max(1.0, $gamma * $heightM * sin($angleRad) * cos($angleRad));

        $fosStatic = round(($cohesionFactor + $resistingFriction) / max(0.01, $drivingBase), 2);

        // Faktor Keamanan Dinamis (Pseudo-Statis dengan percepatan gempa kh)
        $dynamicDriving = $drivingBase + ($kh * cos($angleRad));
        $fosDynamic = round(($cohesionFactor + $resistingFriction) / max(0.01, $dynamicDriving), 2);

        $isOverall = ($slopeType === 'OVERALL_SLOPE');
        $minRequiredFos = $isOverall ? 1.30 : 1.10;

        $alertLevel = 'GREEN_STABLE';
        $verdict = 'LERENG_STABIL';
        $recommendation = 'Kondisi lereng tambang aman. Lanjutkan penambangan sesuai sekuen desain geoteknik.';

        if ($radarRate > 10.0 || $fosStatic < 1.0) {
            $alertLevel = 'RED_CRITICAL_EVACUATE';
            $verdict = 'BAHAYA_LONGSOR_IMINENT';
            $recommendation = "PERINGATAN TINGGI (FoS: {$fosStatic} < 1.0 atau laju radar {$radarRate} mm/jam > 10 mm/jam): Segera evakuasi alat muat dan hentikan seluruh aktivitas di toe lereng.";
        } elseif ($radarRate >= 2.0 || $fosStatic < $minRequiredFos) {
            $alertLevel = 'YELLOW_WATCH_RADAR';
            $verdict = 'LERENG_DEFORMASI_PROGRESIF';
            $recommendation = "WASPADA (FoS: {$fosStatic} di bawah standar {$minRequiredFos} atau laju radar {$radarRate} mm/jam): Buat counterweight berm atau kurangi sudut kemiringan lereng.";
        }

        return [
            'bench_code' => $code,
            'slope_type' => $slopeType,
            'factor_of_safety_static' => $fosStatic,
            'factor_of_safety_dynamic' => $fosDynamic,
            'minimum_required_fos' => $minRequiredFos,
            'radar_displacement_rate_mm_h' => $radarRate,
            'stability_verdict' => $verdict,
            'alert_level' => $alertLevel,
            'geotechnical_recommendation' => $recommendation,
        ];
    }
}
