<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

/**
 * Algoritma Penunjang: Multilateral Prorate Agreement (MPA) Weighted Factor Calculator.
 *
 * Mengimplementasikan metode kurva eksponensial tertimbang IATA Prorate Manual Passenger (PMP)
 * untuk menghitung faktor prorasi penerbangan multi-sektor:
 * PF = a * (TPM)^b + c
 * di mana parameter kalibrasi disesuaikan per wilayah geografis (TC1, TC2, TC3).
 */
class MultilateralProrateAgreementCalculator
{
    /**
     * Hitung faktor prorasi tertimbang IATA untuk jarak terbang tertentu.
     *
     * @param  int  $tpmDistance  Jarak Ticketed Point Mileage dalam mil laut
     * @param  string  $tariffConferenceArea  Wilayah IATA: TC1 (Amerika), TC2 (Eropa/Afrika), TC3 (Asia-Pasifik)
     * @return array{
     *     tpm: int,
     *     area: string,
     *     prorate_factor: float,
     *     curve_parameters: array{a: float, b: float, c: float}
     * }
     */
    public function calculateProrateFactor(int $tpmDistance, string $tariffConferenceArea = 'TC3'): array
    {
        $d = max(10, $tpmDistance);

        // Parameter resmi kalibrasi IATA PMP per kawasan tarif dunia
        $params = match (strtoupper($tariffConferenceArea)) {
            'TC1' => ['a' => 1.785, 'b' => 0.812, 'c' => 85.0],
            'TC2' => ['a' => 1.920, 'b' => 0.795, 'c' => 95.0],
            default => ['a' => 2.050, 'b' => 0.780, 'c' => 110.0], // TC3 Asia Tenggara & Pasifik Barat
        };

        $prorateFactor = ($params['a'] * pow($d, $params['b'])) + $params['c'];

        return [
            'tpm' => $d,
            'area' => strtoupper($tariffConferenceArea),
            'prorate_factor' => round($prorateFactor, 4),
            'curve_parameters' => $params,
        ];
    }

    /**
     * Bagikan total tarif tiket bersih ke beberapa segmen rute menggunakan proporsi faktor prorasi.
     *
     * @param  int  $netFareMinorUnit  Tarif bersih total
     * @param  array<int, array{segment_id: string|int, tpm: int, area?: string}>  $segments
     * @return array<int, array{segment_id: string|int, tpm: int, prorate_factor: float, allocated_fare_minor_unit: int}>
     */
    public function distributeMultiSectorFare(int $netFareMinorUnit, array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        $factors = [];
        $totalFactor = 0.0;

        foreach ($segments as $seg) {
            $calc = $this->calculateProrateFactor(
                (int) $seg['tpm'],
                $seg['area'] ?? 'TC3'
            );
            $factors[] = [
                'segment_id' => $seg['segment_id'],
                'tpm' => $calc['tpm'],
                'prorate_factor' => $calc['prorate_factor'],
            ];
            $totalFactor += $calc['prorate_factor'];
        }

        $allocated = [];
        $runningSum = 0;
        $count = count($factors);

        for ($i = 0; $i < $count; $i++) {
            if ($i === $count - 1) {
                // Selisih pembulatan dialokasikan ke segmen terakhir untuk menjamin zero-rounding discrepancy
                $fareShare = $netFareMinorUnit - $runningSum;
            } else {
                $proportion = $totalFactor > 0 ? ($factors[$i]['prorate_factor'] / $totalFactor) : (1.0 / $count);
                $fareShare = (int) round($netFareMinorUnit * $proportion);
                $runningSum += $fareShare;
            }

            $allocated[] = [
                'segment_id' => $factors[$i]['segment_id'],
                'tpm' => $factors[$i]['tpm'],
                'prorate_factor' => $factors[$i]['prorate_factor'],
                'allocated_fare_minor_unit' => $fareShare,
            ];
        }

        return $allocated;
    }
}
