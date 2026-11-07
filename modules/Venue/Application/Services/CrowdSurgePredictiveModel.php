<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentZone;

final class CrowdSurgePredictiveModel
{
    public const SAFETY_THRESHOLD_DENSITY_SQM = 4.0; // orang per m2
    public const CRUSH_ALARM_DENSITY_SQM = 5.5;      // batas fatal pergerakan kerumunan
    public const PANIC_COMPRESSION_RATIO = 0.85;

    /**
     * Compute real-time crowd density per square meter and surge velocity.
     *
     * @return array{
     *     zone_code: string,
     *     area_sqm: float,
     *     occupancy: int,
     *     density_sqm: float,
     *     density_bps: int,
     *     safety_index: float,
     *     risk_tier: string,
     *     requires_gate_hold: bool,
     *     recommendation: string
     * }
     */
    public function evaluateZoneDensity(EntertainmentZone $zone, int $currentOccupancy, float $assumedAreaSqm = 500.0): array
    {
        $areaSqm = max(50.0, $assumedAreaSqm);
        $density = round($currentOccupancy / $areaSqm, 2);
        $densityBps = (int) round($density * 100);

        $capacityLimit = $zone->capacity_limit > 0 ? $zone->capacity_limit : 1000;
        $utilizationRatio = $currentOccupancy / $capacityLimit;

        $riskTier = 'NORMAL';
        $requiresGateHold = false;
        $recommendation = 'Kepadatan kerumunan dalam ambang batas aman.';

        if ($density >= self::CRUSH_ALARM_DENSITY_SQM || $utilizationRatio >= 0.95) {
            $riskTier = 'CRITICAL_CRUSH_HAZARD';
            $requiresGateHold = true;
            $recommendation = 'BAHAYA KERUMUNAN: Hentikan admisi gerbang segera. Aktifkan sirine evakuasi terarah.';
        } elseif ($density >= self::SAFETY_THRESHOLD_DENSITY_SQM || $utilizationRatio >= 0.85) {
            $riskTier = 'WARNING_SURGE_PENDING';
            $requiresGateHold = true;
            $recommendation = 'Peringatan lonjakan kerumunan. Perlambat turnstile, arahkan pengunjung ke zona penyangga.';
        } elseif ($utilizationRatio >= 0.70) {
            $riskTier = 'ELEVATED';
            $recommendation = 'Kepadatan meningkat. Siapkan staf pengarah di persimpangan koridor.';
        }

        $safetyIndex = round(max(0.0, 1.0 - ($density / self::CRUSH_ALARM_DENSITY_SQM)), 3);

        return [
            'zone_code' => $zone->zone_code,
            'area_sqm' => $areaSqm,
            'occupancy' => $currentOccupancy,
            'density_sqm' => $density,
            'density_bps' => $densityBps,
            'safety_index' => $safetyIndex,
            'risk_tier' => $riskTier,
            'requires_gate_hold' => $requiresGateHold,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Calculate shockwave compression along narrow corridors or egress bottleneck chokepoints.
     *
     * @param float $corridorWidthMeters
     * @param int $ingressRatePerMin
     * @param int $egressRatePerMin
     * @return array{compression_ratio: float, bottleneck_delay_seconds: int, is_choked: bool}
     */
    public function calculateCorridorChokePoint(
        float $corridorWidthMeters,
        int $ingressRatePerMin,
        int $egressRatePerMin
    ): array {
        $effectiveFlowCapacity = (int) round($corridorWidthMeters * 50); // ~50 orang/meter/menit
        $actualEgress = min($egressRatePerMin, $effectiveFlowCapacity);

        $accumulation = max(0, $ingressRatePerMin - $actualEgress);
        $compressionRatio = $effectiveFlowCapacity > 0 ? round($ingressRatePerMin / $effectiveFlowCapacity, 2) : 1.0;

        $isChoked = $compressionRatio > 1.2 || $accumulation > 20;
        $delaySeconds = $actualEgress > 0 ? (int) round(($accumulation / $actualEgress) * 60) : 0;

        return [
            'compression_ratio' => $compressionRatio,
            'bottleneck_delay_seconds' => $delaySeconds,
            'is_choked' => $isChoked,
        ];
    }
}
