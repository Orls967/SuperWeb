<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Illuminate\Support\Str;

/**
 * Layanan Simulator Adjudikasi Klaim Penjamin Otomatis (AutomatedClaimAdjudicationSimulator).
 * Mensimulasikan protokol pertukaran data web service E-Klaim BPJS & TPA Asuransi Swasta:
 *   1. Verifikasi kelayakan kepesertaan peserta aktif (Eligibility Verification).
 *   2. Adjudikasi aturan benefit limit & deductibles.
 *   3. Penerbitan Nomor Otorisasi Klaim (Claim Authorization Reference).
 *   4. Pengembalian rincian pemotongan co-payment dan excess share.
 */
class AutomatedClaimAdjudicationSimulator
{
    /**
     * Mensimulasikan adjudikasi klaim instan secara real-time.
     *
     * @param  string  $payerCode  Kode penjamin (BPJS, PRUDENTIAL, ALLIANZ, MANDIRI_INHEALTH)
     * @param  string  $policyOrCardNumber  Nomor kartu peserta / nomor polis
     * @param  int  $billedAmountIdr  Total biaya bruto yang diajukan
     * @param  string  $cbgOrProcedureCode  Kode INA-CBG atau tindakan
     * @param  array<string, mixed>  $supportingDocumentHashes  Hash bukti resume medis
     * @return array{
     *     adjudication_id: string,
     *     payer_code: string,
     *     adjudication_status: string,
     *     approved_amount_idr: int,
     *     deductible_amount_idr: int,
     *     patient_copay_idr: int,
     *     disapproved_amount_idr: int,
     *     adjudication_timestamp: string,
     *     authorization_code: string
     * }
     */
    public function simulateAdjudication(
        string $payerCode,
        string $policyOrCardNumber,
        int $billedAmountIdr,
        string $cbgOrProcedureCode,
        array $supportingDocumentHashes = []
    ): array {
        if ($billedAmountIdr <= 0) {
            throw new DomainException('Jumlah tagihan yang diajukan adjudikasi harus lebih dari nol.');
        }

        $payer = strtoupper(trim($payerCode));
        $adjudicationId = 'ADJ-'.date('Ymd').'-'.strtoupper(Str::random(8));
        $authCode = 'AUTH-'.strtoupper(Str::random(6));

        // Simulasi jika berkas resume medis tidak lengkap
        if (empty($supportingDocumentHashes)) {
            return [
                'adjudication_id' => $adjudicationId,
                'payer_code' => $payer,
                'adjudication_status' => 'PENDING_DOCUMENTATION',
                'approved_amount_idr' => 0,
                'deductible_amount_idr' => 0,
                'patient_copay_idr' => 0,
                'disapproved_amount_idr' => $billedAmountIdr,
                'adjudication_timestamp' => now()->toIso8601String(),
                'authorization_code' => 'REJECT-DOCS-INCOMPLETE',
            ];
        }

        // Jika BPJS Kesehatan: Pembayaran berbasis tarif paket flat INA-CBGs
        if ($payer === 'BPJS') {
            $approved = $billedAmountIdr; // Ditanggung penuh sesuai paket

            return [
                'adjudication_id' => $adjudicationId,
                'payer_code' => $payer,
                'adjudication_status' => 'APPROVED_FULL',
                'approved_amount_idr' => $approved,
                'deductible_amount_idr' => 0,
                'patient_copay_idr' => 0,
                'disapproved_amount_idr' => 0,
                'adjudication_timestamp' => now()->toIso8601String(),
                'authorization_code' => $authCode,
            ];
        }

        // Jika Asuransi Swasta: Skema Deductible (5%) + Co-Payment (10%)
        $deductible = (int) round($billedAmountIdr * 0.05);
        $remainingAfterDeductible = max(0, $billedAmountIdr - $deductible);
        $copay = (int) round($remainingAfterDeductible * 0.10);
        $approvedAmount = max(0, $remainingAfterDeductible - $copay);

        return [
            'adjudication_id' => $adjudicationId,
            'payer_code' => $payer,
            'adjudication_status' => 'APPROVED_PARTIAL',
            'approved_amount_idr' => $approvedAmount,
            'deductible_amount_idr' => $deductible,
            'patient_copay_idr' => $copay,
            'disapproved_amount_idr' => 0,
            'adjudication_timestamp' => now()->toIso8601String(),
            'authorization_code' => $authCode,
        ];
    }
}
