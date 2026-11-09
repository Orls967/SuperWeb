<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * HospitalSurgicalImplantTraceabilityService
 *
 * Mengelola penelusuran implan bedah ortopedi, kardiologi (stent/ring koroner),
 * lensa intraokular, dan prostesis medis lainnya sesuai standar UDI (Unique Device Identification)
 * dan regulasi izin edar alat kesehatan (AKL/AKD Kemenkes RI).
 * Memverifikasi validitas lot, masa simpan sterilitas, serta integritas penagihan folio pasien.
 */
class HospitalSurgicalImplantTraceabilityService
{
    public const IMPLANT_CATEGORY_ORTHOPEDIC = 'ORTHOPEDIC_SCREW_PLATE';

    public const IMPLANT_CATEGORY_CARDIAC_STENT = 'CARDIOLOGY_DRUG_ELUTING_STENT';

    public const IMPLANT_CATEGORY_OPHTHALMIC_LENS = 'OPHTHALMOLOGY_INTRAOCULAR_LENS';

    public const IMPLANT_CATEGORY_NEUROSURGICAL_MESH = 'NEUROSURGERY_TITANIUM_MESH';

    public const STATUS_VERIFIED_STERILE = 'VERIFIED_STERILE_READY_FOR_USE';

    public const STATUS_EXPIRED_STERILITY = 'EXPIRED_STERILITY_REJECTED';

    public const STATUS_RECALLED_BATCH = 'MANUFACTURER_RECALL_QUARANTINED';

    public const STATUS_INVALID_REGISTRATION = 'INVALID_AKL_REGISTRATION';

    /**
     * Verifikasi kelaikan implan bedah sebelum insersi ke tubuh pasien di kamar operasi.
     *
     * @param  string  $udiBarcode  Barcode Unique Device Identification (GS1 format)
     * @param  string  $aklRegistrationNumber  Nomor izin edar Kemenkes RI (misal: KEMENKES RI AKL 30501918234)
     * @param  string  $category  Kategori implan
     * @param  DateTimeImmutable  $sterilizationExpiryDate  Tanggal kedaluwarsa sterilitas kemasan
     * @param  DateTimeImmutable  $operationDate  Tanggal tindakan bedah
     * @param  array<string>  $activeRecallLotNumbers  Daftar nomor lot yang sedang ditarik oleh pabrik/BPOM
     * @param  string  $lotNumber  Nomor lot implan
     * @return array{
     *     udi_barcode: string,
     *     lot_number: string,
     *     category: string,
     *     status: string,
     *     is_approved_for_implantation: bool,
     *     days_remaining_sterility: int,
     *     traceability_audit_token: string,
     *     rejection_reason: ?string
     * }
     */
    public function verifyImplantForSurgery(
        string $udiBarcode,
        string $aklRegistrationNumber,
        string $category,
        DateTimeImmutable $sterilizationExpiryDate,
        DateTimeImmutable $operationDate,
        array $activeRecallLotNumbers,
        string $lotNumber
    ): array {
        if (empty($udiBarcode) || empty($lotNumber)) {
            throw new InvalidArgumentException('UDI Barcode dan lot number tidak boleh kosong');
        }

        // Validasi format nomor registrasi Kemenkes AKL/AKD
        $cleanedReg = strtoupper(trim($aklRegistrationNumber));
        if (! str_contains($cleanedReg, 'AKL') && ! str_contains($cleanedReg, 'AKD')) {
            return [
                'udi_barcode' => $udiBarcode,
                'lot_number' => $lotNumber,
                'category' => $category,
                'status' => self::STATUS_INVALID_REGISTRATION,
                'is_approved_for_implantation' => false,
                'days_remaining_sterility' => 0,
                'traceability_audit_token' => '',
                'rejection_reason' => 'Nomor izin edar alat kesehatan (AKL/AKD) tidak valid atau palsu.',
            ];
        }

        // Pengecekan recall pabrik/BPOM
        if (in_array($lotNumber, $activeRecallLotNumbers, true)) {
            return [
                'udi_barcode' => $udiBarcode,
                'lot_number' => $lotNumber,
                'category' => $category,
                'status' => self::STATUS_RECALLED_BATCH,
                'is_approved_for_implantation' => false,
                'days_remaining_sterility' => 0,
                'traceability_audit_token' => '',
                'rejection_reason' => "Lot {$lotNumber} termasuk dalam daftar penarikan produk (recall) pabrik / BPOM.",
            ];
        }

        // Pengecekan masa kedaluwarsa sterilitas
        $daysRemaining = (int) $operationDate->diff($sterilizationExpiryDate)->format('%r%a');
        if ($daysRemaining < 0) {
            return [
                'udi_barcode' => $udiBarcode,
                'lot_number' => $lotNumber,
                'category' => $category,
                'status' => self::STATUS_EXPIRED_STERILITY,
                'is_approved_for_implantation' => false,
                'days_remaining_sterility' => $daysRemaining,
                'traceability_audit_token' => '',
                'rejection_reason' => sprintf('Masa sterilitas implan telah kedaluwarsa sejak %d hari yang lalu.', abs($daysRemaining)),
            ];
        }

        // Terverifikasi lolos: hasilkan token penelusuran permanen rekam medis
        $auditPayload = sprintf('%s|%s|%s|%s|%s', $udiBarcode, $lotNumber, $aklRegistrationNumber, $category, $operationDate->format('Y-m-d'));
        $auditToken = 'IMP-TRACE-'.strtoupper(hash('sha256', $auditPayload));

        return [
            'udi_barcode' => $udiBarcode,
            'lot_number' => $lotNumber,
            'category' => $category,
            'status' => self::STATUS_VERIFIED_STERILE,
            'is_approved_for_implantation' => true,
            'days_remaining_sterility' => $daysRemaining,
            'traceability_audit_token' => $auditToken,
            'rejection_reason' => null,
        ];
    }
}
