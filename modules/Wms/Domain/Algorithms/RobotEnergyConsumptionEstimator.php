<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Wms\Domain\Certificates\RobotEnergyCertificate;

/**
 * Estimator deterministik konsumsi energi misi robotika berbasis aritmetika integer (mWh & persentase baterai).
 *
 * Model fisika diskret (tanpa floating-point):
 * - Base travel energy: 120 mWh per meter lintasan
 * - Payload factor: +4 mWh per (meter * kg)
 * - Lift/actuator overhead per misi: 1_500 mWh
 * - Konversi ke persentase baterai: 1% = 4_800 mWh (kapasitas pak baterai standar 480 Wh = 480_000 mWh),
 *   dibulatkan ke atas (ceiling) agar estimasi aman untuk interlock pengisian daya.
 */
final class RobotEnergyConsumptionEstimator
{
    public const int BASE_MWH_PER_METER = 120;

    public const int PAYLOAD_MWH_PER_METER_KG = 4;

    public const int ACTUATOR_OVERHEAD_MWH = 1_500;

    public const int MWH_PER_BATTERY_PCT = 4_800;

    public const int DEFAULT_RESERVE_MIN_PCT = 20;

    /**
     * @param  list<array{mission_id:int, distance_m:int, payload_kg:int, zone_slope_pct?:int}>  $missions
     */
    public function estimateBatch(
        int $robotId,
        int $initialBatteryPct,
        array $missions,
        int $reserveMinBatteryPct = self::DEFAULT_RESERVE_MIN_PCT,
    ): RobotEnergyCertificate {
        if ($initialBatteryPct < 0 || $initialBatteryPct > 100) {
            throw new InvalidArgumentException('initialBatteryPct harus di antara 0 dan 100.');
        }

        if ($reserveMinBatteryPct < 0 || $reserveMinBatteryPct > 90) {
            throw new InvalidArgumentException('reserveMinBatteryPct harus di antara 0 dan 90.');
        }

        $items = [];
        $runningPct = $initialBatteryPct;
        $totalDrain = 0;
        $totalMwh = 0;
        $allFeasible = true;

        foreach ($missions as $mission) {
            $missionId = (int) $mission['mission_id'];
            $distanceM = max(0, (int) $mission['distance_m']);
            $payloadKg = max(0, (int) $mission['payload_kg']);
            $slopePct = max(0, (int) ($mission['zone_slope_pct'] ?? 0));

            $travelMwh = $distanceM * (self::BASE_MWH_PER_METER + ($payloadKg * self::PAYLOAD_MWH_PER_METER_KG));
            $slopePenaltyMwh = intdiv($travelMwh * $slopePct, 100);
            $missionMwh = $travelMwh + $slopePenaltyMwh + self::ACTUATOR_OVERHEAD_MWH;

            $drainPct = intdiv($missionMwh + self::MWH_PER_BATTERY_PCT - 1, self::MWH_PER_BATTERY_PCT);
            $runningPctAfter = $runningPct - $drainPct;
            $feasible = $runningPctAfter >= $reserveMinBatteryPct;

            if (! $feasible) {
                $allFeasible = false;
            }

            $items[] = [
                'mission_id' => $missionId,
                'distance_m' => $distanceM,
                'payload_kg' => $payloadKg,
                'estimated_drain_pct' => $drainPct,
                'energy_mwh' => $missionMwh,
                'feasible' => $feasible,
            ];

            $runningPct = max(0, $runningPctAfter);
            $totalDrain += $drainPct;
            $totalMwh += $missionMwh;
        }

        $projectedEnd = max(0, $initialBatteryPct - $totalDrain);
        if ($projectedEnd < $reserveMinBatteryPct) {
            $allFeasible = false;
        }

        $hash = RobotEnergyCertificate::computeHash(
            $robotId,
            $initialBatteryPct,
            $reserveMinBatteryPct,
            $totalDrain,
            $totalMwh,
            $items,
        );

        $cert = new RobotEnergyCertificate(
            robotId: $robotId,
            initialBatteryPct: $initialBatteryPct,
            reserveMinBatteryPct: $reserveMinBatteryPct,
            totalDrainPct: $totalDrain,
            projectedEndBatteryPct: $projectedEnd,
            totalEnergyMwh: $totalMwh,
            allFeasible: $allFeasible,
            items: $items,
            signatureHash: $hash,
        );

        $cert->verify();

        return $cert;
    }

    /**
     * Menghitung estimasi jarak tempuh maksimum (meter) sebelum menyentuh batas cadangan baterai.
     */
    public function maxSafeRangeMeters(int $currentBatteryPct, int $payloadKg = 0, int $reserveMinBatteryPct = self::DEFAULT_RESERVE_MIN_PCT): int
    {
        $usablePct = max(0, $currentBatteryPct - $reserveMinBatteryPct);
        if ($usablePct === 0) {
            return 0;
        }

        $usableMwh = $usablePct * self::MWH_PER_BATTERY_PCT;
        if ($usableMwh <= self::ACTUATOR_OVERHEAD_MWH) {
            return 0;
        }

        $mwhPerMeter = self::BASE_MWH_PER_METER + (max(0, $payloadKg) * self::PAYLOAD_MWH_PER_METER_KG);

        return intdiv($usableMwh - self::ACTUATOR_OVERHEAD_MWH, $mwhPerMeter);
    }
}
