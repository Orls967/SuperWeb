<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use InvalidArgumentException;

/**
 * Layanan Skrining Keamanan & Pencegahan Toksisitas Zat Kontras Radiologi (Fase 105).
 *
 * Menerapkan pedoman ACR (American College of Radiology) Manual on Contrast Media & ESUR:
 * 1. Estimasi Laju Filtrasi Glomerulus (eGFR) CKD-EPI 2021 (tanpa variabel ras):
 *    eGFR = 142 * min(Scr/kappa, 1)^alpha * max(Scr/kappa, 1)^(-1.200) * 0.9938^Age * (1.012 if female)
 * 2. Penilaian Risiko Post-Contrast Acute Kidney Injury (PC-AKI / CI-AKI):
 *    - eGFR >= 45 mL/min/1.73m2 : Risiko minimal, aman tanpa profilaksis hidrasi khusus
 *    - 30 <= eGFR < 45 : Risiko sedang, indikasi hidrasi salin isotonik IV pra & pasca kontras
 *    - eGFR < 30 / Dialisis : Risiko tinggi CI-AKI, pertimbangkan modalitas alternatif tanpa kontras
 * 3. Skrining Nefrogenik Fibrosis Sistemik (NSF) untuk Agen Kontras Berbasis Gadolinium (GBCA):
 *    - Kontraindikasi mutlak agen GBCA Grup I (Omniscan, Magnevist) pada eGFR < 30 mL/min/1.73m2.
 * 4. Manajemen Pasien Mengonsumsi Metformin:
 *    - Tahan metformin 48 jam pasca kontras jika eGFR < 45 untuk mencegah asidosis laktat.
 * 5. Skrining Riwayat Reaksi Hipersensitivitas Akut & Protokol Premedikasi Kortikosteroid.
 */
class RadiologyContrastMediaSafetyScreeningService
{
    /**
     * Hitung perkiraan Laju Filtrasi Glomerulus (eGFR) menggunakan rumus CKD-EPI 2021.
     *
     * @param  float  $serumCreatinine  Kadar kreatinin serum (mg/dL)
     * @param  int  $ageUsia  Usia pasien dalam tahun (minimal 18 tahun)
     * @param  string  $gender  Jenis kelamin ('MALE' atau 'FEMALE')
     */
    public function calculateEgfrCkdEpi2021(float $serumCreatinine, int $ageUsia, string $gender): float
    {
        if ($serumCreatinine <= 0.0) {
            throw new InvalidArgumentException('Kadar kreatinin serum harus lebih besar dari 0.');
        }

        if ($ageUsia < 18) {
            throw new InvalidArgumentException('Rumus CKD-EPI 2021 diperuntukkan bagi pasien dewasa (>= 18 tahun).');
        }

        $isFemale = strtoupper(trim($gender)) === 'FEMALE';
        $kappa = $isFemale ? 0.7 : 0.9;
        $alpha = $isFemale ? -0.241 : -0.302;
        $genderMultiplier = $isFemale ? 1.012 : 1.0;

        $scrDivKappa = $serumCreatinine / $kappa;
        $minTerm = pow(min($scrDivKappa, 1.0), $alpha);
        $maxTerm = pow(max($scrDivKappa, 1.0), -1.200);
        $ageTerm = pow(0.9938, $ageUsia);

        $egfr = 142.0 * $minTerm * $maxTerm * $ageTerm * $genderMultiplier;

        return round($egfr, 1);
    }

    /**
     * Evaluasi kelayakan pemberian zat kontras iodinasi (CT Scan).
     *
     * @param  float  $egfr  Nilai eGFR pasien (mL/min/1.73m2)
     * @param  bool  $isTakingMetformin  Apakah pasien mengonsumsi metformin
     * @param  bool  $hasPriorAllergy  Apakah ada riwayat reaksi alergi kontras sebelumnya
     * @param  bool  $isDehydrated  Apakah pasien mengalami dehidrasi klinis
     * @return array{
     *     egfr: float,
     *     pc_aki_risk_level: string,
     *     is_contrast_cleared: bool,
     *     hydration_protocol_required: bool,
     *     metformin_action: string,
     *     premedication_required: bool,
     *     screening_decision: string,
     *     instructions: list<string>
     * }
     */
    public function screenIodinatedContrastSafety(
        float $egfr,
        bool $isTakingMetformin,
        bool $hasPriorAllergy,
        bool $isDehydrated = false
    ): array {
        $instructions = [];
        $premedication = false;
        $hydrationRequired = false;

        // Evaluasi Risiko Renal PC-AKI
        if ($egfr >= 45.0 && ! $isDehydrated) {
            $riskLevel = 'LOW';
            $isCleared = true;
            $instructions[] = 'Fungsi ginjal adekuat. Pasien dianjurkan minum air putih yang cukup pasca pemeriksaan.';
        } elseif ($egfr >= 30.0 || $isDehydrated) {
            $riskLevel = 'MODERATE';
            $isCleared = true;
            $hydrationRequired = true;
            $instructions[] = 'RISIKO SEDANG: Wajib protokol hidrasi IV NaCl 0.9% 100 mL/jam selama 4 jam sebelum dan 4 jam sesudah penyuntikan kontras.';
        } else {
            $riskLevel = 'HIGH';
            $isCleared = false;
            $instructions[] = 'KONTRAINDIKASI RELATIF: eGFR < 30 mL/min/1.73m2. Diskusikan dengan dokter pemohon untuk alternatif modalitas non-kontras (USG/MRI polos) atau hidrasi intensif nephrology-guided.';
        }

        // Metformin Management
        $metforminAction = 'NO_ACTION_REQUIRED';
        if ($isTakingMetformin) {
            if ($egfr < 45.0) {
                $metforminAction = 'HOLD_48H_POST_PROCEDURE';
                $instructions[] = 'Hentikan konsumsi Metformin saat pemeriksaan dan tahan selama 48 jam hingga konfirmasi fungsi ginjal stabil.';
            } else {
                $metforminAction = 'CONTINUE_MONITOR';
                $instructions[] = 'Metformin dapat dilanjutkan dengan pemantauan asupan cairan adekuat.';
            }
        }

        // Alergi & Premedikasi
        if ($hasPriorAllergy) {
            $premedication = true;
            $instructions[] = 'Riwayat reaksi kontras: Berikan protokol premedikasi kortikosteroid oral (Prednison 50 mg PO pada 13, 7, dan 1 jam sebelum kontras) ditambah Diphenhydramine 50 mg IV/PO.';
        }

        $decision = $isCleared ? 'APPROVED_WITH_CONDITIONS' : 'HOLD_NEPHROLOGY_CONSULT';
        if ($isCleared && ! $hydrationRequired && ! $premedication && $metforminAction === 'NO_ACTION_REQUIRED') {
            $decision = 'UNCONDITIONAL_CLEARANCE';
        }

        return [
            'egfr' => $egfr,
            'pc_aki_risk_level' => $riskLevel,
            'is_contrast_cleared' => $isCleared,
            'hydration_protocol_required' => $hydrationRequired,
            'metformin_action' => $metforminAction,
            'premedication_required' => $premedication,
            'screening_decision' => $decision,
            'instructions' => $instructions,
        ];
    }

    /**
     * Skrining keamanan agen kontras berbasis gadolinium (GBCA) untuk pemeriksaan MRI.
     *
     * @param  float  $egfr  Nilai eGFR
     * @param  string  $gbcaGroup  Kelompok agen gadolinium ('GROUP_1', 'GROUP_2', 'GROUP_3')
     * @return array{
     *     gbca_group: string,
     *     nsf_risk: string,
     *     is_permitted: bool,
     *     clinical_recommendation: string
     * }
     */
    public function screenGadoliniumNsfRisk(float $egfr, string $gbcaGroup = 'GROUP_2'): array
    {
        $group = strtoupper(trim($gbcaGroup));

        if ($group === 'GROUP_1') {
            // Agen linear non-ionik/ionik (Magnevist, Omniscan, Optimark)
            if ($egfr < 30.0) {
                return [
                    'gbca_group' => $group,
                    'nsf_risk' => 'VERY_HIGH_NEPHROGENIC_SYSTEMIC_FIBROSIS',
                    'is_permitted' => false,
                    'clinical_recommendation' => 'KONTRAINDIKASI MUTLAK: Agen Gadolinium Grup I dilarang keras diberikan pada eGFR < 30 atau pasien dialisis karena risiko tinggi fatal NSF.',
                ];
            }

            return [
                'gbca_group' => $group,
                'nsf_risk' => 'LOW',
                'is_permitted' => true,
                'clinical_recommendation' => 'Dapat diberikan dengan kehati-hatian; lebih disarankan beralih ke agen makrosiklik Grup II.',
            ];
        }

        // Agen Makrosiklik Grup II (Dotarem, Gadovist, ProHance)
        if ($egfr < 30.0) {
            return [
                'gbca_group' => $group,
                'nsf_risk' => 'MINIMAL_LOW',
                'is_permitted' => true,
                'clinical_recommendation' => 'Agen makrosiklik Grup II memiliki stabilitas khelat sangat tinggi; risiko NSF sangat rendah jika memang indikasi klinis MRI kontras mendesak.',
            ];
        }

        return [
            'gbca_group' => $group,
            'nsf_risk' => 'EXTREMELY_LOW',
            'is_permitted' => true,
            'clinical_recommendation' => 'Aman untuk diberikan sesuai dosis standar diagnostik (0.1 mmol/kg).',
        ];
    }
}
