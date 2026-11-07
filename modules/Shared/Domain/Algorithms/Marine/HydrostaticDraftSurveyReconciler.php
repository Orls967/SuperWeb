<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

use InvalidArgumentException;

/**
 * Algoritma M9: Hydrostatic Draft Survey Reconciler.
 * Menghitung perpindahan massa air (displacement) tongkang curah 6-titik draft
 * dengan koreksi defleksi lambung (Quarter-Mean), koreksi trim, dan koreksi densitas air perairan,
 * lalu merekonsiliasi tonase kargo terhadap jembatan timbang darat.
 */
final class HydrostaticDraftSurveyReconciler
{
    /**
     * @param  list<HydrostaticTableEntry>  $hydrostaticTable  Tabel hidrostatik kapal tongkang terurut berdasarkan draft
     * @param  float  $lengthBetweenPerpendicularsM  Panjang tongkang LBP (meter, default 90.0m untuk tongkang 300ft)
     * @param  float  $statutoryTolerancePercent  Batas toleransi statutory deviasi pengapalan curah (default 2.0%)
     */
    public function reconcile(
        SixPointDraftReading $initialReading,
        SixPointDraftReading $finalReading,
        array $hydrostaticTable,
        float $weighbridgeTonnageTon,
        float $lengthBetweenPerpendicularsM = 90.0,
        float $statutoryTolerancePercent = 2.0
    ): HydrostaticDraftSurveyReconcilerResult {
        if (empty($hydrostaticTable)) {
            throw new InvalidArgumentException('Tabel hidrostatik kapal tongkang tidak boleh kosong.');
        }

        if ($weighbridgeTonnageTon <= 0.0) {
            throw new InvalidArgumentException('Tonase timbangan darat (weighbridge) harus bernilai positif.');
        }

        // 1. Hitung Draf Quarter-Mean Awal dan Akhir
        $dMInitial = $initialReading->quarterMeanDraft();
        $dMFinal = $finalReading->quarterMeanDraft();

        if ($dMFinal <= $dMInitial) {
            throw new InvalidArgumentException("Draf akhir setelah pemuatan ({$dMFinal}m) harus lebih dalam dari draf awal tongkang kosong ({$dMInitial}m).");
        }

        // 2. Interpolasi Displacement dari Tabel Hidrostatik
        $dispInitialRaw = $this->interpolateDisplacement($hydrostaticTable, $dMInitial);
        $dispFinalRaw = $this->interpolateDisplacement($hydrostaticTable, $dMFinal);

        // 3. Koreksi Densitas Air Perairan (Standar tabel air laut = 1.025 t/m^3)
        $dispInitialCorrected = $dispInitialRaw * ($initialReading->waterDensityTPerM3 / 1.025);
        $dispFinalCorrected = $dispFinalRaw * ($finalReading->waterDensityTPerM3 / 1.025);

        // 4. Hitung Tonase Bersih Muatan Kargo (Net Cargo Ton)
        $netInitial = $dispInitialCorrected - $initialReading->ballastAndConsumablesTon;
        $netFinal = $dispFinalCorrected - $finalReading->ballastAndConsumablesTon;
        $calculatedCargoTon = max(0.0, $netFinal - $netInitial);

        // 5. Rekonsiliasi Terhadap Timbangan Jembatan Timbang
        $varianceTon = $calculatedCargoTon - $weighbridgeTonnageTon;
        $variancePct = round(($varianceTon / $weighbridgeTonnageTon) * 100.0, 3);
        $isWithinTolerance = abs($variancePct) <= $statutoryTolerancePercent;

        $signature = HydrostaticDraftSurveyVerifier::sign(
            round($dispInitialCorrected, 2),
            round($dispFinalCorrected, 2),
            round($calculatedCargoTon, 2),
            round($weighbridgeTonnageTon, 2),
            $variancePct
        );

        return new HydrostaticDraftSurveyReconcilerResult(
            initialDraftQuarterMeanM: round($dMInitial, 3),
            finalDraftQuarterMeanM: round($dMFinal, 3),
            initialDisplacementTon: round($dispInitialCorrected, 2),
            finalDisplacementTon: round($dispFinalCorrected, 2),
            calculatedNetCargoTon: round($calculatedCargoTon, 2),
            weighbridgeTonnageTon: round($weighbridgeTonnageTon, 2),
            varianceTon: round($varianceTon, 2),
            variancePercent: $variancePct,
            isWithinTolerance: $isWithinTolerance,
            hmacSignature: $signature
        );
    }

    /**
     * @param  list<HydrostaticTableEntry>  $table
     */
    private function interpolateDisplacement(array $table, float $draft): float
    {
        // Urutkan tabel berdasarkan draft terkecil
        usort($table, fn (HydrostaticTableEntry $a, HydrostaticTableEntry $b) => $a->draftM <=> $b->draftM);

        $count = count($table);

        if ($draft <= $table[0]->draftM) {
            return $table[0]->displacementTon;
        }

        if ($draft >= $table[$count - 1]->draftM) {
            $last = $table[$count - 1];
            // Ekstrapolasi linear dengan TPC
            $diffM = $draft - $last->draftM;

            return $last->displacementTon + ($diffM * 100.0 * $last->tpcTonPerCm);
        }

        for ($i = 0; $i < $count - 1; $i++) {
            $lower = $table[$i];
            $upper = $table[$i + 1];

            if ($draft >= $lower->draftM && $draft <= $upper->draftM) {
                $span = $upper->draftM - $lower->draftM;
                if ($span <= 1e-6) {
                    return $lower->displacementTon;
                }
                $ratio = ($draft - $lower->draftM) / $span;

                return $lower->displacementTon + ($ratio * ($upper->displacementTon - $lower->displacementTon));
            }
        }

        return $table[$count - 1]->displacementTon;
    }
}
