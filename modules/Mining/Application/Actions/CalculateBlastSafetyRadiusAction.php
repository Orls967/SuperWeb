<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;

/**
 * Domain Action: Kalkulasi Radius Aman Peledakan Tambang (Blast Safety Exclusion Zone).
 * Menghitung batas aman flyrock (metode Lundborg & McKenzie), getaran tanah Peak Particle Velocity (PPV
 * formula USBM / Indonesian SNI 7571:2010), dan batas overpressure gelombang kejut udara (Airblast dB).
 */
class CalculateBlastSafetyRadiusAction
{
    /**
     * @param  array{
     *     hole_diameter_mm: float,
     *     charge_weight_per_delay_kg: float,
     *     distance_to_nearest_structure_m: float,
     *     rock_type?: string,
     *     site_constant_k?: float,
     *     attenuation_exponent_beta?: float,
     *     safety_margin_factor?: float
     * } $data
     * @return array{
     *     flyrock_max_throw_meters: float,
     *     recommended_clearance_radius_personnel_meters: float,
     *     recommended_clearance_radius_equipment_meters: float,
     *     estimated_ppv_mm_s: float,
     *     is_ppv_compliant_sni: bool,
     *     estimated_airblast_db: float,
     *     is_airblast_compliant: bool
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $holeDiameterMm = (float) $data['hole_diameter_mm'];
        $holeDiameterM = $holeDiameterMm / 1000.0;
        $chargeKg = (float) $data['charge_weight_per_delay_kg'];
        $distanceM = (float) $data['distance_to_nearest_structure_m'];
        $safetyFactor = (float) ($data['safety_margin_factor'] ?? 1.50);

        // Model Flyrock Lundborg (1981): L_max = 260 * (d^(2/3))
        // di mana d dalam meter, menghasilkan lemparan maksimum dalam meter
        $lundborgMaxThrowM = 260.0 * ($holeDiameterM ** (2.0 / 3.0));

        // Model Face Burst / Crater Ejection (McKenzie 2009):
        // Koreksi tambahan bila isian bahan peledak per delay tinggi
        $craterFlyrockM = 40.0 * ($chargeKg ** (1.0 / 3.0));
        $predictedFlyrockM = max($lundborgMaxThrowM, $craterFlyrockM);

        // Radius eksklusi personel = flyrock max * safety factor (minimum 500 meter baku industri)
        $personnelRadiusM = round(max(500.0, $predictedFlyrockM * $safetyFactor), 1);
        // Radius eksklusi alat berat / armada tambang (minimum 300 meter)
        $equipmentRadiusM = round(max(300.0, $predictedFlyrockM * 1.15), 1);

        // Perhitungan PPV (USBM formula): PPV = k * (SD)^(-beta)
        // Scaled Distance SD = D / sqrt(W)
        $scaledDistance = $distanceM / sqrt($chargeKg);
        $k = (float) ($data['site_constant_k'] ?? 1140.0); // Standar batuan keras rata-rata
        $beta = (float) ($data['attenuation_exponent_beta'] ?? 1.60);

        $ppv = round($k * ($scaledDistance ** (-$beta)), 3);

        // Batas baku mutu SNI 7571:2010 untuk bangunan permanen pemukiman: max 3.0 mm/s (kategori 1), fasilitas tambang 10.0 mm/s
        $isPpvCompliant = $ppv <= 5.0;

        // Model Airblast Overpressure (Perkins formula):
        // Overpressure (kPa) = 3.3 * (D / (W^(1/3)))^(-1.2)
        $cubeRootScaledDistance = $distanceM / ($chargeKg ** (1.0 / 3.0));
        $overpressureKpa = 3.3 * ($cubeRootScaledDistance ** (-1.2));
        $airblastDb = round(20.0 * log10(($overpressureKpa * 1000.0) / (20.0 * (10 ** -6))), 1);

        // Batas Kepmen ESDM No. 1827 K/2018: max 120 dB untuk batas IUP/pemukiman terdekat
        $isAirblastCompliant = $airblastDb <= 120.0;

        return [
            'flyrock_max_throw_meters' => round($predictedFlyrockM, 2),
            'recommended_clearance_radius_personnel_meters' => $personnelRadiusM,
            'recommended_clearance_radius_equipment_meters' => $equipmentRadiusM,
            'estimated_ppv_mm_s' => $ppv,
            'is_ppv_compliant_sni' => $isPpvCompliant,
            'estimated_airblast_db' => $airblastDb,
            'is_airblast_compliant' => $isAirblastCompliant,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (($data['hole_diameter_mm'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Diameter lubang ledak harus lebih besar dari 0 mm.');
        }

        if (($data['charge_weight_per_delay_kg'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Muatan bahan peledak per delay (kg) harus lebih besar dari 0.');
        }

        if (($data['distance_to_nearest_structure_m'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Jarak ke struktur/pemukiman terdekat harus lebih besar dari 0 meter.');
        }
    }
}
