<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Blasting;

use InvalidArgumentException;

/**
 * Algoritma M8: Kuznetsov-Cunningham-Ouchterlony (KCO) Blasting Fragmentation Model.
 * Memprediksi distribusi fragmentasi ukuran butir batuan pasca-peledakan tambang,
 * mendeteksi fraksi oversize (>1000 mm), dan menghasilkan rekomendasi koreksi pola pemboran.
 */
final class KcoBlastingFragmentationModel
{
    /**
     * @param  float  $rockMassFactorA  Faktor massa batuan RMD (7 = batuan lunak/sedimen, 10 = medium, 14 = sangat keras/basalt)
     * @param  float  $chargeKgPerHole  Massa bahan peledak per lubang ledak (kg)
     */
    public function evaluate(
        BlastGeometry $geometry,
        ExplosiveProperty $explosive,
        float $rockMassFactorA = 10.0,
        float $chargeKgPerHole = 120.0,
        float $oversizeThresholdMm = 1000.0,
        float $finesThresholdMm = 25.0,
        float $maxAcceptableOversizePct = 15.0
    ): KcoBlastingFragmentationModelResult {
        if ($geometry->burdenM <= 0.0 || $geometry->spacingM <= 0.0 || $geometry->benchHeightM <= 0.0) {
            throw new InvalidArgumentException('Dimensi geometri pemboran (burden, spacing, bench height) harus bernilai positif.');
        }

        if ($chargeKgPerHole <= 0.0) {
            throw new InvalidArgumentException('Massa muatan bahan peledak per lubang harus bernilai positif.');
        }

        $rockVolumePerHole = $geometry->rockVolumePerHoleM3();
        $powderFactorQ = $chargeKgPerHole / max(0.1, $rockVolumePerHole); // kg/m^3

        // 1. Perhitungan Ukuran Median x50 (Kuznetsov Formula, hasil cm dikonversi ke mm)
        // x50_cm = A * (q)^(-0.8) * (Qe)^(1/6) * (115 / RWS)^(19/30)
        $termPowderFactor = $powderFactorQ ** (-0.8);
        $termChargeMass = $chargeKgPerHole ** (1.0 / 6.0);
        $termStrength = (115.0 / max(1.0, $explosive->relativeWeightStrengthRws)) ** (19.0 / 30.0);

        $x50Cm = $rockMassFactorA * $termPowderFactor * $termChargeMass * $termStrength;
        $x50Mm = max(1.0, $x50Cm * 10.0); // mm

        // 2. Perhitungan Indeks Keseragaman Cunningham n
        // n = [2.2 - 14 * (B/d)] * sqrt[(1 + S/B)/2] * (1 - W/B) * (Lb/Ltot)
        $diameterM = $geometry->holeDiameterMm / 1000.0;
        $termBd = max(0.01, 2.2 - (14.0 * ($geometry->burdenM / max(0.01, $geometry->holeDiameterMm * 10.0))));
        $ratioSb = $geometry->spacingM / $geometry->burdenM;
        $termSb = sqrt((1.0 + $ratioSb) / 2.0);
        $drillingDeviationTerm = max(0.5, 1.0 - ($geometry->drillingAccuracyDeviationM / $geometry->burdenM));
        $chargeRatio = $geometry->chargeLengthM() / max(0.1, $geometry->holeDepthM);

        $uniformityN = abs($termBd * $termSb * $drillingDeviationTerm * $chargeRatio);
        // Indeks n berada dalam rentang wajar 0.60 hingga 2.20
        $uniformityN = max(0.60, min(2.20, $uniformityN));

        // 3. Karakteristik Ukuran xc (Rosin-Rammler Swebrec distribution)
        // xc = x50 / (ln 2)^(1/n)
        $characteristicSizeXc = $x50Mm / ((log(2.0)) ** (1.0 / $uniformityN));

        // 4. Fraksi Oversize (> 1000 mm) dan Fines (< 25 mm)
        // R(x) = exp[ - (x / xc)^n ] -> persentase tertahan di atas saringan x
        $oversizeRatio = exp(-1.0 * (($oversizeThresholdMm / $characteristicSizeXc) ** $uniformityN));
        $oversizePct = round($oversizeRatio * 100.0, 2);

        $passingFinesRatio = 1.0 - exp(-1.0 * (($finesThresholdMm / $characteristicSizeXc) ** $uniformityN));
        $finesPct = round($passingFinesRatio * 100.0, 2);

        // 5. Evaluasi Kebutuhan Pemecahan Sekunder & Rekomendasi Koreksi Drill Pattern
        $isSecondaryBreakingRequired = ($oversizePct > $maxAcceptableOversizePct);

        $recBurdenAdjM = 0.0;
        $recSpacingAdjM = 0.0;
        $recPfAdj = 0.0;

        if ($isSecondaryBreakingRequired) {
            // Oversize terlalu tinggi: perketat burden (-10%), perketat spacing (-10%), naikkan powder factor (+15%)
            $recBurdenAdjM = -1.0 * round($geometry->burdenM * 0.10, 2);
            $recSpacingAdjM = -1.0 * round($geometry->spacingM * 0.10, 2);
            $recPfAdj = round($powderFactorQ * 0.15, 3);
        } elseif ($finesPct > 25.0) {
            // Fines terlalu tinggi (over-crushed): longgarkan burden (+5%), kurangi powder factor (-10%)
            $recBurdenAdjM = round($geometry->burdenM * 0.05, 2);
            $recSpacingAdjM = round($geometry->spacingM * 0.05, 2);
            $recPfAdj = -1.0 * round($powderFactorQ * 0.10, 3);
        }

        $signature = KcoBlastingFragmentationVerifier::sign(
            round($x50Mm, 2),
            round($uniformityN, 3),
            $oversizePct,
            $finesPct,
            round($powderFactorQ, 3)
        );

        return new KcoBlastingFragmentationModelResult(
            meanFragmentSizeX50Mm: round($x50Mm, 2),
            uniformityIndexN: round($uniformityN, 3),
            characteristicSizeXcPct: round($characteristicSizeXc, 2),
            oversizePercent: $oversizePct,
            finesPercent: $finesPct,
            powderFactorKgPerM3: round($powderFactorQ, 3),
            isSecondaryBreakingRequired: $isSecondaryBreakingRequired,
            recommendedBurdenAdjustmentM: $recBurdenAdjM,
            recommendedSpacingAdjustmentM: $recSpacingAdjM,
            recommendedPowderFactorAdjustmentKgPerM3: $recPfAdj,
            hmacSignature: $signature
        );
    }
}
