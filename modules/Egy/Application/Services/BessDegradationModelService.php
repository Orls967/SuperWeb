<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use InvalidArgumentException;
use Modules\Egy\Application\DTOs\BessHealthAssessmentDTO;
use Modules\Egy\Domain\Models\BessAsset;

/**
 * Layanan Pemodelan Degradasi Elektrokimia Sel Baterai BESS.
 * Menghitung penuaan kalender (calendar aging) dan penuaan siklus (cycling aging)
 * berdasarkan kedalaman pengosongan (DoD - Depth of Discharge) dan suhu rata-rata.
 */
final class BessDegradationModelService
{
    /**
     * Lakukan asesmen kesehatan baterai dan estimasi sisa siklus berguna (Remaining Useful Life).
     *
     * @param  int  $ambientTemperatureCelsius  Suhu rata-rata ruang kontainer baterai (misal 25 C)
     * @param  int  $averageDoDBps  Rata-rata Depth of Discharge per siklus (misal 8000 = 80%)
     */
    public function assessBatteryHealth(
        string $bessCode,
        int $ambientTemperatureCelsius = 25,
        int $averageDoDBps = 8000
    ): BessHealthAssessmentDTO {
        $asset = BessAsset::where('bess_code', $bessCode)->firstOrFail();

        $capacityKwh = (int) $asset->capacity_kwh;
        $totalCycles = (int) $asset->cycle_count;

        if ($ambientTemperatureCelsius < 0 || $ambientTemperatureCelsius > 60) {
            throw new InvalidArgumentException('Suhu ruang baterai tidak realistis.');
        }

        // 1. Cycling Aging Loss:
        // Sel LFP standar: 6000 siklus pada 80% DoD menuju 80% SoH (kehilangan 2000 bps / 6000 siklus ≈ 0.33 bps per siklus)
        // Faktor akselerasi temperatur Arrhenius: kenaikan tiap 10C di atas 25C melipatgandakan laju degradasi kalender
        $tempMultiplierBps = 10000;
        if ($ambientTemperatureCelsius > 25) {
            $deltaT = $ambientTemperatureCelsius - 25;
            $tempMultiplierBps += (int) intdiv($deltaT * 4000, 10); // +40% per 10C
        }

        // DoD non-linear factor: DoD 80% = 1.0, DoD 100% = 1.3, DoD 50% = 0.5
        $dodFactorBps = (int) intdiv($averageDoDBps * 125, 100);

        // Kehilangan kapasitas akibat siklus (bps)
        $cyclingLossBps = (int) intdiv(
            $totalCycles * $dodFactorBps * 33,
            100000
        );

        // 2. Calendar Aging Loss (berdasarkan umur baterai sejak dibuat)
        $daysInService = max(1, (int) $asset->created_at?->diffInDays(now()));
        // Kehilangan kalender tipikal LFP: ~1.5% per tahun (150 bps per 365 hari ≈ 0.41 bps/hari)
        $calendarLossBps = (int) intdiv(
            $daysInService * 41 * $tempMultiplierBps,
            1000000
        );

        // Total degradasi dan SoH saat ini
        $totalDegradationBps = $cyclingLossBps + $calendarLossBps;
        $calculatedSohBps = max(5000, 10000 - $totalDegradationBps);

        // Kapasitas tersisa aktual
        $currentRetainedKwh = (int) intdiv($capacityKwh * $calculatedSohBps, 10000);

        // Estimasi sisa siklus hingga mencapai ambang batas pensiun 80% SoH (2000 bps degradasi)
        $remainingLossBudgetBps = max(0, 2000 - $totalDegradationBps);
        $lossPerCycleBps = max(1, (int) intdiv($dodFactorBps * 33, 100000));
        $estimatedRemainingCycles = (int) intdiv($remainingLossBudgetBps, $lossPerCycleBps);

        // Akumulasi cadangan penggantian baterai (Rp 2.500.000 / kWh total biaya penggantian)
        $replacementUnitCost = 2500000;
        $totalReplacementCostIdr = $capacityKwh * $replacementUnitCost;
        $cumulativeReserveIdr = (int) intdiv($totalReplacementCostIdr * $totalDegradationBps, 10000);

        // Perbarui SoH pada model
        $asset->state_of_health_bps = $calculatedSohBps;
        $asset->save();

        $hashPayload = sprintf(
            'BESS-HEALTH|CODE:%s|CHEM:%s|CAP:%d|RET_KWH:%d|SOH:%d|CYC:%d|CAL:%d|REMAIN:%d|RES:%d',
            $asset->bess_code,
            $asset->battery_chemistry,
            $capacityKwh,
            $currentRetainedKwh,
            $calculatedSohBps,
            $totalCycles,
            $calendarLossBps,
            $estimatedRemainingCycles,
            $cumulativeReserveIdr
        );
        $assessmentHash = hash('sha256', $hashPayload);

        return new BessHealthAssessmentDTO(
            bessCode: $asset->bess_code,
            batteryChemistry: $asset->battery_chemistry,
            nominalCapacityKwh: $capacityKwh,
            currentRetainedCapacityKwh: $currentRetainedKwh,
            stateOfHealthBps: $calculatedSohBps,
            totalEquivalentFullCycles: $totalCycles,
            calendarAgingLossBps: $calendarLossBps,
            cyclingAgingLossBps: $cyclingLossBps,
            estimatedRemainingCyclesTo80Pct: $estimatedRemainingCycles,
            cumulativeReplacementReserveAccruedIdr: $cumulativeReserveIdr,
            degradationBreakdownBps: [
                'calendar_loss_bps' => $calendarLossBps,
                'cycling_loss_bps' => $cyclingLossBps,
                'temperature_stress_bps' => max(0, $tempMultiplierBps - 10000),
            ],
            assessmentHash: $assessmentHash
        );
    }
}
