<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use InvalidArgumentException;

/**
 * Pelacak Deplesi & Rekonsiliasi Cadangan Tambang KCMI / JORC Code (D94-4, D94-6).
 *
 * Mengkalkulasi penipisan cadangan mineral/batubara berdasarkan kaidah Competent Person Indonesia (CPI):
 * 1. Neraca Cadangan Terbukti (Proved Reserves) & Cadangan Terkira (Probable Reserves):
 *    Cadangan Akhir = Cadangan Awal - Produksi Tertambang + Eksplorasi Konversi Baru.
 * 2. Nisbah Kupas Aktual vs Rencana (Stripping Ratio / SR):
 *    SR Aktual = Volume Overburden (BCM) / Tonase Batubara/Bijih (Ton).
 * 3. Amortisasi Biaya Pengupasan Tanah Lebih (Deferred Stripping Asset Amortization):
 *    Bila SR Aktual > SR Rencana Hidup Tambang (LOM SR), kelebihan biaya dikapitalisasi sebagai aset stripping.
 */
final class MineExplorationReserveDepletionTracker
{
    /**
     * @param array{
     *     pit_code: string,
     *     initial_proved_reserves_tons: int,
     *     initial_probable_reserves_tons: int,
     *     mined_production_tons: int,
     *     exploration_conversion_tons?: int,
     *     actual_overburden_bcm: int,
     *     planned_life_of_mine_sr: float,
     *     stripping_cost_per_bcm_idr: int
     * } $data
     * @return array{
     *     pit_code: string,
     *     closing_proved_reserves_tons: int,
     *     closing_probable_reserves_tons: int,
     *     total_remaining_reserves_tons: int,
     *     actual_stripping_ratio: float,
     *     planned_lom_sr: float,
     *     excess_stripping_bcm: int,
     *     capitalized_stripping_asset_idr: int,
     *     reserve_depletion_pct: float,
     *     exhaustion_risk_verdict: string
     * }
     */
    public function track(array $data): array
    {
        $pit = trim((string) $data['pit_code']);
        $provedTons = (int) $data['initial_proved_reserves_tons'];
        $probableTons = (int) $data['initial_probable_reserves_tons'];
        $minedTons = (int) $data['mined_production_tons'];
        $newTons = (int) ($data['exploration_conversion_tons'] ?? 0);
        $obBcm = (int) $data['actual_overburden_bcm'];
        $lomSr = (float) $data['planned_life_of_mine_sr'];
        $costPerBcm = (int) $data['stripping_cost_per_bcm_idr'];

        if ($provedTons < 0 || $minedTons < 0 || $obBcm < 0 || $lomSr <= 0 || $costPerBcm <= 0) {
            throw new InvalidArgumentException('Parameter cadangan, produksi, dan stripping ratio harus valid.');
        }

        // 1. Neraca Penipisan Cadangan
        $closingProved = max(0, $provedTons - $minedTons);
        $closingProbable = $probableTons + $newTons;
        $totalClosing = $closingProved + $closingProbable;

        $totalInitial = $provedTons + $probableTons;
        $depletionPct = $totalInitial > 0 ? round(($minedTons / $totalInitial) * 100, 2) : 0.0;

        // 2. Stripping Ratio Aktual
        $actualSr = $minedTons > 0 ? round($obBcm / $minedTons, 2) : 0.0;

        // 3. Deferred Stripping Asset (PSAK 64 / IFRIC 20)
        $expectedObBcm = (int) round($minedTons * $lomSr);
        $excessObBcm = max(0, $obBcm - $expectedObBcm);
        $capitalizedAssetIdr = $excessObBcm * $costPerBcm;

        $verdict = ($closingProved > ($minedTons * 3))
            ? "Cadangan pit {$pit} melimpah (Umur cadangan terbukti > 3 tahun operasi)."
            : "PERINGATAN DEPLESI: Cadangan terbukti pit {$pit} mendekati habis (Tersisa {$closingProved} ton). Percepat infill drilling.";

        return [
            'pit_code' => $pit,
            'closing_proved_reserves_tons' => $closingProved,
            'closing_probable_reserves_tons' => $closingProbable,
            'total_remaining_reserves_tons' => $totalClosing,
            'actual_stripping_ratio' => $actualSr,
            'planned_lom_sr' => $lomSr,
            'excess_stripping_bcm' => $excessObBcm,
            'capitalized_stripping_asset_idr' => $capitalizedAssetIdr,
            'reserve_depletion_pct' => $depletionPct,
            'exhaustion_risk_verdict' => $verdict,
        ];
    }
}
