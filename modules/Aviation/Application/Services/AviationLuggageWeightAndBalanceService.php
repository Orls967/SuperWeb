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

    /**
     * Hitung amplop berat pendaratan (Landing Weight Envelope) dan batas struktur MLW.
     *
     * @return array{
     *     estimated_landing_weight_kg: float,
     *     max_landing_weight_kg: float,
     *     fuel_burn_trip_kg: int,
     *     under_mlw_margin_kg: float,
     *     overweight_landing_risk: bool
     * }
     */
    public function calculateLandingWeightEnvelope(
        float $takeoffWeightKg,
        int $tripFuelKg,
        float $maxLandingWeightKg
    ): array {
        $landingWeight = max(0.0, $takeoffWeightKg - (float) $tripFuelKg);
        $margin = $maxLandingWeightKg - $landingWeight;
        $overweight = $landingWeight > $maxLandingWeightKg;

        return [
            'estimated_landing_weight_kg' => round($landingWeight, 2),
            'max_landing_weight_kg' => round($maxLandingWeightKg, 2),
            'fuel_burn_trip_kg' => $tripFuelKg,
            'under_mlw_margin_kg' => round($margin, 2),
            'overweight_landing_risk' => $overweight,
        ];
    }

    /**
     * Bagikan muatan bagasi dan kargo ke kompartemen kargo pesawat (Forward Hold vs Aft Hold).
     *
     * @return array{
     *     forward_hold_kg: int,
     *     aft_hold_kg: int,
     *     bulk_hold_kg: int,
     *     total_loaded_kg: int,
     *     forward_capacity_utilization: float,
     *     aft_capacity_utilization: float
     * }
     */
    public function calculateHoldCompartmentDistribution(
        int $totalBaggageKg,
        int $totalCargoKg,
        int $forwardHoldCapacityKg = 3500,
        int $aftHoldCapacityKg = 4200,
        int $bulkHoldCapacityKg = 800
    ): array {
        $totalWeight = $totalBaggageKg + $totalCargoKg;

        // Alokasikan bagasi penumpang prioritas ke Aft Hold untuk kecepatan pembongkaran
        $aftWeight = min($aftHoldCapacityKg, (int) round($totalBaggageKg * 0.7) + (int) round($totalCargoKg * 0.3));
        $remainder = max(0, $totalWeight - $aftWeight);

        $bulkWeight = min($bulkHoldCapacityKg, (int) round($remainder * 0.15));
        $forwardWeight = min($forwardHoldCapacityKg, $remainder - $bulkWeight);

        $fwdUtil = $forwardHoldCapacityKg > 0 ? ($forwardWeight / (float) $forwardHoldCapacityKg) * 100.0 : 0.0;
        $aftUtil = $aftHoldCapacityKg > 0 ? ($aftWeight / (float) $aftHoldCapacityKg) * 100.0 : 0.0;

        return [
            'forward_hold_kg' => $forwardWeight,
            'aft_hold_kg' => $aftWeight,
            'bulk_hold_kg' => $bulkWeight,
            'total_loaded_kg' => $forwardWeight + $aftWeight + $bulkWeight,
            'forward_capacity_utilization' => round($fwdUtil, 1),
            'aft_capacity_utilization' => round($aftUtil, 1),
        ];
    }

    /**
     * Verifikasi kompatibilitas muatan barang berbahaya (Dangerous Goods Segregation & NOTOC).
     *
     * @param  array<int, array{un_number: string, class: string, weight_kg: float, compartment: string}>  $dgItems
     * @return array{is_safe: bool, segregation_violations: array<int, string>, notoc_prepared: bool}
     */
    public function verifyDangerousGoodsSegregation(array $dgItems): array
    {
        $violations = [];
        $compartmentClasses = [];

        foreach ($dgItems as $item) {
            $class = trim($item['class']);
            $comp = strtoupper(trim($item['compartment']));
            $compartmentClasses[$comp][] = $class;
        }

        // Cek pemisahan: Kelas 1 (Eksplosif) tidak boleh sekamar dengan Kelas 2/3 (Mudah Terbakar)
        foreach ($compartmentClasses as $comp => $classes) {
            $hasExplosives = in_array('1', $classes, true) || in_array('1.4', $classes, true);
            $hasFlammables = in_array('2.1', $classes, true) || in_array('3', $classes, true);

            if ($hasExplosives && $hasFlammables) {
                $violations[] = "Inkompatibilitas ICAO: Kelas 1 dan Kelas Flammable berada di kompartemen {$comp}";
            }

            // Radioaktif (Kelas 7) tidak boleh bersebelahan dengan hewan hidup atau film
            $hasRadioactive = in_array('7', $classes, true);
            $hasPerishables = in_array('9_LHO', $classes, true);
            if ($hasRadioactive && $hasPerishables) {
                $violations[] = "Inkompatibilitas IATA DGR: Radioaktif Kelas 7 dan Hewan Hidup di kompartemen {$comp}";
            }
        }

        return [
            'is_safe' => count($violations) === 0,
            'segregation_violations' => $violations,
            'notoc_prepared' => count($dgItems) > 0,
        ];
    }
}
