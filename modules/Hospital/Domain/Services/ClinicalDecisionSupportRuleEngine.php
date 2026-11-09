<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\AllergyMatrix;
use Modules\Hospital\Domain\Models\DrugInteractionRule;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\ValueObjects\MedicineDosageRegimen;
use Modules\Hospital\Domain\ValueObjects\PrescriptionSafetyScore;

/**
 * Clinical Decision Support Rule Engine (CDS-RE) untuk Evaluasi Farmakologi Medis.
 * Memeriksa:
 * 1. Alergi obat silang terhadap rekam medis / human passport pasien (Fase 87.2).
 * 2. Penyesuaian klirens ginjal (eGFR) berdasarkan formula Cockcroft-Gault.
 * 3. Matriks interaksi antarobat (Drug-Drug Interactions / DDI).
 * 4. Peringatan kelebihan dosis maksimal harian.
 */
class ClinicalDecisionSupportRuleEngine
{
    /**
     * Mengevaluasi keselamatan resep komprehensif.
     *
     * @param  array<int, MedicineDosageRegimen>  $regimens
     */
    public function evaluateSafety(
        Patient $patient,
        array $regimens,
        ?float $serumCreatinineMgDl = null,
        ?float $patientWeightKg = 70.0
    ): PrescriptionSafetyScore {
        $contraindications = [];
        $warnings = [];
        $score = 100;
        $requiresDualApproval = false;

        // 1. Evaluasi Alergi Pasien (Cross-Allergy Evaluation)
        $allergyResults = $this->evaluatePatientAllergies($patient, $regimens);
        if (! empty($allergyResults['contraindications'])) {
            $contraindications = array_merge($contraindications, $allergyResults['contraindications']);
            $score -= 60;
        }
        if (! empty($allergyResults['warnings'])) {
            $warnings = array_merge($warnings, $allergyResults['warnings']);
            $score -= 20;
        }

        // 2. Evaluasi Klirens Ginjal (Renal Clearance & Nephrotoxicity)
        if ($serumCreatinineMgDl !== null && $serumCreatinineMgDl > 0) {
            $renalResults = $this->evaluateRenalClearance(
                $patient,
                $regimens,
                $serumCreatinineMgDl,
                $patientWeightKg ?? 70.0
            );
            if (! empty($renalResults['contraindications'])) {
                $contraindications = array_merge($contraindications, $renalResults['contraindications']);
                $score -= 50;
            }
            if (! empty($renalResults['warnings'])) {
                $warnings = array_merge($warnings, $renalResults['warnings']);
                $score -= 15;
            }
        }

        // 3. Evaluasi Interaksi Antarobat (Drug-Drug Interactions / DDI)
        $ddiResults = $this->evaluateDrugInteractions($regimens);
        if (! empty($ddiResults['contraindications'])) {
            $contraindications = array_merge($contraindications, $ddiResults['contraindications']);
            $score -= 50;
        }
        if (! empty($ddiResults['warnings'])) {
            $warnings = array_merge($warnings, $ddiResults['warnings']);
            $score -= 15;
        }

        // 4. Evaluasi Overdosis Maksimal Harian
        foreach ($regimens as $regimen) {
            if ($regimen->isMaxDailyDoseExceeded()) {
                $msg = sprintf(
                    'Kelebihan dosis maksimal untuk %s: Dosis %.1f mg/hari melebihi batas aman %.1f mg/hari (Rasio: %.2fx).',
                    $regimen->drugName,
                    $regimen->totalDailyDoseMg(),
                    $regimen->maxSafeDailyDoseMg,
                    $regimen->excessRatio()
                );
                $warnings[] = $msg;
                $score -= 25;
                $requiresDualApproval = true;
            }
        }

        $finalScore = max(0, min(100, $score));

        if (! empty($contraindications)) {
            return new PrescriptionSafetyScore(
                scorePoints: $finalScore,
                safetyLevel: PrescriptionSafetyScore::LEVEL_BLOCKED,
                isDispensable: false,
                requiresDualDoctorApproval: true,
                contraindications: array_unique($contraindications),
                warnings: array_unique($warnings)
            );
        }

        if (! empty($warnings) || $requiresDualApproval || $finalScore < 80) {
            return new PrescriptionSafetyScore(
                scorePoints: $finalScore,
                safetyLevel: PrescriptionSafetyScore::LEVEL_CAUTION,
                isDispensable: true,
                requiresDualDoctorApproval: true,
                contraindications: [],
                warnings: array_unique($warnings)
            );
        }

        return PrescriptionSafetyScore::safe();
    }

    /**
     * Memeriksa alergi obat pasien terhadap zat aktif resep.
     *
     * @param  array<int, MedicineDosageRegimen>  $regimens
     * @return array{contraindications: array<int, string>, warnings: array<int, string>}
     */
    public function evaluatePatientAllergies(Patient $patient, array $regimens): array
    {
        $contraindications = [];
        $warnings = [];

        // Ambil alergi dari database AllergyMatrix atau model Patient
        $knownAllergies = AllergyMatrix::where('patient_id', $patient->id)->get();
        $patientAllergens = $knownAllergies->pluck('allergen_code')->map(fn ($c) => strtoupper((string) $c))->all();

        // Cross-allergy classes mapping
        $allergyCrossClasses = [
            'PENICILLIN' => ['AMOXICILLIN', 'AMPICILLIN', 'PENICILLIN_G', 'AMOXICILLIN_CLAVULANATE'],
            'SULFONAMIDE' => ['SULFAMETHOXAZOLE', 'COTRIMOXAZOLE', 'SULFASALAZINE'],
            'CEPHALOSPORIN' => ['CEFALEXIN', 'CEFTRIAXONE', 'CEFIXIME', 'CEFOTAXIME'],
            'NSAID' => ['ASPIRIN', 'IBUPROFEN', 'KETOPROFEN', 'DICLOFENAC', 'MEFENAMIC_ACID'],
            'OPIOID' => ['MORPHINE', 'FENTANYL', 'CODEINE', 'TRAMADOL'],
        ];

        foreach ($regimens as $regimen) {
            $drugCodeUpper = strtoupper($regimen->drugCode);

            // Cek alergi langsung
            if (in_array($drugCodeUpper, $patientAllergens, true)) {
                $contraindications[] = "Pasien memiliki riwayat alergi langsung terdaftar terhadap {$regimen->drugName} ({$regimen->drugCode}).";

                continue;
            }

            // Cek alergi silang golongan (misal alergi Penicillin tapi diresepkan Amoxicillin)
            foreach ($allergyCrossClasses as $allergenClass => $drugsInClass) {
                if (in_array($allergenClass, $patientAllergens, true) && in_array($drugCodeUpper, $drugsInClass, true)) {
                    $contraindications[] = "Alergi silang terdeteksi: Pasien alergi kelas {$allergenClass}, dilarang meresepkan {$regimen->drugName}.";
                    break;
                }
            }
        }

        return [
            'contraindications' => $contraindications,
            'warnings' => $warnings,
        ];
    }

    /**
     * Menghitung Estimated Glomerular Filtration Rate (eGFR) menggunakan formula Cockcroft-Gault.
     * eGFR = [(140 - Umur) * Berat Badan (kg)] / [72 * Serum Kreatinin (mg/dL)] (* 0.85 untuk wanita).
     */
    public function calculateCockcroftGaultEgfr(
        int $ageYears,
        float $weightKg,
        float $serumCreatinineMgDl,
        bool $isFemale = false
    ): float {
        if ($serumCreatinineMgDl <= 0 || $ageYears <= 0 || $weightKg <= 0) {
            return 90.0; // Nilai default normal
        }

        $clearance = ((140 - $ageYears) * $weightKg) / (72 * $serumCreatinineMgDl);

        if ($isFemale) {
            $clearance *= 0.85;
        }

        return round(max(5.0, $clearance), 2);
    }

    /**
     * Memeriksa keselamatan obat nefrotoksik terhadap klirens ginjal.
     */
    public function evaluateRenalClearance(
        Patient $patient,
        array $regimens,
        float $serumCreatinineMgDl,
        float $weightKg
    ): array {
        $contraindications = [];
        $warnings = [];

        // Hitung umur pasien
        $birthDate = $patient->date_of_birth ?? now()->subYears(45);
        $ageYears = max(18, (int) now()->diffInYears($birthDate));
        $isFemale = strtoupper((string) ($patient->gender ?? 'MALE')) === 'FEMALE';

        $egfr = $this->calculateCockcroftGaultEgfr($ageYears, $weightKg, $serumCreatinineMgDl, $isFemale);

        // Daftar obat nefrotoksik tinggi yang dilarang pada GFR < 30 mL/min
        $highRiskNephrotoxic = [
            'GENTAMICIN' => 30.0,
            'AMIKACIN' => 30.0,
            'METFORMIN' => 30.0, // Risiko lactic acidosis berat pada eGFR < 30
            'VANCOMYCIN' => 20.0,
            'CISPLATIN' => 45.0,
            'METHOTREXATE' => 30.0,
        ];

        // Daftar obat yang butuh penyesuaian dosis pada GFR 30-60 mL/min
        $moderateRenalAdjustment = [
            'CIPROFLOXACIN' => 50.0,
            'ALLOPURINOL' => 50.0,
            'RANITIDINE' => 50.0,
            'ENALAPRIL' => 30.0,
        ];

        foreach ($regimens as $regimen) {
            $code = strtoupper($regimen->drugCode);

            if (isset($highRiskNephrotoxic[$code]) && $egfr < $highRiskNephrotoxic[$code]) {
                $contraindications[] = sprintf(
                    'Kontraindikasi Ginjal Berat: %s dilarang pada eGFR < %.0f mL/min (eGFR pasien saat ini: %.1f mL/min).',
                    $regimen->drugName,
                    $highRiskNephrotoxic[$code],
                    $egfr
                );
            } elseif (isset($moderateRenalAdjustment[$code]) && $egfr < $moderateRenalAdjustment[$code]) {
                $warnings[] = sprintf(
                    'Peringatan Gangguan Ginjal Sedang: %s membutuhkan penurunan dosis 50%% untuk eGFR %.1f mL/min.',
                    $regimen->drugName,
                    $egfr
                );
            }
        }

        return [
            'contraindications' => $contraindications,
            'warnings' => $warnings,
        ];
    }

    /**
     * Memeriksa interaksi antarobat dari aturan database dan aturan patokan klinis.
     *
     * @param  array<int, MedicineDosageRegimen>  $regimens
     * @return array{contraindications: array<int, string>, warnings: array<int, string>}
     */
    public function evaluateDrugInteractions(array $regimens): array
    {
        $contraindications = [];
        $warnings = [];
        $count = count($regimens);

        if ($count < 2) {
            return ['contraindications' => [], 'warnings' => []];
        }

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $drugA = strtoupper($regimens[$i]->drugCode);
                $drugB = strtoupper($regimens[$j]->drugCode);

                // Periksa tabel aturan hsp_drug_interaction_rules
                $rule = DrugInteractionRule::where('is_active', true)
                    ->where(function ($q) use ($drugA, $drugB): void {
                        $q->where(fn ($sub) => $sub->where('drug_a_code', $drugA)->where('drug_b_code', $drugB))
                            ->orWhere(fn ($sub) => $sub->where('drug_a_code', $drugB)->where('drug_b_code', $drugA));
                    })
                    ->first();

                if ($rule) {
                    $desc = "Interaksi Obat [{$rule->interaction_severity}] antara {$regimens[$i]->drugName} dan {$regimens[$j]->drugName}: {$rule->clinical_mechanism}. Rekomendasi: {$rule->recommendation}";
                    if ($rule->interaction_severity === 'CONTRAINDICATED') {
                        $contraindications[] = $desc;
                    } else {
                        $warnings[] = $desc;
                    }

                    continue;
                }

                // Patokan interaksi mayor umum
                $pairKey = $drugA < $drugB ? "{$drugA}+{$drugB}" : "{$drugB}+{$drugA}";
                $knownMajorInteractions = [
                    'SILDENAFIL+NITROGLYCERIN' => [
                        'type' => 'CONTRAINDICATED',
                        'msg' => 'Kombinasi Sildenafil dan Nitroglycerin menyebabkan hipotensi refrakter fatal (penurunan tensi kolaps).',
                    ],
                    'WARFARIN+ASPIRIN' => [
                        'type' => 'MAJOR',
                        'msg' => 'Kombinasi Warfarin dan Aspirin meningkatkan risiko perdarahan internal mayor gastrointestinal.',
                    ],
                    'SIMVASTATIN+CLARITHROMYCIN' => [
                        'type' => 'CONTRAINDICATED',
                        'msg' => 'Clarithromycin menghambat metabolisme Simvastatin CYP3A4, risiko rhabdomyolysis dan gagal ginjal akut.',
                    ],
                    'DIGOXIN+AMIODARONE' => [
                        'type' => 'MAJOR',
                        'msg' => 'Amiodarone meningkatkan konsentrasi serum Digoxin >70%, risiko aritmia ventrikel toksik.',
                    ],
                    'METHOTREXATE+IBUPROFEN' => [
                        'type' => 'CONTRAINDICATED',
                        'msg' => 'NSAID menghambat klirens tubular ginjal Methotrexate, risiko supresi sumsum tulang fatal.',
                    ],
                ];

                if (isset($knownMajorInteractions[$pairKey])) {
                    $item = $knownMajorInteractions[$pairKey];
                    if ($item['type'] === 'CONTRAINDICATED') {
                        $contraindications[] = $item['msg'];
                    } else {
                        $warnings[] = $item['msg'];
                    }
                }
            }
        }

        return [
            'contraindications' => $contraindications,
            'warnings' => $warnings,
        ];
    }
}
