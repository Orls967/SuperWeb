<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

/**
 * Service Transit Kanal Internasional & Tol Jalur Pelayaran Strategis.
 * Menghitung tol Terusan Suez (SCNT/SDR), Terusan Panama (PCUMS), dan Selat Malaka (TSS VTIS).
 */
final class CanalTransitAndPassageTollService
{
    // Nilai tukar SDR (Special Drawing Rights IMF) ke USD rata-rata
    public const SDR_TO_USD_RATE = 1.3350;

    /**
     * Hitung estimasi biaya transit Terusan Suez (Suez Canal Authority Rules of Navigation).
     *
     * @param  float  $suezNetTonnage  Bobot bersih kapal menurut sertifikat SCNT
     * @param  string  $vesselType  BULK_CARRIER, CONTAINER, TANKER
     * @param  bool  $isLaden  Status bermuatan (laden) atau kosong (in ballast)
     * @param  bool  $requiresSearchlightRental  Memerlukan sewa lampu sorot kanal
     * @return array{
     *     canal: string,
     *     scnt_tonnage: float,
     *     tier_rate_sdr: float,
     *     base_toll_sdr: float,
     *     base_toll_usd: float,
     *     ancillary_charges_usd: float,
     *     total_transit_cost_usd: float
     * }
     */
    public function estimateSuezCanalToll(
        float $suezNetTonnage,
        string $vesselType,
        bool $isLaden = true,
        bool $requiresSearchlightRental = true
    ): array {
        // Tarif berjenjang SDR per SCNT: Laden kapal muat membayar lebih tinggi dibanding ballast
        $rateMultiplier = $isLaden ? 1.0 : 0.85;
        $tierRateSdr = match ($vesselType) {
            'CONTAINER' => 7.80 * $rateMultiplier,
            'PRODUCT_TANKER' => 8.20 * $rateMultiplier,
            default => 6.90 * $rateMultiplier,
        };

        $baseTollSdr = $suezNetTonnage * $tierRateSdr;
        $baseTollUsd = $baseTollSdr * self::SDR_TO_USD_RATE;

        // Biaya tambahan: pandu kanal, mooring boats, sewa lampu sorot kanal
        $ancillaryUsd = 4500.0;
        if ($requiresSearchlightRental) {
            $ancillaryUsd += 1200.0;
        }

        $totalUsd = $baseTollUsd + $ancillaryUsd;

        return [
            'canal' => 'SUEZ_CANAL',
            'scnt_tonnage' => round($suezNetTonnage, 2),
            'tier_rate_sdr' => round($tierRateSdr, 4),
            'base_toll_sdr' => round($baseTollSdr, 2),
            'base_toll_usd' => round($baseTollUsd, 2),
            'ancillary_charges_usd' => round($ancillaryUsd, 2),
            'total_transit_cost_usd' => round($totalUsd, 2),
        ];
    }

    /**
     * Hitung pungutan pelayaran melintasi Traffic Separation Scheme (TSS) Selat Malaka.
     * Standar: Tripartite Cooperative Mechanism (Indonesia, Malaysia, Singapore) & Straits Light Dues.
     */
    public function estimateMalaccaStraitPassageDues(
        float $grossTonnage,
        float $designDraftM,
        bool $deepWaterRouteRequired = false
    ): array {
        // Deep Water Route (DWR) wajib untuk kapal draft > 15.0 meter demi keselamatan kandas
        $mustUseDwr = ($designDraftM >= 15.0) || $deepWaterRouteRequired;

        $lightDuesUsd = $grossTonnage * 0.085;
        $vtisReportingFeeUsd = 450.0;
        $escortTugUsd = $mustUseDwr ? 8500.0 : 0.0;

        $total = $lightDuesUsd + $vtisReportingFeeUsd + $escortTugUsd;

        return [
            'strait' => 'STRAIT_OF_MALACCA_TSS',
            'gross_tonnage' => round($grossTonnage, 2),
            'deep_water_route_mandatory' => $mustUseDwr,
            'light_dues_usd' => round($lightDuesUsd, 2),
            'vtis_fee_usd' => round($vtisReportingFeeUsd, 2),
            'escort_tug_usd' => round($escortTugUsd, 2),
            'total_passage_cost_usd' => round($total, 2),
        ];
    }
}
