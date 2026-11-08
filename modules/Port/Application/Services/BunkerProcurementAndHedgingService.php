<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

/**
 * Service Pengadaan Bahan Bakar Bunker, Pengujian Kualitas ISO 8217 & Lindung Nilai (Hedging).
 * Memeriksa parameter kritis bahan bakar kapal niaga guna mencegah kerusakan mesin di laut lepas.
 */
final class BunkerProcurementAndHedgingService
{
    /**
     * Evaluasi sertifikat uji laboratorium sampel bunker (ISO 8217:2017 Table 2).
     *
     * @param array{
     *     sample_seal_number: string,
     *     fuel_grade: string,
     *     density_15c_kg_m3: float,
     *     kinematic_viscosity_50c_cst: float,
     *     flash_point_celsius: float,
     *     sulphur_mass_pct: float,
     *     water_volume_pct: float,
     *     al_plus_si_mg_kg: float, // Catalytic fines
     *     total_sediment_potential_mass_pct: float
     * } $labResult
     * @return array{
     *     is_on_spec: bool,
     *     grade: string,
     *     violations: array<int, string>,
     *     risk_assessment: string
     * }
     */
    public function evaluateBunkerQualityCertificate(array $labResult): array
    {
        $violations = [];

        // 1. Flash point minimum SOLAS Ch. II-2 Reg. 4: tidak boleh < 60°C untuk keselamatan kebakaran
        if ($labResult['flash_point_celsius'] < 60.0) {
            $violations[] = "Flash point ({$labResult['flash_point_celsius']}°C) di bawah batas minimum SOLAS (60°C). Bahaya ledakan tangki!";
        }

        // 2. Sulphur cap MARPOL Annex VI
        $maxSulphur = $labResult['fuel_grade'] === 'LSMGO' ? 0.10 : 0.50;
        if ($labResult['sulphur_mass_pct'] > $maxSulphur) {
            $violations[] = "Kandungan belerang ({$labResult['sulphur_mass_pct']}%) melampaui batas MARPOL Annex VI ({$maxSulphur}%).";
        }

        // 3. Water content ISO 8217 max 0.50% v/v
        if ($labResult['water_volume_pct'] > 0.50) {
            $violations[] = "Kadar air ({$labResult['water_volume_pct']}%) melampaui batas toleransi 0.50%.";
        }

        // 4. Catalytic Fines (Al + Si) max 60 mg/kg untuk mencegah aus silinder liner mesin
        if ($labResult['al_plus_si_mg_kg'] > 60.0) {
            $violations[] = "Kandungan partikel katalis Al+Si ({$labResult['al_plus_si_mg_kg']} mg/kg) melampaui batas aman 60 mg/kg.";
        }

        $isOnSpec = empty($violations);
        $riskAssessment = $isOnSpec
            ? 'Bahan bakar memenuhi spesifikasi ISO 8217:2017 dan aman untuk dibakar di mesin kapal.'
            : 'Bahan bakar OFF-SPEC. Direkomendasikan mengajukan Bunker Quality Dispute (Letter of Protest).';

        return [
            'is_on_spec' => $isOnSpec,
            'grade' => $labResult['fuel_grade'],
            'violations' => $violations,
            'risk_assessment' => $riskAssessment,
        ];
    }

    /**
     * Hitung kontrak lindung nilai (Bunker Swap / Collar Hedging) untuk melindungi margin TCE kapal.
     *
     * @param  float  $volumeMt  Volume bahan bakar yang dilindungi
     * @param  float  $strikePriceUsd  Harga patokan (strike price) kontrak derivatif
     * @param  float  $actualMarketPriceUsd  Harga pasar riil saat penyelesaian (settlement)
     * @return array{
     *     volume_mt: float,
     *     strike_price: float,
     *     market_price: float,
     *     hedge_payout_usd: float,
     *     net_effective_price_per_mt: float
     * }
     */
    public function calculateBunkerSwapSettlement(
        float $volumeMt,
        float $strikePriceRate,
        float $actualMarketPriceRate
    ): array {
        // Jika harga pasar > strike price: trader hedging membayar kompensasi selisih kenaikan
        // Jika harga pasar < strike price: kapal membayar selisih ke trader
        $diffPerMt = $actualMarketPriceRate - $strikePriceRate;
        $hedgePayout = $volumeMt * $diffPerMt;

        // Harga efektif setelah hedging selalu sama dengan strike price
        $netEffectivePrice = $actualMarketPriceRate - ($hedgePayout / max(1.0, $volumeMt));

        return [
            'volume_mt' => round($volumeMt, 2),
            'strike_price' => round($strikePriceRate, 2),
            'market_price' => round($actualMarketPriceRate, 2),
            'hedge_payout_usd' => round($hedgePayout, 2),
            'net_effective_price_per_mt' => round($netEffectivePrice, 2),
        ];
    }
}
