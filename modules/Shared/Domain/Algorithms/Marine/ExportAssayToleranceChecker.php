<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

/**
 * A19: ExportAssayToleranceChecker.
 *
 * Memverifikasi kesesuaian hasil uji laboratorium residu antibiotik terlarang,
 * histamin, mikrobiologi, dan logam berat terhadap ambang regulasi negara tujuan
 * (US FDA, EU RASFF, Japan MHLW, China GACC).
 */
class ExportAssayToleranceChecker
{
    // Standar MRL (Maximum Residue Limits) per regulasi internasional
    public const MRL_RULES = [
        'EU' => [
            'chloramphenicol_ppb' => 0.3,    // Limit of Quantification (LOQ)
            'nitrofuran_aoz_ppb' => 0.5,
            'nitrofuran_amoz_ppb' => 0.5,
            'nitrofuran_sem_ppb' => 0.5,
            'tetracycline_ppb' => 100.0,
            'histamine_ppm' => 100.0,
            'cadmium_ppm' => 0.50,
            'mercury_ppm' => 0.50,
            'lead_ppm' => 0.30,
        ],
        'US_FDA' => [
            'chloramphenicol_ppb' => 0.3,
            'nitrofuran_aoz_ppb' => 0.5,
            'nitrofuran_amoz_ppb' => 0.5,
            'nitrofuran_sem_ppb' => 0.5,
            'tetracycline_ppb' => 200.0,
            'histamine_ppm' => 50.0,   // Tindakan FDA pada level 50 ppm
            'cadmium_ppm' => 1.0,
            'mercury_ppm' => 1.0,
            'lead_ppm' => 0.50,
        ],
        'JAPAN' => [
            'chloramphenicol_ppb' => 0.3,
            'nitrofuran_aoz_ppb' => 0.5,
            'nitrofuran_amoz_ppb' => 0.5,
            'nitrofuran_sem_ppb' => 0.5,
            'tetracycline_ppb' => 100.0,
            'histamine_ppm' => 100.0,
            'cadmium_ppm' => 0.50,
            'mercury_ppm' => 0.40,
            'lead_ppm' => 0.30,
        ],
    ];

    /**
     * Periksa hasil assay laboratorium terhadap standar regulasi pasar tujuan.
     *
     * @param  string  $destinationMarket  'EU'|'US_FDA'|'JAPAN'
     * @param array{
     *     chloramphenicol_ppb?: float,
     *     nitrofuran_aoz_ppb?: float,
     *     nitrofuran_amoz_ppb?: float,
     *     nitrofuran_sem_ppb?: float,
     *     tetracycline_ppb?: float,
     *     histamine_ppm?: float,
     *     cadmium_ppm?: float,
     *     mercury_ppm?: float,
     *     lead_ppm?: float,
     *     salmonella_present?: bool,
     *     vibrio_cholerae_present?: bool
     * } $assayResults
     * @return array{
     *     is_eligible_for_export: bool,
     *     destination_market: string,
     *     violations: array<string>,
     *     warnings: array<string>,
     *     health_certificate_grade: string,
     *     quarantine_release_status: string
     * }
     */
    public function evaluateAssay(string $destinationMarket, array $assayResults): array
    {
        $marketKey = strtoupper($destinationMarket);
        $rules = self::MRL_RULES[$marketKey] ?? self::MRL_RULES['EU'];

        $violations = [];
        $warnings = [];

        // 1. Mikrobiologi mutlak (Zero tolerance Salmonella & Vibrio cholerae)
        if (! empty($assayResults['salmonella_present'])) {
            $violations[] = 'MICRO_FAIL: Positif cemaran Salmonella sp. (toleransi 0 dalam 25g).';
        }

        if (! empty($assayResults['vibrio_cholerae_present'])) {
            $violations[] = 'MICRO_FAIL: Positif patogen Vibrio cholerae.';
        }

        // 2. Evaluasi parameter kimiawi & residu obat
        foreach ($rules as $parameterKey => $maxLimit) {
            if (! isset($assayResults[$parameterKey])) {
                continue;
            }

            $testedValue = (float) $assayResults[$parameterKey];
            if ($testedValue > $maxLimit) {
                $violations[] = sprintf(
                    'RESIDUE_FAIL: Parameter %s (%.2f) melebihi batas regulasi %s (%.2f).',
                    $parameterKey,
                    $testedValue,
                    $marketKey,
                    $maxLimit
                );
            } elseif ($testedValue > ($maxLimit * 0.80)) {
                $warnings[] = sprintf(
                    'RESIDUE_WARN: Parameter %s (%.2f) mendekati batas toleransi (%.2f).',
                    $parameterKey,
                    $testedValue,
                    $maxLimit
                );
            }
        }

        $isEligible = empty($violations);
        $grade = $isEligible ? (empty($warnings) ? 'GRADE_A_PRIME' : 'GRADE_B_COMPLIANT') : 'REJECTED';
        $status = $isEligible ? 'SPS_EXPORT_PERMIT_APPROVED' : 'QUARANTINE_DETAINED_DESTRUCTION_REQUIRED';

        return [
            'is_eligible_for_export' => $isEligible,
            'destination_market' => $marketKey,
            'violations' => $violations,
            'warnings' => $warnings,
            'health_certificate_grade' => $grade,
            'quarantine_release_status' => $status,
        ];
    }
}
