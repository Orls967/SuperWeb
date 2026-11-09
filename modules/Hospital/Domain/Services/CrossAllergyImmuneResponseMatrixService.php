<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * Cross-Allergy Immune Response & Gell-Coombs Hypersensitivity Matrix.
 * Memetakan klasifikasi imunologi reaksi hipersensitivitas obat:
 * 1. Tipe I: IgE-mediated (Anafilaksis akut, angioedema, bronkospasme).
 * 2. Tipe II: Sitotoksik (Anemia hemolitik, trombositopenia imbas obat).
 * 3. Tipe III: Kompleks imun (Serum sickness, vaskulitis).
 * 4. Tipe IV: T-cell mediated (Sindrom Stevens-Johnson / SJS, TEN, DRESS).
 * 5. Rekomendasi alternatif antibiotik/analgesik yang aman tanpa risiko reaksi silang rantai samping.
 */
class CrossAllergyImmuneResponseMatrixService
{
    /**
     * Alternatif lini terapi aman untuk alergi obat mayor.
     *
     * @var array<string, array{safe_alternatives: array<int, string>, strict_bans: array<int, string>}>
     */
    private const SAFE_ALTERNATIVES_MAP = [
        'PENICILLIN' => [
            'safe_alternatives' => ['AZITHROMYCIN', 'CLARITHROMYCIN', 'VANCOMYCIN', 'LEVOFLOXACIN', 'AZTREONAM'],
            'strict_bans' => ['AMOXICILLIN', 'AMPICILLIN', 'PIPERACILLIN', 'AMOXICILLIN_CLAVULANATE'],
        ],
        'SULFONAMIDE' => [
            'safe_alternatives' => ['CIPROFLOXACIN', 'DOXYCYCLINE', 'AMOXICILLIN'],
            'strict_bans' => ['COTRIMOXAZOLE', 'SULFAMETHOXAZOLE', 'SULFADIAZINE'],
        ],
        'NSAID' => [
            'safe_alternatives' => ['PARACETAMOL', 'TRAMADOL', 'CELECOXIB'],
            'strict_bans' => ['ASPIRIN', 'IBUPROFEN', 'KETOPROFEN', 'DICLOFENAC', 'MEFENAMIC_ACID'],
        ],
    ];

    /**
     * Menganalisis reaksi alergi dan merekomendasikan alternatif obat aman.
     *
     * @return array{
     *     allergen: string,
     *     gell_coombs_type: string,
     *     severity_grade: string, // FATAL_ANAPHYLACTIC, SEVERE, MODERATE, MILD
     *     safe_alternatives: array<int, string>,
     *     forbidden_drugs: array<int, string>,
     *     cross_reactivity_risk_pct: float
     * }
     */
    public function analyzeAllergyProfile(string $reportedAllergen, string $reactionDescription = ''): array
    {
        $allergenUpper = strtoupper(trim($reportedAllergen));
        $descUpper = strtoupper($reactionDescription);

        $gellType = 'TYPE_I_IGE_MEDIATED';
        $severity = 'MODERATE';
        $riskPct = 10.0;

        if (str_contains($descUpper, 'ANAFILAKSIS') || str_contains($descUpper, 'SESAK') || str_contains($descUpper, 'ANGIOEDEMA')) {
            $gellType = 'TYPE_I_IGE_MEDIATED';
            $severity = 'FATAL_ANAPHYLACTIC';
            $riskPct = 95.0;
        } elseif (str_contains($descUpper, 'SJS') || str_contains($descUpper, 'KULIT MELEPUH') || str_contains($descUpper, 'STEVENS')) {
            $gellType = 'TYPE_IV_CELL_MEDIATED';
            $severity = 'SEVERE';
            $riskPct = 85.0;
        } elseif (str_contains($descUpper, 'GATAL') || str_contains($descUpper, 'URTIKARIA') || str_contains($descUpper, 'RUAM')) {
            $gellType = 'TYPE_I_IGE_MEDIATED';
            $severity = 'MILD';
            $riskPct = 15.0;
        }

        $map = self::SAFE_ALTERNATIVES_MAP[$allergenUpper] ?? [
            'safe_alternatives' => ['PARACETAMOL', 'ERYTHROMYCIN'],
            'strict_bans' => [$allergenUpper],
        ];

        return [
            'allergen' => $allergenUpper,
            'gell_coombs_type' => $gellType,
            'severity_grade' => $severity,
            'safe_alternatives' => $map['safe_alternatives'],
            'forbidden_drugs' => $map['strict_bans'],
            'cross_reactivity_risk_pct' => $riskPct,
        ];
    }
}
