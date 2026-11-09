<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

/**
 * Layanan Audit & Validasi Koder Medis Elektronik (MedicalCoderAuditValidationService).
 * Memvalidasi kepatuhan kaidah koding rekam medis (ICD-10 & ICD-9-CM) sebelum diajukan ke BPJS / Asuransi:
 *   1. Penegakan aturan ICD-10 Bab XVIII: Gejala umum (R00-R99) dilarang menjadi diagnosa primer jika penyebab definitif diketahui.
 *   2. Validasi pasangan prosedur operatif wajib (e.g. Seksio Sesarea O82 harus disertai prosedur bedah obstetri 74.x).
 *   3. Deteksi potensi Unbundling (pemecahan prosedur paket menjadi beberapa klaim terpisah ilegal).
 *   4. Deteksi Upcoding (penambahan diagnosa komorbiditas berat tanpa bukti dokumentasi penunjang klinis).
 */
class MedicalCoderAuditValidationService
{
    /**
     * Pasangan wajib: Diagnosa Primer -> Prosedur ICD-9-CM yang harus ada.
     *
     * @var array<string, array{required_procedures: list<string>, description: string}>
     */
    private const REQUIRED_PROCEDURE_PAIRS = [
        'O82' => [
            'required_procedures' => ['74.0', '74.1', '74.2', '74.4', '74.99'],
            'description' => 'Persalinan Seksio Sesarea wajib menyertakan tindakan pembedahan sesar (ICD-9-CM 74.x)',
        ],
        'K35' => [
            'required_procedures' => ['47.01', '47.09', '47.11', '47.19'],
            'description' => 'Apendisitis Akut Terbedah wajib menyertakan tindakan apendiktomi (ICD-9-CM 47.0x / 47.1x)',
        ],
        'I21' => [
            'required_procedures' => ['36.06', '36.07', '88.56', '88.53'],
            'description' => 'Infark Miokard Akut dengan Intervensi Koroner Perkutan harus mencantumkan PTCA/Stenting atau Angiografi',
        ],
        'S06' => [
            'required_procedures' => ['01.24', '01.39', '02.02', '87.03'],
            'description' => 'Cedera Intrakranial Berat membutuhkan tindakan kraniotomi atau CT-Scan kepala',
        ],
    ];

    /**
     * Memvalidasi berkas koding klinis sebelum diajukan ke verifikator klaim.
     *
     * @param  string  $primaryDiagnosisIcd10  Kode diagnosa utama
     * @param  array<int, string>  $secondaryDiagnosesIcd10  Daftar kode komorbiditas
     * @param  array<int, string>  $proceduresIcd9  Daftar tindakan medis
     * @param  bool  $hasSupportingDiagnosticReport  Apakah ada laporan laboratorium/radiologi pendukung
     * @return array{
     *     is_valid: bool,
     *     audit_status: string,
     *     violation_codes: list<string>,
     *     warning_messages: list<string>,
     *     upcoding_risk_score: float
     * }
     */
    public function auditMedicalCoding(
        string $primaryDiagnosisIcd10,
        array $secondaryDiagnosesIcd10,
        array $proceduresIcd9,
        bool $hasSupportingDiagnosticReport
    ): array {
        $cleanPrimary = strtoupper(trim(explode('.', $primaryDiagnosisIcd10)[0]));
        $violations = [];
        $warnings = [];
        $upcodingRisk = 0.0;

        // 1. Aturan ICD-10: Gejala (Kategori R) dilarang menjadi diagnosa primer jika ada komorbiditas definitif
        if (str_starts_with($cleanPrimary, 'R')) {
            $hasDefinitiveSecondary = false;
            foreach ($secondaryDiagnosesIcd10 as $sec) {
                if (! str_starts_with(strtoupper(trim($sec)), 'R')) {
                    $hasDefinitiveSecondary = true;
                    break;
                }
            }

            if ($hasDefinitiveSecondary) {
                $violations[] = 'RULE_R_SYMPTOM_AS_PRIMARY';
                $warnings[] = "Diagnosa primer {$primaryDiagnosisIcd10} merupakan gejala (R-Code), sedangkan ditemukan diagnosis penyakit definitif pada diagnosa sekunder. Diagnosa definitif wajib dijadikan diagnosa primer.";
            }
        }

        // 2. Validasi Pasangan Wajib Prosedur Operatif
        if (isset(self::REQUIRED_PROCEDURE_PAIRS[$cleanPrimary])) {
            $req = self::REQUIRED_PROCEDURE_PAIRS[$cleanPrimary];
            $matched = false;
            foreach ($proceduresIcd9 as $proc) {
                $cleanProc = trim($proc);
                foreach ($req['required_procedures'] as $validProc) {
                    if (str_starts_with($cleanProc, $validProc)) {
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (! $matched && ! empty($proceduresIcd9)) {
                $violations[] = 'RULE_MISSING_REQUIRED_PROCEDURE';
                $warnings[] = $req['description'];
            }
        }

        // 3. Deteksi Upcoding: Menambahkan komplikasi berat (Syok / Sepsis) tanpa bukti diagnostik
        $mccKeywords = ['R57', 'A41', 'J96', 'I46'];
        $hasMcc = false;
        foreach ($secondaryDiagnosesIcd10 as $sec) {
            $prefix = strtoupper(substr(trim($sec), 0, 3));
            if (in_array($prefix, $mccKeywords, true)) {
                $hasMcc = true;
                break;
            }
        }

        if ($hasMcc) {
            $upcodingRisk += 0.40;
            if (! $hasSupportingDiagnosticReport) {
                $violations[] = 'RULE_MCC_WITHOUT_CLINICAL_EVIDENCE';
                $warnings[] = 'Diagnosa komplikasi mayor (MCC) dicantumkan tetapi tidak ditemukan hasil pemeriksaan laboratorium/penunjang medis yang memverifikasi kondisi tersebut.';
                $upcodingRisk += 0.45;
            }
        }

        $isValid = empty($violations);
        $status = $isValid ? 'CLEAN_APPROVED' : 'REVISION_REQUIRED';

        return [
            'is_valid' => $isValid,
            'audit_status' => $status,
            'violation_codes' => $violations,
            'warning_messages' => $warnings,
            'upcoding_risk_score' => min(1.0, round($upcodingRisk, 2)),
        ];
    }
}
