<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

class AviationLuggageWeightAndBalanceService
{
    /**
     * Hitung distribusi berat dan titik pusat gravitasi pesawat (Weight & Balance Trim Sheet).
     *
     * @param  float  $dryOperatingWeightKg  Berat kosong operasional pesawat
     * @param  int  $passengerCount  Jumlah penumpang dewasa
     * @param  int  $checkedBaggageKg  Berat bagasi tercatat di kompartemen kargo
     * @param  int  $cargoPayloadKg  Berat kargo komersial
     * @param  int  $takeoffFuelKg  Bahan bakar avtur untuk lepas landas
     * @param  float  $maxZeroFuelWeightKg  Batas struktural maksimum ZFW
     * @param  float  $maxTakeoffWeightKg  Batas struktural maksimum MTOW
     * @return array{
     *     zero_fuel_weight_kg: float,
     *     takeoff_weight_kg: float,
     *     mac_cg_percentage: float,
     *     is_within_envelope: bool,
     *     stabilizer_trim_units: float,
     *     status: string
     * }
     */
    public function calculateTrimSheet(
        float $dryOperatingWeightKg,
        int $passengerCount,
        int $checkedBaggageKg,
        int $cargoPayloadKg,
        int $takeoffFuelKg,
        float $maxZeroFuelWeightKg,
        float $maxTakeoffWeightKg
    ): array {
        // Standar berat penumpang ICAO: 75 kg per dewasa + 7 kg bagasi kabin = 82 kg
        $paxTotalWeight = $passengerCount * 82.0;
        $totalCargoAndBags = (float) ($checkedBaggageKg + $cargoPayloadKg);

        $zfw = $dryOperatingWeightKg + $paxTotalWeight + $totalCargoAndBags;
        $tow = $zfw + $takeoffFuelKg;

        $zfwValid = $zfw <= $maxZeroFuelWeightKg;
        $towValid = $tow <= $maxTakeoffWeightKg;

        // Simulasi kalkulasi indeks Center of Gravity % Mean Aerodynamic Chord (MAC)
        // Hubungan linier estimasi momen muatan depan (Forward Hold) vs belakang (Aft Hold)
        $cargoForwardRatio = 0.55;
        $cargoAftRatio = 0.45;
        $indexDelta = (($totalCargoAndBags * $cargoForwardRatio * -0.05) + ($totalCargoAndBags * $cargoAftRatio * 0.06)) / 1000.0;
        $macCg = 25.0 + $indexDelta; // 25% MAC adalah titik tengah amplop

        // Rentang aman % MAC jet komersial umumnya 18% s.d. 32%
        $cgValid = $macCg >= 18.0 && $macCg <= 32.0;

        $isWithinEnvelope = $zfwValid && $towValid && $cgValid;

        // Penyetelan Trimsheet Stabilizer Horizontal (THS Units UP/DN)
        $trimUnits = round(5.0 - (($macCg - 25.0) * 0.3), 1);

        $status = $isWithinEnvelope ? 'WITHIN_SAFE_LIMITS' : 'EXCEEDED_WEIGHT_OR_CG_LIMITS';

        return [
            'zero_fuel_weight_kg' => round($zfw, 2),
            'takeoff_weight_kg' => round($tow, 2),
            'mac_cg_percentage' => round($macCg, 2),
            'is_within_envelope' => $isWithinEnvelope,
            'stabilizer_trim_units' => $trimUnits,
            'status' => $status,
        ];
    }
}
