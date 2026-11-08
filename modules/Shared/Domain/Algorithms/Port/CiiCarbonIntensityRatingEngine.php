<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Port;

/**
 * A12: IMO Carbon Intensity Indicator (CII) & Operational Carbon Rating Engine.
 * 
 * Standar: IMO MARPOL Annex VI Regulation 28, Resolutions MEPC.336(76), MEPC.338(76), MEPC.354(78).
 * Menghitung emisi operasional CO2e kapal, batas referensi CII, batas peringkat A-E,
 * serta rekomendasi rencana tindakan korektif SEEMP Bagian III.
 */
final class CiiCarbonIntensityRatingEngine
{
    // Faktor konversi bahan bakar ke CO2 (CF) menurut MEPC.308(73) (t-CO2 / t-Fuel)
    public const CF_VLSFO = 3.1510;
    public const CF_LSMGO = 3.2060;
    public const CF_LNG = 2.7500;
    public const CF_BIO_B30 = 2.4500; // Blend dengan sertifikasi ISCC

    // Parameter garis referensi IMO: CII_ref = a * Capacity^(-c)
    // Nilai standar MEPC.337(76)
    public const SHIP_PARAMS = [
        'BULK_CARRIER' => ['a' => 4745.0, 'c' => 0.4220, 'd1' => 0.14, 'd2' => 0.05, 'd3' => 0.06, 'd4' => 0.18],
        'CONTAINER' => ['a' => 1984.0, 'c' => 0.4890, 'd1' => 0.19, 'd2' => 0.07, 'd3' => 0.08, 'd4' => 0.22],
        'PRODUCT_TANKER' => ['a' => 5247.0, 'c' => 0.4610, 'd1' => 0.18, 'd2' => 0.06, 'd3' => 0.07, 'd4' => 0.20],
        'GENERAL_CARGO' => ['a' => 31940.0, 'c' => 0.6120, 'd1' => 0.22, 'd2' => 0.08, 'd3' => 0.09, 'd4' => 0.25],
        'LPG_CARRIER' => ['a' => 14405.0, 'c' => 0.5380, 'd1' => 0.16, 'd2' => 0.05, 'd3' => 0.06, 'd4' => 0.19],
    ];

    /**
     * Hitung Attained CII dan peringkat operasional tahunan.
     *
     * @param string $shipType
     * @param float $capacityDwt Kapasitas deadweight tonnage (DWT) kapal
     * @param float $distanceTraveledNm Jarak pelayaran yang ditempuh (nautical miles)
     * @param array{vlsfo_mt?: float, lsmgo_mt?: float, lng_mt?: float, bio_mt?: float} $fuelConsumedMt
     * @param int $year Tahun evaluasi (misal 2026)
     * @return array{
     *     ship_type: string,
     *     capacity_dwt: float,
     *     distance_nm: float,
     *     total_co2_grams: float,
     *     attained_cii: float,
     *     reference_cii: float,
     *     required_cii: float,
     *     rating: string, // A, B, C, D, E
     *     rating_label: string,
     *     boundaries: array{
     *         superior_a: float,
     *         minor_superior_b: float,
     *         moderate_c: float,
     *         minor_inferior_d: float
     *     },
     *     corrective_action_required: bool,
     *     recommended_speed_reduction_pct: float
     * }
     */
    public function evaluateCii(
        string $shipType,
        float $capacityDwt,
        float $distanceTraveledNm,
        array $fuelConsumedMt,
        int $year = 2026
    ): array {
        $params = self::SHIP_PARAMS[$shipType] ?? self::SHIP_PARAMS['BULK_CARRIER'];
        $capacity = max(1000.0, $capacityDwt);
        $distance = max(10.0, $distanceTraveledNm);

        // 1. Total CO2 emisi dalam gram:
        $vlsfo = $fuelConsumedMt['vlsfo_mt'] ?? 0.0;
        $lsmgo = $fuelConsumedMt['lsmgo_mt'] ?? 0.0;
        $lng = $fuelConsumedMt['lng_mt'] ?? 0.0;
        $bio = $fuelConsumedMt['bio_mt'] ?? 0.0;

        $totalCo2Tons = ($vlsfo * self::CF_VLSFO) +
            ($lsmgo * self::CF_LSMGO) +
            ($lng * self::CF_LNG) +
            ($bio * self::CF_BIO_B30);

        $totalCo2Grams = $totalCo2Tons * 1_000_000.0;

        // 2. Attained CII (g CO2 / DWT.nm):
        $transportWork = $capacity * $distance;
        $attainedCii = $totalCo2Grams / $transportWork;

        // 3. Reference line: CII_ref = a * Capacity^(-c)
        $refCii = $params['a'] * ($capacity ** (-1.0 * $params['c']));

        // 4. Reduction factor Z (%):
        // 2023 = 5%, 2024 = 7%, 2025 = 9%, 2026 = 11%, 2027 = 13%
        $baseZ = 5.0 + max(0, ($year - 2023) * 2.0);
        $reductionFactor = min(20.0, $baseZ);
        $requiredCii = ((100.0 - $reductionFactor) / 100.0) * $refCii;

        // 5. Rating Boundaries IMO:
        $boundaryA = exp(-1.0 * $params['d1']) * $requiredCii;
        $boundaryB = exp(-1.0 * $params['d2']) * $requiredCii;
        $boundaryC = exp($params['d3']) * $requiredCii;
        $boundaryD = exp($params['d4']) * $requiredCii;

        // 6. Tentukan Rating:
        $rating = 'C';
        $ratingLabel = 'Moderate';
        $correctiveAction = false;

        if ($attainedCii <= $boundaryA) {
            $rating = 'A';
            $ratingLabel = 'Major Superior';
        } elseif ($attainedCii <= $boundaryB) {
            $rating = 'B';
            $ratingLabel = 'Minor Superior';
        } elseif ($attainedCii <= $boundaryC) {
            $rating = 'C';
            $ratingLabel = 'Moderate';
        } elseif ($attainedCii <= $boundaryD) {
            $rating = 'D';
            $ratingLabel = 'Minor Inferior';
        } else {
            $rating = 'E';
            $ratingLabel = 'Inferior';
            $correctiveAction = true;
        }

        // Rekomendasi speed reduction jika di bawah rating C:
        // Karena daya propulsi sebanding dengan V^3, reduksi kecepatan 10% menghemat ~25% emisi.
        $speedReductionPct = 0.0;
        if ($attainedCii > $boundaryC) {
            $excessRatio = $attainedCii / $boundaryC;
            // (1 - x)^2 ≈ 1 / excessRatio
            $speedReductionPct = round((1.0 - (1.0 / sqrt($excessRatio))) * 100.0, 1);
            $speedReductionPct = min(25.0, max(2.0, $speedReductionPct));
        }

        return [
            'ship_type' => $shipType,
            'capacity_dwt' => round($capacity, 2),
            'distance_nm' => round($distance, 2),
            'total_co2_grams' => round($totalCo2Grams, 2),
            'attained_cii' => round($attainedCii, 4),
            'reference_cii' => round($refCii, 4),
            'required_cii' => round($requiredCii, 4),
            'rating' => $rating,
            'rating_label' => $ratingLabel,
            'boundaries' => [
                'superior_a' => round($boundaryA, 4),
                'minor_superior_b' => round($boundaryB, 4),
                'moderate_c' => round($boundaryC, 4),
                'minor_inferior_d' => round($boundaryD, 4),
            ],
            'corrective_action_required' => $correctiveAction,
            'recommended_speed_reduction_pct' => $speedReductionPct,
        ];
    }
}

