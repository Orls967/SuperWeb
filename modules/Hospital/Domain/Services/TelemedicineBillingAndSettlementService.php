<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\TeleConsult;
use Modules\Hospital\Domain\ValueObjects\TelemedicineConsultationInvoice;

/**
 * Telemedicine Billing, Multi-Payer Split & Settlement Service.
 * Menghitung tagihan akhir dan alokasi tanggungan asuransi/pasien:
 * 1. Kompilasi biaya konsultasi klinis, obat-obatan e-resep, dan tarif pengiriman kurir.
 * 2. Perhitungan penjaminan asuransi (BPJS / Swasta) vs porsi bayar mandiri (patient copay).
 * 3. Penerbitan invoice terpadu (Unified Telehealth Invoice).
 */
class TelemedicineBillingAndSettlementService
{
    private const BASE_CONSULT_FEE_IDR = 150_000;

    private const BASE_COURIER_FEE_IDR = 25_000;

    /**
     * Menerbitkan invoice terpadu untuk satu episode telemedis dan penebusan e-resepnya.
     */
    public function generateConsultationInvoice(
        TeleConsult $teleConsult,
        ?EPharmacyOrder $order = null,
        int $insuranceCoveredAmountIdr = 0
    ): TelemedicineConsultationInvoice {
        $consultFee = self::BASE_CONSULT_FEE_IDR;
        $medicineFee = $order ? (int) $order->total_price_idr : 0;
        $courierFee = $order ? self::BASE_COURIER_FEE_IDR : 0;

        $grossTotal = $consultFee + $medicineFee + $courierFee;
        $patientShare = max(0, $grossTotal - $insuranceCoveredAmountIdr);

        $invNumber = 'INV-TEL-'.strtoupper(bin2hex(random_bytes(4)));

        return new TelemedicineConsultationInvoice(
            invoiceNumber: $invNumber,
            consultCode: $teleConsult->consult_code,
            consultationFeeIdr: $consultFee,
            prescriptionMedicinesIdr: $medicineFee,
            deliveryFreightFeeIdr: $courierFee,
            insuranceCoverageIdr: $insuranceCoveredAmountIdr,
            patientPayableIdr: $patientShare,
            settlementStatus: $patientShare === 0 ? 'SETTLED_BY_INSURANCE' : 'PENDING_PAYMENT'
        );
    }
}
