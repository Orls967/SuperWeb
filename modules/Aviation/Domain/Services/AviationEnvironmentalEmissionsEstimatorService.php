<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

class AviationEnvironmentalEmissionsEstimatorService
{
    /**
     * Hitung emisi CO2 penerbangan berdasarkan tipe pesawat, jarak, dan faktor muat (ICAO Methodology).
     *
     * @param  string  $aircraftIcaoType  Tipe pesawat (e.g. A320, B738, A333)
     * @param  int  $distanceKm  Jarak penerbangan dalam kilometer
     * @param  int  $passengerCount  Jumlah penumpang
     * @param  int  $cargoKg  Berat kargo dalam kg
     * @return array{
     *     aircraft_type: string,
     *     total_fuel_burn_kg: float,
     *     total_co2_kg: float,
     *     co2_per_passenger_kg: float,
     *     saf_blend_offset_eligible: bool,
     *     carbon_offset_units_needed: int
     * }
     */
    public function estimateFlightEmissions(
        string $aircraftIcaoType,
        int $distanceKm,
        int $passengerCount,
        int $cargoKg = 0
    ): array {
        $dist = max(100, $distanceKm);
        $pax = max(1, $passengerCount);

        // Faktor konsumsi bahan bakar rata-rata per km (kg/km) berdasarkan tipe pesawat
        $fuelBurnRatePerKm = match (strtoupper($aircraftIcaoType)) {
            'A333', 'A330', 'B772', 'B788' => 6.2, // Widebody twin-aisle
            'A321', 'B739' => 3.1,                 // Narrowbody besar
            'AT76', 'AT72' => 1.4,                 // Turboprop regional
            default => 2.6,                        // A320, B738 Narrowbody standar
        };

        // Total konsumsi bahan bakar avtur Jet A-1
        $totalFuelKg = ($dist * $fuelBurnRatePerKm) + 400.0; // 400 kg taxiway idling & APU ground run

        // Faktor konversi bahan bakar avtur ke CO2 ICAO: 1 kg Jet A-1 = 3.157 kg CO2
        $totalCo2Kg = $totalFuelKg * 3.157;

        // Alokasi proporsional antara penumpang (asumsi 100 kg per pax termasuk bagasi) dan kargo komersial
        $paxMassKg = $pax * 100.0;
        $totalPayloadKg = max(1.0, $paxMassKg + $cargoKg);
        $paxShare = $paxMassKg / $totalPayloadKg;

        $paxTotalCo2 = $totalCo2Kg * $paxShare;
        $co2PerPax = $paxTotalCo2 / (float) $pax;

        // 1 unit kredit karbon = 1.000 kg (1 ton) CO2
        $creditsNeeded = (int) ceil($totalCo2Kg / 1000.0);

        return [
            'aircraft_type' => strtoupper($aircraftIcaoType),
            'total_fuel_burn_kg' => round($totalFuelKg, 2),
            'total_co2_kg' => round($totalCo2Kg, 2),
            'co2_per_passenger_kg' => round($co2PerPax, 2),
            'saf_blend_offset_eligible' => true,
            'carbon_offset_units_needed' => $creditsNeeded,
        ];
    }
}
