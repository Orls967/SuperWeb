<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Detektor Anomali & Kecurangan Penimbangan Jembatan Timbang Tambang (D94-1).
 *
 * Menganalisis pola anomali tonase truk tambang:
 * 1. Anomali Bobot Kosong (Tare Weight Drift): Deviasi tare > 3% dari rata-rata historis (indikasi residu material menempel atau manipulasi tare).
 * 2. Kelebihan Muatan Kritis (Critical Overload): Gross weight melebihi Gross Vehicle Weight Rating (GVWR) pabrikan (ancaman keselamatan rem & jalan tambang).
 * 3. Muatan Di Bawah Standar (Underload Inefficiency): Net payload < 70% kapasitas armada angkut.
 * 4. Anomali Siklus Waktu (Haul Cycle Anomaly): Waktu tempuh dari pit ke jembatan timbang terlalu singkat (indikasi pemotongan rute ilegal).
 */
final class WeighbridgeAnomalyDetector
{
    /**
     * @param array{
     *     truck_plate: string,
     *     truck_type: string,
     *     rated_payload_ton: float,
     *     historical_avg_tare_kg: int,
     *     current_tare_kg: int,
     *     current_gross_kg: int,
     *     travel_time_minutes?: int,
     *     expected_min_travel_minutes?: int
     * } $data
     * @return array{
     *     truck_plate: string,
     *     current_net_kg: int,
     *     current_net_ton: float,
     *     tare_drift_pct: float,
     *     is_tare_anomalous: bool,
     *     is_overloaded: bool,
     *     overload_margin_pct: float,
     *     is_underloaded: bool,
     *     is_speeding_or_route_breached: bool,
     *     overall_risk_score: int,
     *     fraud_warning_level: string,
     *     recommended_action: string
     * }
     */
    public function analyze(array $data): array
    {
        $plate = trim((string) $data['truck_plate']);
        $ratedPayloadTon = (float) $data['rated_payload_ton'];
        $histAvgTareKg = (int) $data['historical_avg_tare_kg'];
        $currentTareKg = (int) $data['current_tare_kg'];
        $currentGrossKg = (int) $data['current_gross_kg'];
        $travelMinutes = (int) ($data['travel_time_minutes'] ?? 25);
        $minTravelMinutes = (int) ($data['expected_min_travel_minutes'] ?? 15);

        if ($currentGrossKg <= $currentTareKg || $ratedPayloadTon <= 0) {
            throw new InvalidArgumentException('Bobot kotor harus lebih besar dari tara, dan kapasitas nominal harus bernilai positif.');
        }

        $currentNetKg = $currentGrossKg - $currentTareKg;
        $currentNetTon = round($currentNetKg / 1000, 2);

        // 1. Analisis Deviasi Tare Historis
        $tareDriftPct = 0.0;
        if ($histAvgTareKg > 0) {
            $tareDriftPct = round(abs($currentTareKg - $histAvgTareKg) / $histAvgTareKg * 100, 2);
        }
        $isTareAnomalous = ($tareDriftPct > 3.0); // Toleransi drift tara > 3%

        // 2. Analisis Overload terhadap Kapasitas Angkut
        $ratedPayloadKg = (int) ($ratedPayloadTon * 1000);
        $overloadMarginPct = 0.0;
        $isOverloaded = false;
        if ($currentNetKg > $ratedPayloadKg) {
            $overloadMarginPct = round(($currentNetKg - $ratedPayloadKg) / $ratedPayloadKg * 100, 2);
            $isOverloaded = ($overloadMarginPct > 5.0); // Kelebihan muatan > 5% ditandai overload
        }

        // 3. Analisis Underload
        $isUnderloaded = ($currentNetKg < ($ratedPayloadKg * 0.70));

        // 4. Analisis Kecepatan & Potong Rute Ilegal
        $isSpeedingOrRouteBreached = ($travelMinutes < $minTravelMinutes);

        // 5. Skor Risiko Kecurangan (0 - 100)
        $riskScore = 0;
        if ($isTareAnomalous) {
            $riskScore += ($tareDriftPct > 7.0) ? 40 : 20;
        }
        if ($isOverloaded) {
            $riskScore += ($overloadMarginPct > 15.0) ? 35 : 20;
        }
        if ($isSpeedingOrRouteBreached) {
            $riskScore += 25;
        }
        if ($isUnderloaded) {
            $riskScore += 10;
        }

        $riskScore = min(100, $riskScore);

        $warningLevel = 'LOW';
        $recommendedAction = 'Tiket penimbangan normal. Lanjutkan pengosongan muatan ke stockpile tujuan.';

        if ($riskScore >= 70) {
            $warningLevel = 'CRITICAL';
            $recommendedAction = 'TAHAN KENDARAAN: Terdeteksi multi-anomali kritis (potensi manipulasi tara atau jalan pintas ilegal). Wajib audit inspektur tambang.';
        } elseif ($riskScore >= 40) {
            $warningLevel = 'ELEVATED';
            $recommendedAction = 'PERINGATAN: Lakukan penimbangan ulang tara setelah bongkar dan periksa pembersihan bak truk.';
        } elseif ($isOverloaded) {
            $warningLevel = 'SAFETY_HAZARD';
            $recommendedAction = 'BAHAYA K3: Truk mengalami kelebihan beban. Kurangi muatan di pit front sebelum perjalanan berikutnya.';
        }

        return [
            'truck_plate' => $plate,
            'current_net_kg' => $currentNetKg,
            'current_net_ton' => $currentNetTon,
            'tare_drift_pct' => $tareDriftPct,
            'is_tare_anomalous' => $isTareAnomalous,
            'is_overloaded' => $isOverloaded,
            'overload_margin_pct' => $overloadMarginPct,
            'is_underloaded' => $isUnderloaded,
            'is_speeding_or_route_breached' => $isSpeedingOrRouteBreached,
            'overall_risk_score' => $riskScore,
            'fraud_warning_level' => $warningLevel,
            'recommended_action' => $recommendedAction,
        ];
    }
}
