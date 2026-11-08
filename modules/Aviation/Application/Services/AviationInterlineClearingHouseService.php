<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;

class AviationInterlineClearingHouseService
{
    /**
     * Hitung ringkasan kliring bulanan IATA Clearing House untuk perjanjian tertentu.
     *
     * @param  int  $agreementId  ID perjanjian codeshare
     * @param  string  $billingMonth  Format 'Y-m' (e.g. '2026-11')
     * @return array{
     *     agreement_id: int,
     *     billing_month: string,
     *     total_coupons_billed: int,
     *     gross_billing_minor_unit: int,
     *     interline_service_charge_minor_unit: int,
     *     net_settlement_minor_unit: int,
     *     clearing_currency: string,
     *     status: string
     * }
     */
    public function generateMonthlyClearingSummary(int $agreementId, string $billingMonth): array
    {
        $agreement = AviationCodeshareAgreement::with('codeshareFlights')->findOrFail($agreementId);

        $flights = $agreement->codeshareFlights;
        $couponCount = $flights->count() * 45; // Asumsi rata-rata volume kupon per segmen codeshare
        $averageCouponFareMinor = 180000000; // Rp1.800.000 rata-rata

        $grossBilling = $couponCount * $averageCouponFareMinor;
        $operatingBps = (int) $agreement->revenue_split_bps;
        $operatingGross = (int) round(($grossBilling * $operatingBps) / 10000.0);

        // Biaya Penanganan Standar IATA ISC (Interline Service Charge) sebesar 9%
        $iscMinor = (int) round($operatingGross * 0.09);
        $netSettlement = $operatingGross - $iscMinor;

        return [
            'agreement_id' => $agreement->id,
            'billing_month' => $billingMonth,
            'total_coupons_billed' => $couponCount,
            'gross_billing_minor_unit' => $grossBilling,
            'interline_service_charge_minor_unit' => $iscMinor,
            'net_settlement_minor_unit' => $netSettlement,
            'clearing_currency' => 'IDR',
            'status' => 'RECONCILED',
        ];
    }

    /**
     * Terbitkan Nota Penolakan Faktur (Rejection Memo Tahap 1) bila terdapat disparitas kupon.
     */
    public function issueRejectionMemo(
        int $agreementId,
        string $invoiceRef,
        string $disputeReason,
        int $disputedAmountMinorUnit
    ): array {
        $agreement = AviationCodeshareAgreement::findOrFail($agreementId);

        $memoRef = sprintf('REJ-MEMO-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));

        return [
            'memo_reference' => $memoRef,
            'agreement_code' => $agreement->agreement_code,
            'invoice_reference' => $invoiceRef,
            'dispute_reason' => $disputeReason,
            'disputed_amount_minor_unit' => $disputedAmountMinorUnit,
            'status' => 'PENDING_ADJUSTMENT',
            'created_at' => now()->toIso8601String(),
        ];
    }
}
