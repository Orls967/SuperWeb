<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Mesin Manajemen Penolakan & Banding Klaim Asuransi/BPJS (ClaimDenialManagementEngine).
 * Mengotomasi penanganan klaim bermasalah (Disputed & Denied Claims):
 *   1. Kategorisasi akar penyebab penolakan klaim (Root-Cause Categorization).
 *   2. Estimasi probabilitas keberhasilan banding (Appeal Overturn Probability).
 *   3. Pembuatan berkas justifikasi klinis banding resmi (Clinical Justification Dossier).
 *   4. Analisis dampak keuangan terhadap likuiditas arus kas rumah sakit.
 */
class ClaimDenialManagementEngine
{
    /**
     * Peta kategori kode penolakan klaim BPJS & asuransi swasta.
     *
     * @var array<string, array{name: string, appealable: bool, standard_turnaround_days: int, baseline_overturn_prob: float}>
     */
    private const DENIAL_REASON_REGISTRY = [
        'D01_MISSING_CLINICAL_RESUME' => [
            'name' => 'Resume Medis Tidak Lengkap atau Tidak Ditandatangani DPJP',
            'appealable' => true,
            'standard_turnaround_days' => 7,
            'baseline_overturn_prob' => 0.88,
        ],
        'D02_CODING_MISMATCH' => [
            'name' => 'Ketidaksesuaian Koding ICD-10 Diagnosa Primer dengan ICD-9-CM Prosedur',
            'appealable' => true,
            'standard_turnaround_days' => 14,
            'baseline_overturn_prob' => 0.72,
        ],
        'D03_TIMELY_FILING_EXCEEDED' => [
            'name' => 'Keterlambatan Pengajuan Berkas Melebihi Batas Waktu Regulasi (15 Hari)',
            'appealable' => false,
            'standard_turnaround_days' => 3,
            'baseline_overturn_prob' => 0.15,
        ],
        'D04_PREAUTH_NONCOMPLIANCE' => [
            'name' => 'Tindakan atau Obat Khusus Tanpa Surat Jaminan Pra-Otorisasi Awal',
            'appealable' => true,
            'standard_turnaround_days' => 10,
            'baseline_overturn_prob' => 0.60,
        ],
        'D05_MEDICAL_NECESSITY_QUESTIONED' => [
            'name' => 'Indikasi Rawat Inap Dipertanyakan oleh Tim Kendali Mutu & Biaya',
            'appealable' => true,
            'standard_turnaround_days' => 21,
            'baseline_overturn_prob' => 0.55,
        ],
        'D06_DUPLICATE_BILLING' => [
            'name' => 'Dugaan Klaim Ganda pada Periode Episode Perawatan yang Sama',
            'appealable' => true,
            'standard_turnaround_days' => 5,
            'baseline_overturn_prob' => 0.80,
        ],
    ];

    /**
     * Menganalisis alasan penolakan dan menghitung estimasi keberhasilan banding klaim.
     *
     * @param  string  $denialReasonCode  Kode penolakan (e.g. D01_MISSING_CLINICAL_RESUME)
     * @param  int  $disputedAmountIdr  Nilai tagihan yang ditolak
     * @param  bool  $hasSignedDoctorResume  Apakah resume dokter DPJP lengkap
     * @param  bool  $hasDiagnosticEvidence  Apakah bukti lab/rontgen/EKG lengkap
     * @param  int  $daysSinceDischarge  Hari sejak pasien pulang
     * @return array{
     *     denial_code: string,
     *     denial_name: string,
     *     is_appealable: bool,
     *     estimated_overturn_probability: float,
     *     recommended_strategy: string,
     *     estimated_recoverable_amount_idr: int,
     *     deadline_days: int
     * }
     */
    public function evaluateDenialAndAppealViability(
        string $denialReasonCode,
        int $disputedAmountIdr,
        bool $hasSignedDoctorResume,
        bool $hasDiagnosticEvidence,
        int $daysSinceDischarge
    ): array {
        $code = strtoupper(trim($denialReasonCode));
        $meta = self::DENIAL_REASON_REGISTRY[$code] ?? [
            'name' => 'Penolakan Tidak Terdaftar / Umum',
            'appealable' => true,
            'standard_turnaround_days' => 14,
            'baseline_overturn_prob' => 0.50,
        ];

        if ($disputedAmountIdr <= 0) {
            throw new DomainException('Jumlah klaim yang disengketakan harus lebih dari nol.');
        }

        $probability = $meta['baseline_overturn_prob'];

        // Penyesuaian berdasarkan bukti kelengkapan berkas klinis
        if ($hasSignedDoctorResume) {
            $probability += 0.10;
        } else {
            $probability -= 0.25;
        }

        if ($hasDiagnosticEvidence) {
            $probability += 0.08;
        } else {
            $probability -= 0.15;
        }

        // Penyesuaian jika waktu pengajuan sudah mepet batas kadaluarsa
        if ($daysSinceDischarge > 14) {
            $probability -= 0.20;
        }

        $clampedProbability = max(0.05, min(0.98, round($probability, 2)));
        $recoverableAmount = (int) round($disputedAmountIdr * $clampedProbability);

        $strategy = 'SUBMIT_FORMAL_APPEAL';
        if (! $meta['appealable'] || $clampedProbability < 0.25) {
            $strategy = 'WRITE_OFF_OR_TRANSFER_TO_PATIENT_SHARE';
        } elseif ($code === 'D02_CODING_MISMATCH') {
            $strategy = 'RE_GROUP_WITH_CODER_COMMITTEE_AND_RE_SUBMIT';
        } elseif ($code === 'D01_MISSING_CLINICAL_RESUME') {
            $strategy = 'EXPEDITE_DOCTOR_SIGNATURE_AND_UPLOAD';
        }

        return [
            'denial_code' => $code,
            'denial_name' => $meta['name'],
            'is_appealable' => $meta['appealable'] && $clampedProbability >= 0.25,
            'estimated_overturn_probability' => $clampedProbability,
            'recommended_strategy' => $strategy,
            'estimated_recoverable_amount_idr' => $recoverableAmount,
            'deadline_days' => $meta['standard_turnaround_days'],
        ];
    }

    /**
     * Membuat ringkasan dokumen berkas banding klinis (Dossier Header).
     *
     * @param  string  $claimNumber
     * @param  string  $dpjpDoctorName
     * @param  string  $clinicalRationale
     * @param  array<int, string>  $attachedEvidenceCodes
     * @return array<string, mixed>
     */
    public function generateAppealDossier(
        string $claimNumber,
        string $dpjpDoctorName,
        string $clinicalRationale,
        array $attachedEvidenceCodes
    ): array {
        if (empty(trim($clinicalRationale))) {
            throw new DomainException('Rasionalisasi klinis banding wajib diisi oleh dokter DPJP.');
        }

        return [
            'appeal_dossier_id' => 'APL-'.date('Ymd').'-'.strtoupper(substr(md5($claimNumber), 0, 8)),
            'claim_number' => $claimNumber,
            'attending_physician' => $dpjpDoctorName,
            'clinical_rationale' => $clinicalRationale,
            'attached_evidence_count' => count($attachedEvidenceCodes),
            'attached_evidence_list' => $attachedEvidenceCodes,
            'certified_at' => now()->toIso8601String(),
            'status' => 'READY_FOR_SUBMISSION',
        ];
    }
}
