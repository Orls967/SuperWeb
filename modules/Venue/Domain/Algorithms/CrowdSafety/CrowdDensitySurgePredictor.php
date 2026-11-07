<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\CrowdSafety;

/**
 * Algoritma V14: CrowdDensitySurgePredictor.
 * Memprediksi kepadatan kerumunan 15 menit ke depan menggunakan diferensial laju aliran masuk-keluar
 * dan merekomendasikan pembatasan gerbang otomatis guna mencegah potensi crowd crush.
 */
final class CrowdDensitySurgePredictor
{
    /**
     * Hitung proyeksi kepadatan dan rekomendasi tindakan keselamatan.
     */
    public function predictSurge(
        int $zoneId,
        int $currentHeadcount,
        int $zoneAreaSqm,
        int $legalCapacityLimit,
        int $inflowVelocityPerMinute,
        int $outflowVelocityPerMinute,
        int $weatherFactorBps = 10000,
        string $saltSecret = 'CROWD_SURGE_SECRET_2026'
    ): CrowdPredictionResult {
        // 1. Net Velocity per menit disesuaikan faktor cuaca
        $rawNetFlow = $inflowVelocityPerMinute - $outflowVelocityPerMinute;
        $netVelocity = (int) intdiv($rawNetFlow * $weatherFactorBps, 10000);

        // 2. Proyeksi 15 menit
        $predicted15m = max(0, $currentHeadcount + (15 * $netVelocity));

        // 3. Kerapatan dalam basis poin (orang / sqm * 10.000)
        $densityBps = (int) intdiv($predicted15m * 10000, max(1, $zoneAreaSqm));

        // 4. Evaluasi Aksi & Pembatasan Gerbang
        // Thresholds:
        // Densitas >= 4.5 orang/sqm (45.000 bps) ATAU Headcount >= Kapasitas Legal -> RESTRICT_ACCESS
        // Densitas >= 3.5 orang/sqm (35.000 bps) ATAU Headcount >= 85% Kapasitas -> SLOW_ENTRY
        // Headcount >= 95% Kapasitas -> RESTRICT_ACCESS
        $capacityRatioBps = (int) intdiv($predicted15m * 10000, max(1, $legalCapacityLimit));

        $gateRestricted = false;
        $action = 'NORMAL';

        if ($predicted15m >= $legalCapacityLimit || $densityBps >= 45000 || $capacityRatioBps >= 9500) {
            $action = 'RESTRICT_ACCESS';
            $gateRestricted = true;
        } elseif ($densityBps >= 35000 || $capacityRatioBps >= 8500) {
            $action = 'SLOW_ENTRY';
            $gateRestricted = false;
        }

        $now = time();
        $payload = sprintf(
            '%d:%d:%d:%d:%s:%d:%d',
            $zoneId,
            $currentHeadcount,
            $predicted15m,
            $densityBps,
            $action,
            $gateRestricted ? 1 : 0,
            $now
        );
        $signature = hash_hmac('sha256', $payload, $saltSecret);

        return new CrowdPredictionResult(
            zoneId: $zoneId,
            currentHeadcount: $currentHeadcount,
            predictedHeadcount15m: $predicted15m,
            predictedDensityBps: $densityBps,
            recommendedAction: $action,
            gateRestricted: $gateRestricted,
            certificateSignature: $signature,
            generatedAtTimestamp: $now
        );
    }
}
