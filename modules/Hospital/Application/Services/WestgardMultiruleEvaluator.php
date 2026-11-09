<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Enums\WestgardRule;
use Modules\Hospital\Domain\Models\LabQualityControl;

/**
 * Mesin Evaluasi Kendali Mutu Analitik Westgard Multirules (Fase 105).
 */
class WestgardMultiruleEvaluator
{
    /**
     * Mengevaluasi hasil pengukuran bahan kontrol QC harian.
     *
     * @param  list<float>  $recentZScores  Daftar Z-Score historis sebelumnya (urutan terbaru di indeks 0)
     * @return array{is_rejected: bool, violation: string|null, z_score: float, description: string}
     */
    public function evaluateQcRun(
        string $analyzerCode,
        string $lotNumber,
        string $controlLevel,
        string $testCode,
        float $targetMean,
        float $targetSd,
        float $measuredValue,
        array $recentZScores = [],
        ?int $technologistId = null
    ): array {
        if ($targetSd <= 0.0) {
            $targetSd = 1.0;
        }

        $zScore = ($measuredValue - $targetMean) / $targetSd;
        $violation = null;
        $isRejected = false;
        $desc = 'Pemeriksaan QC Normal (In-Control).';

        // 1. Cek Aturan 1-3s (Hard Rejection: |Z| > 3.0)
        if (abs($zScore) > 3.0) {
            $violation = WestgardRule::RULE_1_3S->value;
            $isRejected = true;
            $desc = 'PENOLAKAN (1-3s): Deviasi acak ekstrem melampaui 3 SD.';
        }
        // 2. Cek Aturan 2-2s (2 run berturut > 2 SD pada sisi yang sama)
        elseif (count($recentZScores) >= 1 && abs($zScore) > 2.0 && abs($recentZScores[0]) > 2.0 && ($zScore * $recentZScores[0] > 0)) {
            $violation = WestgardRule::RULE_2_2S->value;
            $isRejected = true;
            $desc = 'PENOLAKAN (2-2s): 2 kontrol berturut melebihi 2 SD pada sisi yang sama.';
        }
        // 3. Cek Aturan R-4s (Rentang antara Z-Score saat ini dan sebelumnya melebihi 4 SD)
        elseif (count($recentZScores) >= 1 && abs($zScore - $recentZScores[0]) > 4.0) {
            $violation = WestgardRule::RULE_R_4S->value;
            $isRejected = true;
            $desc = 'PENOLAKAN (R-4s): Rentang antar-level kontrol melebihi 4 SD.';
        }
        // 4. Cek Aturan 4-1s (4 kontrol berturut > 1 SD pada sisi yang sama)
        elseif (count($recentZScores) >= 3 && abs($zScore) > 1.0) {
            $allSameSide = true;
            $sign = $zScore > 0 ? 1 : -1;
            for ($i = 0; $i < 3; $i++) {
                if (abs($recentZScores[$i]) <= 1.0 || ($recentZScores[$i] * $sign <= 0)) {
                    $allSameSide = false;
                    break;
                }
            }
            if ($allSameSide) {
                $violation = WestgardRule::RULE_4_1S->value;
                $isRejected = true;
                $desc = 'PENOLAKAN (4-1s): Pergeseran analitik 4 kontrol berturut melebihi 1 SD.';
            }
        }
        // 5. Cek Aturan 10-x (10 kontrol berturut pada satu sisi rata-rata)
        elseif (count($recentZScores) >= 9) {
            $allOneSide = true;
            $sign = $zScore > 0 ? 1 : -1;
            for ($i = 0; $i < 9; $i++) {
                if ($recentZScores[$i] * $sign <= 0) {
                    $allOneSide = false;
                    break;
                }
            }
            if ($allOneSide) {
                $violation = WestgardRule::RULE_10_X->value;
                $isRejected = true;
                $desc = 'PENOLAKAN (10-x): Pergeseran sistematik kalibrasi 10 kontrol berturut pada satu sisi rata-rata.';
            }
        }
        // 6. Cek Aturan 1-2s (Warning Rule: |Z| > 2.0)
        elseif (abs($zScore) > 2.0) {
            $violation = WestgardRule::RULE_1_2S->value;
            $isRejected = false;
            $desc = 'PERINGATAN (1-2s): 1 nilai kontrol di luar 2 SD, waspadai tren alat.';
        }

        // Catat rekam QC ke tabel hsp_lab_quality_controls
        LabQualityControl::create([
            'analyzer_code' => $analyzerCode,
            'lot_number' => $lotNumber,
            'control_level' => $controlLevel,
            'test_code' => $testCode,
            'target_mean' => $targetMean,
            'target_sd' => $targetSd,
            'measured_value' => $measuredValue,
            'z_score' => $zScore,
            'westgard_violation' => $violation,
            'is_rejected' => $isRejected,
            'is_resolved' => ! $isRejected,
            'technologist_id' => $technologistId,
            'evaluated_at' => now(),
        ]);

        return [
            'is_rejected' => $isRejected,
            'violation' => $violation,
            'z_score' => round($zScore, 4),
            'description' => $desc,
        ];
    }
}
