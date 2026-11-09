<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * InaCbgDisputeResolutionEngine
 *
 * Mengelola berkas sanggahan (dispute dossier) dan strategi banding atas penolakan/pending
 * klaim BPJS Kesehatan E-Klaim. Menganalisis alasan dispute berdasarkan Berita Acara Kesepakatan Bersama
 * Kemenkes & BPJS Kesehatan, kelengkapan berkas rekam medis elektronik, serta formularium nasional.
 */
class InaCbgDisputeResolutionEngine
{
    public const DISPUTE_CODE_CODING_DIAGNOSIS = 'DISPUTE_PRIMARY_DIAGNOSIS_ACCURACY';

    public const DISPUTE_CODE_PROCEDURE_BUNDLING = 'DISPUTE_UNBUNDLED_SURGICAL_PROCEDURE';

    public const DISPUTE_CODE_MEDICAL_NECESSITY = 'DISPUTE_MEDICAL_INDICATION_ADMISSION';

    public const DISPUTE_CODE_SPECIAL_PROCEDURE_SUBACUTE = 'DISPUTE_SUBACUTE_REHABILITATION_TARIFF';

    public const DISPUTE_CODE_MEDICATION_RESTRICTION = 'DISPUTE_FORNAS_RESTRICTION_OFF_LABEL';

    /**
     * Susun berkas justifikasi klinis untuk banding klaim INA-CBG.
     *
     * @param  string  $disputeCode  Kode pokok sengketa
     * @param  string  $icd10Primary  Diagnosis primer yang diajukan RS
     * @param  array<string>  $supportingEvidenceCodes  Daftar bukti medis (LAB_RESULT, RADIOLOGY_REPORT, RESUME_MEDIS, PATHOLOGY)
     * @param  int  $claimedTariffInCents  Nilai klaim RS dalam sen
     * @param  int  $adjudicatedTariffInCents  Nilai yang disetujui verifikator BPJS dalam sen
     * @return array{
     *     dispute_code: string,
     *     contested_amount_cents: int,
     *     appeal_viability_score: int,
     *     recommended_action: string,
     *     required_clinical_attachments: array<string>,
     *     regulatory_reference: string,
     *     defense_argument_template: string
     * }
     */
    public function constructDisputeAppeal(
        string $disputeCode,
        string $icd10Primary,
        array $supportingEvidenceCodes,
        int $claimedTariffInCents,
        int $adjudicatedTariffInCents
    ): array {
        if ($claimedTariffInCents < $adjudicatedTariffInCents) {
            throw new InvalidArgumentException('Nilai klaim RS harus lebih besar atau sama dengan nilai adjudikasi');
        }

        $contestedAmount = $claimedTariffInCents - $adjudicatedTariffInCents;
        $evidenceCount = count($supportingEvidenceCodes);

        // Skoring kelayakan banding (0 - 100)
        $score = 40; // baseline
        $score += min(40, $evidenceCount * 10);

        if (in_array('RESUME_MEDIS', $supportingEvidenceCodes, true)) {
            $score += 10;
        }
        if (in_array('PATHOLOGY', $supportingEvidenceCodes, true) || in_array('RADIOLOGY_REPORT', $supportingEvidenceCodes, true)) {
            $score += 10;
        }

        $score = min(100, $score);

        $regulatoryRef = 'Permenkes No. 26 Tahun 2021 tentang Pedoman INA-CBG';
        $recommendedAction = 'SUBMIT_FORMAL_DISPUTE_TKMKB'; // Tim Kendali Mutu dan Kendali Biaya
        $attachments = ['Resume Medis Terverifikasi DPJP', 'Salinan CPPT (Catatan Perkembangan Pasien Terintegrasi)'];
        $template = '';

        switch ($disputeCode) {
            case self::DISPUTE_CODE_CODING_DIAGNOSIS:
                $regulatoryRef = 'Kaidah Koding ICD-10 Bab Morbiditas & Kepmenkes HK.01.07/MENKES/2023';
                $attachments[] = 'Hasil Pemeriksaan Penunjang Objektif Penegak Diagnosis';
                $template = sprintf(
                    'Diagnosis primer %s ditegakkan berdasarkan manifestasi klinis utama yang menyerap sumber daya terbesar rawat inap sesuai aturan koding Morbidity Coding Rule MB1.',
                    $icd10Primary
                );
                break;

            case self::DISPUTE_CODE_PROCEDURE_BUNDLING:
                $regulatoryRef = 'Katalog Prosedur INA-CBG ICD-9-CM & Petunjuk Teknis Klaim Kemenkes';
                $attachments[] = 'Laporan Operasi Rinci & Catatan Anestesi';
                $template = 'Prosedur tindakan operasi yang dilakukan merupakan dua tindakan mandiri berlainan insisi atau target organ berbeda, bukan merupakan fragmentasi atau bundling satu prosedur.';
                break;

            case self::DISPUTE_CODE_MEDICAL_NECESSITY:
                $regulatoryRef = 'Panduan Praktik Klinis (PPK) & Clinical Pathway Rumah Sakit';
                $attachments[] = 'Tanda Vital Triase IGD & Bukti Kriteria Rawat Inap Terpenuhi';
                $template = 'Kondisi kegawatdaruratan dan ketidakstabilan hemodinamik pasien saat admisi memenuhi indikasi rawat inap medis mutlak demi mencegah mortalitas.';
                break;

            case self::DISPUTE_CODE_MEDICATION_RESTRICTION:
                $regulatoryRef = 'Formularium Nasional (Fornas) Kepmenkes & Restriksi Penggunaan Obat';
                $attachments[] = 'Lembar Verifikasi Farmasi Klinis & Hasil Uji Sensitivitas Kultur';
                $template = 'Pemberian obat antibiotik lini lanjutan atau obat khusus telah sesuai restriksi fornas dan didukung hasil kultur mikrobiologi resistensi.';
                break;

            default:
                $template = 'Sanggahan klaim resmi diajukan beserta bukti pendukung klinis lengkap.';
                break;
        }

        if ($score < 60) {
            $recommendedAction = 'ACCEPT_ADJUDICATION_WRITE_OFF_DIFFERENCE';
        }

        return [
            'dispute_code' => $disputeCode,
            'contested_amount_cents' => $contestedAmount,
            'appeal_viability_score' => $score,
            'recommended_action' => $recommendedAction,
            'required_clinical_attachments' => $attachments,
            'regulatory_reference' => $regulatoryRef,
            'defense_argument_template' => $template,
        ];
    }
}
