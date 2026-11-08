<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\ValueObjects;

/**
 * Value Object: Aviation Distance & Navigation Matrix.
 *
 * Menghitung jarak lingkaran besar (Great Circle Distance) menggunakan rumus Haversine
 * presisi tinggi, mengkonversi kilometer ke mil laut (Nautical Miles / NM),
 * serta memperkirakan waktu terbang blok (Block Time) dan konsumsi bahan bakar standar.
 */
final class AviationDistanceMatrix
{
    private const EARTH_RADIUS_KM = 6371.0;

    private const KM_TO_NAUTICAL_MILES = 0.539957;

    /**
     * Hitung jarak Great Circle antara dua koordinat geografis.
     *
     * @param  float  $lat1  Lintang asal (derajat desimal)
     * @param  float  $lon1  Bujur asal (derajat desimal)
     * @param  float  $lat2  Lintang tujuan (derajat desimal)
     * @param  float  $lon2  Bujur tujuan (derajat desimal)
     * @return array{
     *     distance_km: float,
     *     distance_nautical_miles: int,
     *     initial_bearing_degrees: float,
     *     estimated_flight_time_minutes: int,
     *     estimated_block_time_minutes: int
     * }
     */
    public static function calculateGreatCircle(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2.0) * sin($dLat / 2.0) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2.0) * sin($dLon / 2.0);

        $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        $distKm = self::EARTH_RADIUS_KM * $c;

        $distNm = (int) round($distKm * self::KM_TO_NAUTICAL_MILES);

        // Hitung sudut haluan awal (Initial Bearing / Course)
        $y = sin($dLon) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) -
            sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos($dLon);
        $bearingRad = atan2($y, $x);
        $bearingDeg = fmod((rad2deg($bearingRad) + 360.0), 360.0);

        // Estimasi waktu jelajah: Kecepatan jelajah rata-rata jet komersial 450 knot
        $airborneMinutes = (int) ceil(($distNm / 450.0) * 60.0);
        // Block time = Waktu jelajah + 20 menit manuver lepas landas, antre taxiway, dan pendaratan
        $blockTimeMinutes = $airborneMinutes + 20;

        return [
            'distance_km' => round($distKm, 2),
            'distance_nautical_miles' => $distNm,
            'initial_bearing_degrees' => round($bearingDeg, 1),
            'estimated_flight_time_minutes' => $airborneMinutes,
            'estimated_block_time_minutes' => $blockTimeMinutes,
        ];
    }

    /**
     * Dapatkan matriks Ticketed Point Mileage (TPM) resmi untuk pasangan rute utama Indonesia.
     *
     * @return int TPM dalam Nautical Miles
     */
    public static function getTicketedPointMileage(string $originIata, string $destIata): int
    {
        $pair = strtoupper($originIata).'-'.strtoupper($destIata);
        $reversePair = strtoupper($destIata).'-'.strtoupper($originIata);

        $tpmTable = [
            'CGK-DPS' => 612,
            'CGK-SUB' => 370,
            'CGK-KNO' => 762,
            'CGK-UPG' => 872,
            'CGK-BPN' => 682,
            'SUB-DPS' => 188,
            'DPS-LOP' => 66,
            'UPG-MDC' => 512,
            'CGK-SIN' => 547,
            'DPS-SYD' => 2868,
        ];

        return $tpmTable[$pair] ?? ($tpmTable[$reversePair] ?? 500);
    }
}
