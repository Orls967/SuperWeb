<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Kalkulator Stabilitas Termal Vaksin & Biologis Rantai Dingin (VaccineThermalStabilityCalculator).
 * Mengimplementasikan persamaan fisika kimia Arrhenius untuk menghitung Mean Kinetic Temperature (MKT).
 * MKT memberikan representasi matematis setara dari degradasi termal kinetik
 * kumulatif yang dialami protein/vaksin dibandingkan dengan rata-rata aritmatika sederhana.
 */
class VaccineThermalStabilityCalculator
{
    /**
     * Konstanta Gas Universal (R) dalam kJ / (mol * K).
     */
    private const GAS_CONSTANT_R = 0.008314462;

    /**
     * Energi Aktivasi Standar (Delta H) untuk produk biologis / vaksin termolabil dalam kJ / mol.
     * Standar FDA & WHO USP <1150>: 83.144 kJ/mol.
     */
    private const STANDARD_ACTIVATION_ENERGY_KJ_PER_MOL = 83.144;

    /**
     * Hitung Mean Kinetic Temperature (MKT) dalam derajat Celcius (°C) dari serangkaian titik data suhu.
     *
     * @param  array<int, float>  $temperaturesCelsius  Daftar pembacaan suhu telemetri kontinu
     * @param  float|null  $customActivationEnergy  Energi aktivasi spesifik produk (opsional)
     * @return array{
     *     sample_count: int,
     *     arithmetic_mean_c: float,
     *     mkt_c: float,
     *     min_temp_c: float,
     *     max_temp_c: float,
     *     is_shelf_life_impacted: bool,
     *     recommended_action: string
     * }
     */
    public function calculateMeanKineticTemperature(
        array $temperaturesCelsius,
        ?float $customActivationEnergy = null
    ): array {
        $n = count($temperaturesCelsius);
        if ($n === 0) {
            throw new DomainException('Daftar pembacaan suhu tidak boleh kosong untuk menghitung MKT.');
        }

        $deltaH = $customActivationEnergy ?? self::STANDARD_ACTIVATION_ENERGY_KJ_PER_MOL;
        $r = self::GAS_CONSTANT_R;

        $sumExp = 0.0;
        $sumArithmetic = 0.0;
        $minTemp = $temperaturesCelsius[0];
        $maxTemp = $temperaturesCelsius[0];

        foreach ($temperaturesCelsius as $tempC) {
            $sumArithmetic += $tempC;
            if ($tempC < $minTemp) {
                $minTemp = $tempC;
            }
            if ($tempC > $maxTemp) {
                $maxTemp = $tempC;
            }

            // Konversi Celcius ke Kelvin
            $tempK = $tempC + 273.15;
            if ($tempK <= 0) {
                throw new DomainException("Suhu mutlak Kelvin tidak valid ({$tempK} K).");
            }

            // Nilai eksponensial e^(-Delta H / (R * T))
            $exponent = -$deltaH / ($r * $tempK);
            $sumExp += exp($exponent);
        }

        $arithmeticMeanC = round($sumArithmetic / $n, 2);

        // Rumus MKT = (Delta H / R) / -ln( sumExp / n )
        $avgExp = $sumExp / $n;
        if ($avgExp <= 0.0) {
            throw new DomainException('Nilai rata-rata eksponensial tidak valid.');
        }

        $lnAvgExp = log($avgExp);
        $mktKelvin = ($deltaH / $r) / -$lnAvgExp;
        $mktCelsius = round($mktKelvin - 273.15, 2);

        // Ambang stabilitas cold chain chiller standar (2°C - 8°C):
        // Jika MKT > 8.0°C atau suhu puncak > 15.0°C, maka masa simpan terdampak signifikan.
        $isImpacted = $mktCelsius > 8.0 || $maxTemp > 15.0 || $minTemp < 0.0;

        $action = 'RELEASE_APPROVED';
        if ($isImpacted) {
            if ($maxTemp > 25.0 || $minTemp < -2.0) {
                $action = 'DISPOSAL_REQUIRED';
            } else {
                $action = 'ACCELERATED_STABILITY_TESTING_REQUIRED';
            }
        }

        return [
            'sample_count' => $n,
            'arithmetic_mean_c' => $arithmeticMeanC,
            'mkt_c' => $mktCelsius,
            'min_temp_c' => round($minTemp, 2),
            'max_temp_c' => round($maxTemp, 2),
            'is_shelf_life_impacted' => $isImpacted,
            'recommended_action' => $action,
        ];
    }
}
