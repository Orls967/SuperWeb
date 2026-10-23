<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Domain\Models\AllergyMatrix;
use Modules\Hospital\Domain\Models\Patient;

class PrescriptionSafetyEvaluator
{
    /**
     * Known cross-allergy mapping
     *
     * @var array<string, array<int, string>>
     */
    private const CROSS_ALLERGIES = [
        'PENICILLIN' => ['AMOXICILLIN', 'AMPICILLIN', 'PIPERACILLIN', 'PENICILLIN_V'],
        'SULFONAMIDE' => ['SULFAMETHOXAZOLE', 'COTRIMOXAZOLE', 'SULFASALAZINE'],
        'NSAIDS' => ['ASPIRIN', 'IBUPROFEN', 'KETOPROFEN', 'DICLOFENAC'],
    ];

    /**
     * Drugs with strict renal dose contraindication (CrCl < 30 mL/min)
     *
     * @var array<int, string>
     */
    private const RENAL_RESTRICTED_DRUGS = [
        'METFORMIN',
        'GENTAMICIN',
        'VANCOMYCIN',
        'METHOTREXATE',
    ];

    /**
     * Perform comprehensive multi-factor safety evaluation
     *
     * @return array{
     *     is_safe: bool,
     *     risk_level: string,
     *     reasons: array<int, string>
     * }
     */
    public function evaluateSafety(
        Patient $patient,
        string $drugCode,
        int $dosageMg,
        ?int $creatinineClearanceMlLMin = null
    ): array {
        $upperDrug = strtoupper(trim($drugCode));
        $reasons = [];

        // 1. Direct allergy check from patient passport
        $allergies = [];
        if ($patient->encrypted_allergies) {
            $raw = is_array($patient->encrypted_allergies)
                ? $patient->encrypted_allergies
                : json_decode($patient->encrypted_allergies, true);
            if (is_array($raw)) {
                $allergies = array_map('strtoupper', $raw);
            }
        }

        // Add allergies from database matrix
        $dbAllergies = AllergyMatrix::where('patient_id', $patient->id)->pluck('allergen_code')->toArray();
        $allergies = array_unique(array_merge($allergies, array_map('strtoupper', $dbAllergies)));

        if (in_array($upperDrug, $allergies, true)) {
            $reasons[] = "Direct anaphylactic/allergic contraindication: Patient is documented allergic to {$upperDrug}.";
        }

        // 2. Cross-allergy class evaluation
        foreach (self::CROSS_ALLERGIES as $allergenClass => $derivatives) {
            if (in_array($allergenClass, $allergies, true) && in_array($upperDrug, $derivatives, true)) {
                $reasons[] = "Cross-reactivity alert: Patient is allergic to {$allergenClass}, which cross-reacts with {$upperDrug}.";
            }
        }

        // 3. Renal clearance clearance evaluation (eGFR / CrCl)
        if ($creatinineClearanceMlLMin !== null && $creatinineClearanceMlLMin < 30) {
            if (in_array($upperDrug, self::RENAL_RESTRICTED_DRUGS, true)) {
                $reasons[] = "Renal impairment contraindication: CrCl is {$creatinineClearanceMlLMin} mL/min (< 30 mL/min threshold for {$upperDrug}).";
            }
        }

        $isSafe = empty($reasons);
        $riskLevel = $isSafe ? 'LOW_RISK' : 'CRITICAL_CONTRAINDICATION';

        return [
            'is_safe' => $isSafe,
            'risk_level' => $riskLevel,
            'reasons' => $reasons,
        ];
    }
}
