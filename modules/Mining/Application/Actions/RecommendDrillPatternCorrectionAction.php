<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningBlastFragmentationEval;

/**
 * Domain Action: Rekomendasi Koreksi Pola Pengeboran & Peledakan (Blast Pattern Correction).
 * Menggunakan umpan balik kurva fragmentasi KCO untuk melakukan loop optimasi tertutup
 * terhadap parameter burden, spacing, stemming, dan powder factor untuk bench berikutnya.
 */
class RecommendDrillPatternCorrectionAction
{
    /**
     * @param  array{
     *     fragmentation_eval_id: string,
     *     target_crusher_grizzly_mm?: float,
     *     target_diggability_bucket_fill_pct?: float
     * } $data
     * @return array{
     *     eval_code: string,
     *     current_d50_mm: float,
     *     current_boulder_pct: float,
     *     current_fines_pct: float,
     *     correction_verdict: string,
     *     recommended_adjustments: array{
     *         burden_multiplier: float,
     *         spacing_multiplier: float,
     *         powder_factor_adjustment_pct: float,
     *         stemming_adjustment_meters: float,
     *         timing_delay_recommendation_ms: string
     *     },
     *     action_items: list<string>
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $eval = MiningBlastFragmentationEval::query()->findOrFail($data['fragmentation_eval_id']);

        $boulderPct = (float) $eval->boulder_oversize_percent;
        $finesPct = (float) $eval->fines_undersize_percent;
        $d50Mm = (float) $eval->mean_fragment_size_d50_mm;

        $burdenMult = 1.0;
        $spacingMult = 1.0;
        $pfAdjustPct = 0.0;
        $stemmingDeltaM = 0.0;
        $delayRec = 'STANDARD_17MS_BETWEEN_HOLES_42MS_BETWEEN_ROWS';
        $actions = [];
        $verdict = 'OPTIMAL';

        if ($boulderPct > 15.0) {
            $verdict = 'EXCESSIVE_BOULDERS_SECONDARY_BREAKING_RISK';
            $burdenMult = 0.90; // Kurangi burden 10%
            $spacingMult = 0.92;
            $pfAdjustPct = +12.0; // Naikkan powder factor 12%
            $stemmingDeltaM = -0.3; // Kurangi stemming sedikit agar collar tidak boulder
            $delayRec = 'TIGHTENED_25MS_INTER_ROW_FOR_INCREASED_COLLISION';
            $actions[] = 'Perkecil burden dan spacing pada baris pertama dekat free face.';
            $actions[] = 'Tingkatkan energi spesifik powder factor minimal 0.05 kg/m3.';
            $actions[] = 'Gunakan bottom priming dengan electronic detonator untuk inisiasi serentak optimal.';
        } elseif ($finesPct > 20.0) {
            $verdict = 'EXCESSIVE_FINES_ORE_LOSS_AND_DUST';
            $burdenMult = 1.08;
            $spacingMult = 1.06;
            $pfAdjustPct = -10.0;
            $stemmingDeltaM = +0.4;
            $delayRec = 'EXTENDED_42MS_INTER_ROW_TO_PREVENT_PULVERIZATION';
            $actions[] = 'Perlebar pola pemboran (expanded pattern) untuk menekan fines.';
            $actions[] = 'Tambah panjang stemming pasir silika/crushed aggregate untuk menahan gas ledak.';
            $actions[] = 'Kurangi penggunaan explosive berkekuatan kejut tinggi (VOD tinggi), ganti ANFO rasio lebih besar.';
        } else {
            $actions[] = 'Pola pemboran dan isian peledakan saat ini telah optimal untuk crushing & loading.';
            $actions[] = 'Pertahankan parameter burden, spacing, dan delay saat ini.';
        }

        // Simpan rekomendasi ke metadata evaluasi
        $meta = $eval->metadata ?? [];
        $meta['pattern_correction_recommendations'] = [
            'verdict' => $verdict,
            'burden_multiplier' => $burdenMult,
            'spacing_multiplier' => $spacingMult,
            'pf_adjust_pct' => $pfAdjustPct,
            'action_items' => $actions,
            'generated_at' => now()->toIso8601String(),
        ];
        $eval->metadata = $meta;
        $eval->save();

        return [
            'eval_code' => $eval->eval_code,
            'current_d50_mm' => $d50Mm,
            'current_boulder_pct' => $boulderPct,
            'current_fines_pct' => $finesPct,
            'correction_verdict' => $verdict,
            'recommended_adjustments' => [
                'burden_multiplier' => $burdenMult,
                'spacing_multiplier' => $spacingMult,
                'powder_factor_adjustment_pct' => $pfAdjustPct,
                'stemming_adjustment_meters' => $stemmingDeltaM,
                'timing_delay_recommendation_ms' => $delayRec,
            ],
            'action_items' => $actions,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['fragmentation_eval_id'])) {
            throw new InvalidArgumentException('Fragmentation evaluation ID wajib diisi.');
        }
    }
}
