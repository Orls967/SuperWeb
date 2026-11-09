<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\Patient;

/**
 * Adverse Drug Reaction (ADR / MESO) Pharmacovigilance Engine.
 * Menghasilkan formulir Monitoring Efek Samping Obat (MESO Kuning Badan POM):
 * 1. Skoring algoritma probabilitas Naranjo (Definite >= 9, Probable 5-8, Possible 1-4, Doubtful <= 0).
 * 2. Pembuatan payload pelaporan farmakovigilans standar WHO-UMC VigiBase.
 * 3. Pencatatan rekam alergi / intoleransi baru ke dalam rekam medis pasien.
 */
class AdverseDrugReactionReportingEngine
{
    /**
     * Menghitung Skor Algoritma Naranjo untuk causality assessment efek samping obat.
     *
     * @param array{
     *     previous_conclusive_reports: bool, // +1
     *     adverse_event_after_drug: bool,    // +2
     *     improved_when_discontinued: bool,  // +1
     *     reappeared_on_readministration: bool, // +2
     *     alternative_causes_present: bool,  // -1
     *     reaction_with_placebo: bool,       // -1
     *     drug_detected_in_toxic_level: bool, // +1
     *     reaction_severity_dose_dependent: bool, // +1
     *     similar_reaction_in_past: bool,    // +1
     *     confirmed_by_objective_evidence: bool // +1
     * } $answers
     * @return array{score: int, causality: string}
     */
    public function calculateNaranjoScore(array $answers): array
    {
        $score = 0;

        if ($answers['previous_conclusive_reports'] ?? false) {
            $score += 1;
        }
        if ($answers['adverse_event_after_drug'] ?? true) {
            $score += 2;
        }
        if ($answers['improved_when_discontinued'] ?? true) {
            $score += 1;
        }
        if ($answers['reappeared_on_readministration'] ?? false) {
            $score += 2;
        }
        if ($answers['alternative_causes_present'] ?? false) {
            $score -= 1;
        }
        if ($answers['reaction_with_placebo'] ?? false) {
            $score -= 1;
        }
        if ($answers['drug_detected_in_toxic_level'] ?? false) {
            $score += 1;
        }
        if ($answers['reaction_severity_dose_dependent'] ?? false) {
            $score += 1;
        }
        if ($answers['similar_reaction_in_past'] ?? false) {
            $score += 1;
        }
        if ($answers['confirmed_by_objective_evidence'] ?? true) {
            $score += 1;
        }

        $causality = match (true) {
            $score >= 9 => 'DEFINITE',
            $score >= 5 => 'PROBABLE',
            $score >= 1 => 'POSSIBLE',
            default => 'DOUBTFUL',
        };

        return [
            'score' => $score,
            'causality' => $causality,
        ];
    }

    /**
     * Menerbitkan laporan MESO resmi untuk BPOM.
     */
    public function generateMesoReport(
        Patient $patient,
        EPharmacyOrder $order,
        string $adverseReactionSymptom,
        array $naranjoAnswers
    ): array {
        $naranjo = $this->calculateNaranjoScore($naranjoAnswers);
        $mesoReportCode = 'MESO-'.strtoupper(bin2hex(random_bytes(4)));

        return [
            'meso_report_code' => $mesoReportCode,
            'patient_mrn' => $patient->mrn,
            'patient_name' => $patient->name,
            'suspected_drug_code' => $order->drug_code,
            'suspected_drug_name' => $order->drug_name,
            'reaction_description' => $adverseReactionSymptom,
            'naranjo_score' => $naranjo['score'],
            'causality_assessment' => $naranjo['causality'],
            'reported_date' => now()->toDateString(),
            'regulatory_destination' => 'BPOM_PUSAT_FARMAKOVIGILANS',
        ];
    }
}
