<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use InvalidArgumentException;

/**
 * Interpreter Analisis Gas Darah (AGD / BGA) & Gangguan Asam-Basa Elektrolit (Fase 105).
 *
 * Menerapkan kaidah fisiologis respirasi dan nefrologi komprehensif:
 * - Persamaan Henderson-Hasselbalch (pH vs pCO2 vs HCO3)
 * - Anion Gap Serum = Na - (Cl + HCO3) [Normal 8-12 mEq/L, atau terkoreksi albumin]
 * - Anion Gap Terkoreksi = AG + 2.5 * (4.0 - Albumin)
 * - Delta Ratio = (AG - 12) / (24 - HCO3) untuk mendeteksi mixed acid-base disorder
 * - Aturan Kompensasi Winter's Formula: Expected pCO2 = 1.5 * HCO3 + 8 +/- 2
 * - Kompensasi Asidosis Respiratorik Akut vs Kronik (+1 vs +3.5 mEq/L HCO3 per 10 mmHg pCO2)
 * - Kompensasi Alkalosis Respiratorik Akut vs Kronik (-2 vs -5 mEq/L HCO3 per 10 mmHg pCO2)
 * - Rasio PaO2 / FiO2 (Indeks Horowitz) untuk klasifikasi ARDS Berlin Criteria (Mild/Mod/Severe)
 * - Alveolar-arterial O2 gradient (A-a gradient) = [FiO2*(Patm - PH2O) - PaCO2/R] - PaO2
 */
class BloodGasElectrolyteAcidBaseInterpreter
{
    /**
     * Interpretasi komprehensif profil analisa gas darah & elektrolit.
     *
     * @param  float  $pH  pH darah arteri (normal: 7.35 - 7.45)
     * @param  float  $pCO2  Tekanan parsial karbon dioksida mmHg (normal: 35 - 45)
     * @param  float  $pO2  Tekanan parsial oksigen mmHg (normal: 80 - 100)
     * @param  float  $hco3  Bikarbonat serum mEq/L (normal: 22 - 26)
     * @param  float  $na  Natrium serum mEq/L (normal: 135 - 145)
     * @param  float  $cl  Klorida serum mEq/L (normal: 96 - 106)
     * @param  float  $fiO2  Fraksi oksigen inspirasi (0.21 - 1.0)
     * @param  float|null  $albumin  Albumin serum g/dL (normal: 4.0)
     * @param  float  $atmosphericPressure  Tekanan atmosfer mmHg (default 760 di permukaan laut)
     * @return array{
     *     primary_disorder: string,
     *     compensation_status: string,
     *     anion_gap: float,
     *     corrected_anion_gap: float,
     *     anion_gap_category: string,
     *     delta_ratio: float|null,
     *     delta_interpretation: string|null,
     *     expected_compensation: array<string, mixed>,
     *     pao2_fio2_ratio: float,
     *     oxygenation_status: string,
     *     a_a_gradient: float,
     *     shunts_or_diffusion_impairment: bool,
     *     clinical_summary: string,
     *     actionable_recommendations: list<string>
     * }
     */
    public function interpret(
        float $pH,
        float $pCO2,
        float $pO2,
        float $hco3,
        float $na,
        float $cl,
        float $fiO2 = 0.21,
        ?float $albumin = null,
        float $atmosphericPressure = 760.0
    ): array {
        if ($pH <= 0.0 || $pCO2 <= 0.0 || $pO2 <= 0.0 || $hco3 <= 0.0) {
            throw new InvalidArgumentException('Nilai analit gas darah harus bernilai positif.');
        }

        if ($fiO2 < 0.21 || $fiO2 > 1.0) {
            throw new InvalidArgumentException('FiO2 harus berada dalam rentang fisiologis 0.21 hingga 1.0.');
        }

        // 1. Hitung Anion Gap & Koreksi Albumin
        $anionGap = round($na - ($cl + $hco3), 2);
        $effectiveAlbumin = $albumin ?? 4.0;
        $correctedAnionGap = $anionGap;
        if ($effectiveAlbumin < 4.0) {
            // Formula Figge-Jabor-Kazda: AG_koreksi = AG + 2.5 * (4.0 - Albumin)
            $correctedAnionGap = round($anionGap + 2.5 * (4.0 - $effectiveAlbumin), 2);
        }

        $anionGapCategory = 'NORMAL';
        if ($correctedAnionGap > 12.0) {
            $anionGapCategory = 'ELEVATED (HAGMA)';
        } elseif ($correctedAnionGap < 6.0) {
            $anionGapCategory = 'LOW';
        }

        // 2. Evaluasi Gangguan Utama Asam-Basa
        $primaryDisorder = 'NORMAL ACID-BASE STATUS';
        $compensationStatus = 'NOT_APPLICABLE';
        $expectedComp = [];
        $recommendations = [];

        if ($pH < 7.35) {
            // Asidemia
            if ($pCO2 > 45.0 && $hco3 < 22.0) {
                $primaryDisorder = 'MIXED ACIDOSIS (RESPIRATORY & METABOLIC)';
                $compensationStatus = 'UNCOMPENSATED_MIXED';
                $recommendations[] = 'Gangguan asidosis campuran mengancam jiwa; perbaiki ventilasi alveolar dan atasi etiologi asidosis metabolik.';
            } elseif ($pCO2 > 45.0) {
                $primaryDisorder = 'RESPIRATORY ACIDOSIS';
                $deltaPco2 = $pCO2 - 40.0;
                $expectedAcuteHco3 = round(24.0 + (1.0 * ($deltaPco2 / 10.0)), 1);
                $expectedChronicHco3 = round(24.0 + (3.5 * ($deltaPco2 / 10.0)), 1);

                $expectedComp = [
                    'rule' => 'Respiratory Acidosis Compensation',
                    'expected_acute_hco3' => $expectedAcuteHco3,
                    'expected_chronic_hco3' => $expectedChronicHco3,
                ];

                if (abs($hco3 - $expectedAcuteHco3) <= 2.0) {
                    $compensationStatus = 'ACUTE RESPIRATORY ACIDOSIS (ADEQUATE)';
                } elseif (abs($hco3 - $expectedChronicHco3) <= 2.0) {
                    $compensationStatus = 'CHRONIC RESPIRATORY ACIDOSIS (ADEQUATE COMPENSATED)';
                } elseif ($hco3 < $expectedAcuteHco3) {
                    $compensationStatus = 'RESPIRATORY ACIDOSIS WITH CONCOMITANT METABOLIC ACIDOSIS';
                } else {
                    $compensationStatus = 'RESPIRATORY ACIDOSIS WITH CONCOMITANT METABOLIC ALKALOSIS';
                }
                $recommendations[] = 'Evaluasi depresi napas, hipoventilasi, PPOK eksaserbasi, atau obstruksi jalan napas.';
            } else {
                // pCO2 <= 45, HCO3 < 22 -> Asidosis Metabolik
                $primaryDisorder = ($anionGapCategory === 'ELEVATED (HAGMA)')
                    ? 'HIGH ANION GAP METABOLIC ACIDOSIS (HAGMA)'
                    : 'NORMAL ANION GAP METABOLIC ACIDOSIS (NAGMA)';

                // Winter's Formula: pCO2 = 1.5 * HCO3 + 8 (+/- 2)
                $expectedPco2 = round((1.5 * $hco3) + 8.0, 1);
                $expectedPco2Min = $expectedPco2 - 2.0;
                $expectedPco2Max = $expectedPco2 + 2.0;

                $expectedComp = [
                    'rule' => "Winter's Formula for Metabolic Acidosis",
                    'expected_pco2_target' => $expectedPco2,
                    'range' => "{$expectedPco2Min} - {$expectedPco2Max}",
                ];

                if ($pCO2 >= $expectedPco2Min && $pCO2 <= $expectedPco2Max) {
                    $compensationStatus = 'METABOLIC ACIDOSIS WITH ADEQUATE RESPIRATORY COMPENSATION';
                } elseif ($pCO2 > $expectedPco2Max) {
                    $compensationStatus = 'METABOLIC ACIDOSIS WITH CO-EXISTING RESPIRATORY ACIDOSIS';
                    $recommendations[] = 'Gagal napas sekunder teridentifikasi; pCO2 melebihi batas kompensasi Winter.';
                } else {
                    $compensationStatus = 'METABOLIC ACIDOSIS WITH CO-EXISTING RESPIRATORY ALKALOSIS';
                }

                if ($anionGapCategory === 'ELEVATED (HAGMA)') {
                    $recommendations[] = 'Skrining etiologi GOLD MARK: Glycols, Oxoproline, L-Lactate, D-Lactate, Methanol, Aspirin, Renal failure, Ketoacidosis.';
                } else {
                    $recommendations[] = 'Skrining kehilangan bikarbonat (diare masif) atau asidosis tubulus ginjal (RTA).';
                }
            }
        } elseif ($pH > 7.45) {
            // Alkalemia
            if ($pCO2 < 35.0 && $hco3 > 26.0) {
                $primaryDisorder = 'MIXED ALKALOSIS (RESPIRATORY & METABOLIC)';
                $compensationStatus = 'UNCOMPENSATED_MIXED';
                $recommendations[] = 'Alkalosis campuran berat; waspadai aritmia kordis dan hipokalemia refrakter.';
            } elseif ($pCO2 < 35.0) {
                $primaryDisorder = 'RESPIRATORY ALKALOSIS';
                $deltaPco2 = 40.0 - $pCO2;
                $expectedAcuteHco3 = round(24.0 - (2.0 * ($deltaPco2 / 10.0)), 1);
                $expectedChronicHco3 = round(24.0 - (5.0 * ($deltaPco2 / 10.0)), 1);

                $expectedComp = [
                    'rule' => 'Respiratory Alkalosis Compensation',
                    'expected_acute_hco3' => $expectedAcuteHco3,
                    'expected_chronic_hco3' => $expectedChronicHco3,
                ];

                if (abs($hco3 - $expectedAcuteHco3) <= 2.0) {
                    $compensationStatus = 'ACUTE RESPIRATORY ALKALOSIS';
                } elseif (abs($hco3 - $expectedChronicHco3) <= 2.0) {
                    $compensationStatus = 'CHRONIC RESPIRATORY ALKALOSIS';
                } else {
                    $compensationStatus = 'RESPIRATORY ALKALOSIS WITH MIXED COMPONENT';
                }
                $recommendations[] = 'Evaluasi hiperventilasi, nyeri akut, cemas, emboli paru, sepsis awal, atau toksisitas salisilat.';
            } else {
                $primaryDisorder = 'METABOLIC ALKALOSIS';
                // Expected pCO2 = 0.7 * (HCO3 - 24) + 40 (+/- 2)
                $expectedPco2 = round(40.0 + (0.7 * ($hco3 - 24.0)), 1);
                $expectedComp = [
                    'rule' => 'Metabolic Alkalosis Respiratory Compensation',
                    'expected_pco2_target' => $expectedPco2,
                ];

                if (abs($pCO2 - $expectedPco2) <= 2.0) {
                    $compensationStatus = 'METABOLIC ALKALOSIS WITH APPROPRIATE RESPIRATORY COMPENSATION';
                } else {
                    $compensationStatus = 'METABOLIC ALKALOSIS WITH SECONDARY DISORDER';
                }
                $recommendations[] = 'Evaluasi kehilangan asam lambung (muntah/NGT suction), diuretik, hipokalemia, atau kelebihan mineralokortikoid.';
            }
        }

        // 3. Delta Ratio untuk HAGMA
        $deltaRatio = null;
        $deltaInterpretation = null;
        if ($anionGapCategory === 'ELEVATED (HAGMA)' && $hco3 < 24.0) {
            $deltaAg = $correctedAnionGap - 12.0;
            $deltaHco3 = 24.0 - $hco3;
            if ($deltaHco3 > 0.0) {
                $deltaRatio = round($deltaAg / $deltaHco3, 2);

                if ($deltaRatio < 0.4) {
                    $deltaInterpretation = 'Pure Normal Anion Gap Metabolic Acidosis (NAGMA predominant)';
                } elseif ($deltaRatio >= 0.4 && $deltaRatio <= 0.8) {
                    $deltaInterpretation = 'Mixed HAGMA and NAGMA (e.g., DKA with severe diarrhea or saline resuscitation)';
                } elseif ($deltaRatio > 0.8 && $deltaRatio <= 2.0) {
                    $deltaInterpretation = 'Pure High Anion Gap Metabolic Acidosis (HAGMA)';
                } else {
                    $deltaInterpretation = 'Mixed HAGMA and pre-existing Metabolic Alkalosis (e.g., vomiting with DKA)';
                }
            }
        }

        // 4. Oksigenasi & Indeks Horowitz (PaO2/FiO2)
        $pao2Fio2Ratio = round($pO2 / $fiO2, 1);
        $oxygenationStatus = 'NORMAL_OXYGENATION';

        if ($pao2Fio2Ratio <= 100.0) {
            $oxygenationStatus = 'SEVERE_ARDS';
            $recommendations[] = 'KRITIS: Rasio P/F <= 100 menunjukkan Severe ARDS. Pertimbangkan ventilasi protektif, prone positioning, atau ECMO.';
        } elseif ($pao2Fio2Ratio <= 200.0) {
            $oxygenationStatus = 'MODERATE_ARDS';
            $recommendations[] = 'WASPADA: Rasio P/F 101-200 menunjukkan Moderate ARDS. Optimalisasi PEEP dan ventilator mechanics.';
        } elseif ($pao2Fio2Ratio <= 300.0) {
            $oxygenationStatus = 'MILD_ARDS';
            $recommendations[] = 'Rasio P/F 201-300 menunjukkan Mild ARDS / Hipoksemia signifikan.';
        } elseif ($pO2 < 80.0) {
            $oxygenationStatus = 'MILD_HYPOXEMIA';
        }

        // 5. A-a Gradient
        // PAO2 = FiO2 * (Patm - 47) - (pCO2 / 0.8)
        $waterVaporPressure = 47.0;
        $respiratoryQuotient = 0.8;
        $pAO2 = ($fiO2 * ($atmosphericPressure - $waterVaporPressure)) - ($pCO2 / $respiratoryQuotient);
        $aaGradient = round(max(0.0, $pAO2 - $pO2), 2);

        // Perkiraan normal A-a gradient berdasarkan usia (asumsi dewasa 40 th): Age/4 + 4 = 14
        $shuntsOrDiffusion = $aaGradient > 20.0;
        if ($shuntsOrDiffusion) {
            $recommendations[] = "A-a gradient meningkat ({$aaGradient} mmHg), mengindikasikan V/Q mismatch, right-to-left shunt, atau gangguan difusi alveolar.";
        }

        $summary = sprintf(
            'Interpretasi Asam-Basa: %s (%s). Anion Gap: %.1f mEq/L (%s). Oksigenasi: P/F Rasio %.1f (%s).',
            $primaryDisorder,
            $compensationStatus,
            $correctedAnionGap,
            $anionGapCategory,
            $pao2Fio2Ratio,
            $oxygenationStatus
        );

        return [
            'primary_disorder' => $primaryDisorder,
            'compensation_status' => $compensationStatus,
            'anion_gap' => $anionGap,
            'corrected_anion_gap' => $correctedAnionGap,
            'anion_gap_category' => $anionGapCategory,
            'delta_ratio' => $deltaRatio,
            'delta_interpretation' => $deltaInterpretation,
            'expected_compensation' => $expectedComp,
            'pao2_fio2_ratio' => $pao2Fio2Ratio,
            'oxygenation_status' => $oxygenationStatus,
            'a_a_gradient' => $aaGradient,
            'shunts_or_diffusion_impairment' => $shuntsOrDiffusion,
            'clinical_summary' => $summary,
            'actionable_recommendations' => $recommendations,
        ];
    }
}
