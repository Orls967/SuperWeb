<?php

declare(strict_types=1);

namespace Modules\Insurance\Contracts;

/**
 * G7-K31: Kontrak Permintaan Jaminan Cashless Rumah Sakit (Cashless Guarantee Requester).
 * Digunakan oleh modul Hospital untuk meminta Surat Jaminan Pelayanan (SJP / Guarantee Letter)
 * dan settlement klaim cashless secara real-time dengan asuransi swasta / penjamin kesehatan.
 */
interface CashlessGuaranteeRequester
{
    /**
     * Mengajukan pra-otorisasi (pre-authorization) penerbitan Guarantee Letter awal admisi.
     *
     * @param  string  $policyNumber  Nomor polis asuransi aktif peserta
     * @param  string  $patientId  ID pasien atau nomor rekam medis
     * @param  string  $admissionDiagnosisCode  Kode diagnosa awal ICD-10
     * @param  int  $estimatedCostIdr  Estimasi biaya perawatan dalam satuan rupiah (IDR integer)
     * @param  string  $hospitalCode  Kode registrasi rumah sakit penjamin
     * @param  array<string, mixed>  $metadata  Data klinis tambahan (kelas rawat, dokter penanggung jawab, dll.)
     * @return array<string, mixed> Data penerbitan SJP / GL awal (guarantee_number, approved_limit_idr, status, auth_code)
     */
    public function requestPreAuthorization(
        string $policyNumber,
        string $patientId,
        string $admissionDiagnosisCode,
        int $estimatedCostIdr,
        string $hospitalCode,
        array $metadata = []
    ): array;

    /**
     * Mengajukan kalkulasi dan settlement pelepasan/pemulangan (discharge settlement) klaim cashless.
     *
     * @param  string  $guaranteeNumber  Nomor Guarantee Letter rujukan pra-otorisasi
     * @param  int  $finalBilledAmountIdr  Total biaya bruto akhir tagihan rumah sakit (IDR)
     * @param  array<string, mixed>  $itemizedFolioSummary  Ringkasan rincian tagihan per kategori (kamar, obat, tindakan)
     * @param  array<string, mixed>  $dischargeSummary  Resume medis akhir, kode ICD-10/ICD-9-CM final
     * @return array<string, mixed> Rincian adjudikasi (approved_claim_idr, copay_amount_idr, patient_excess_idr, settlement_status)
     */
    public function requestDischargeSettlement(
        string $guaranteeNumber,
        int $finalBilledAmountIdr,
        array $itemizedFolioSummary,
        array $dischargeSummary = []
    ): array;

    /**
     * Memeriksa status klaim atau sisa plafon jaminan aktif.
     *
     * @param  string  $guaranteeNumber  Nomor Guarantee Letter
     * @return array<string, mixed> Status jaminan (status, approved_limit_idr, utilized_idr, remaining_limit_idr)
     */
    public function queryGuaranteeStatus(string $guaranteeNumber): array;
}
