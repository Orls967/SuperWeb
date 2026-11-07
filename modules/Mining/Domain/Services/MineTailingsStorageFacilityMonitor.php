<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Pemantau Keamanan Bendungan Tailing Tambang (Tailings Storage Facility - TSF) (D94-6).
 *
 * Mengikuti standar internasional GISTM (Global Industry Standard on Tailings Management):
 * 1. Tinggi Jagaan Minimum (Minimum Freeboard): Jarak puncak tanggul ke muka air kolam (minimal 1.5 meter).
 * 2. Tekanan Air Pori Tanggul (Piezometric Phreatic Line): Ketinggian garis freatik terhadap lereng hilir tanggul.
 * 3. Indeks Kerentanan Likuefaksi Seismik (Liquefaction Susceptibility Ratio): Ketahanan material pasir tailing jenuh air terhadap guncangan gempa.
 * 4. Kualitas Rembesan Kaki Tanggul (Toe Seepage Flow Rate): Laju debit rembesan air (L/menit).
 */
final class MineTailingsStorageFacilityMonitor
{
    /**
     * @param array{
     *     dam_code: string,
     *     crest_elevation_meters: float,
     *     pond_water_elevation_meters: float,
     *     phreatic_line_ratio: float,
     *     toe_seepage_flow_liters_per_minute: float,
     *     historic_baseline_seepage_lpm: float,
     *     cyclic_shear_stress_ratio: float,
     *     cyclic_resistance_ratio: float
     * } $data
     * @return array{
     *     dam_code: string,
     *     freeboard_meters: float,
     *     is_freeboard_compliant: bool,
     *     phreatic_stability_status: string,
     *     seepage_anomaly_detected: bool,
     *     seepage_variance_pct: float,
     *     liquefaction_factor_of_safety: float,
     *     is_liquefaction_safe: bool,
     *     overall_dam_safety_level: string,
     *     mitigation_directive: string
     * }
     */
    public function monitor(array $data): array
    {
        $damCode = trim((string) $data['dam_code']);
        $crestElev = (float) $data['crest_elevation_meters'];
        $waterElev = (float) $data['pond_water_elevation_meters'];
        $phreaticRatio = (float) $data['phreatic_line_ratio']; // 0.0 - 1.0
        $seepageLpm = (float) $data['toe_seepage_flow_liters_per_minute'];
        $baselineLpm = (float) $data['historic_baseline_seepage_lpm'];
        $csr = (float) $data['cyclic_shear_stress_ratio'];
        $crr = (float) $data['cyclic_resistance_ratio'];

        if ($crestElev <= 0 || $waterElev <= 0 || $csr <= 0 || $crr <= 0) {
            throw new InvalidArgumentException('Elevasi tanggul, muka air, dan rasio tegangan gempa harus bernilai positif.');
        }

        // 1. Tinggi Jagaan (Freeboard)
        $freeboard = round($crestElev - $waterElev, 2);
        $isFreeboardCompliant = ($freeboard >= 1.50);

        // 2. Evaluasi Garis Freatik Piezometer
        $phreaticStatus = 'OPTIMAL_INTERNAL_DRAINAGE';
        if ($phreaticRatio > 0.70) {
            $phreaticStatus = 'CRITICAL_HIGH_PHREATIC_LINE';
        } elseif ($phreaticRatio > 0.45) {
            $phreaticStatus = 'MODERATE_SATURATION';
        }

        // 3. Evaluasi Debit Rembesan
        $seepageVariance = 0.0;
        if ($baselineLpm > 0) {
            $seepageVariance = round(($seepageLpm - $baselineLpm) / $baselineLpm * 100, 2);
        }
        $seepageAnomaly = ($seepageVariance > 25.0); // Peningkatan rembesan > 25% indikasi piping

        // 4. Faktor Keamanan Likuefaksi (CRR / CSR)
        $fosLiquefaction = round($crr / $csr, 2);
        $isLiquefactionSafe = ($fosLiquefaction >= 1.20);

        // 5. Tingkat Keamanan Keseluruhan TSF
        $safetyLevel = 'GREEN_STABLE';
        $directive = 'Bendungan tailing berada dalam batas desain operasional normal aman.';

        if (! $isFreeboardCompliant || $seepageAnomaly || ! $isLiquefactionSafe) {
            $safetyLevel = 'RED_HIGH_ALERT';
            $directive = sprintf(
                'BAHAYA BENDUNGAN TAILING (Freeboard: %.2f m, Rembesan: +%.1f%%, Likuefaksi FoS: %.2f). Aktifkan pompa siphon darurat dan timbun tanggul penyangga hilir.',
                $freeboard,
                $seepageVariance,
                $fosLiquefaction
            );
        } elseif ($phreaticStatus === 'MODERATE_SATURATION') {
            $safetyLevel = 'YELLOW_ELEVATED';
            $directive = 'Tingkatkan frekuensi pembacaan piezometer kawat bergetar (vibrating wire) menjadi tiap jam.';
        }

        return [
            'dam_code' => $damCode,
            'freeboard_meters' => $freeboard,
            'is_freeboard_compliant' => $isFreeboardCompliant,
            'phreatic_stability_status' => $phreaticStatus,
            'seepage_anomaly_detected' => $seepageAnomaly,
            'seepage_variance_pct' => $seepageVariance,
            'liquefaction_factor_of_safety' => $fosLiquefaction,
            'is_liquefaction_safe' => $isLiquefactionSafe,
            'overall_dam_safety_level' => $safetyLevel,
            'mitigation_directive' => $directive,
        ];
    }
}
