<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Marine\Domain\Models\AquacultureExportOrder;

/**
 * Service pemrosesan kontrak pesanan ekspor & kepatuhan Letter of Credit (UCP 600) (Fase 171).
 */
class AquacultureExportOrderService
{
    // Standar toleransi deviasi volume pengapalan internasional (UCP 600 Article 30: +/- 5%)
    public const UCP600_QUANTITY_TOLERANCE_PCT = 5.0;

    /**
     * Verifikasi kepatuhan Letter of Credit sebelum konfirmasi pemuatan kapal ekspor.
     *
     * @param array{
     *     lc_number: string,
     *     issuing_bank_swift: string,
     *     lc_amount_minor_cents: int,
     *     expiry_date: string,
     *     latest_shipment_date: string,
     *     partial_shipments_allowed: bool,
     *     transshipment_allowed: bool,
     *     presented_documents: array<string>
     * } $lcDetails
     * @return array{
     *     is_compliant: bool,
     *     order_number: string,
     *     discrepancies: array<string>,
     *     ucp600_clearance: string,
     *     effective_contract_value_minor_cents: int
     * }
     */
    public function verifyLetterOfCreditCompliance(int $orderId, array $lcDetails): array
    {
        $order = AquacultureExportOrder::findOrFail($orderId);
        $discrepancies = [];

        $lcAmount = (int) ($lcDetails['lc_amount_minor_cents'] ?? 0);
        $orderAmount = (int) $order->total_contract_value_minor_cents;

        // Evaluasi kecukupan nilai L/C terhadap nilai pesanan
        if ($lcAmount < $orderAmount) {
            $discrepancies[] = sprintf(
                'NILAI_LC_KURANG: Nilai L/C (%d cents) lebih kecil dari nilai pesanan (%d cents).',
                $lcAmount,
                $orderAmount
            );
        }

        // Evaluasi tanggal kadaluarsa L/C
        $expiryDate = CarbonImmutable::parse($lcDetails['expiry_date'] ?? now()->subDay()->toDateString());
        if ($expiryDate->isPast()) {
            $discrepancies[] = 'KADALUARSA: Letter of Credit telah melewati tanggal berlakunya.';
        }

        // Evaluasi kelengkapan dokumen wajib UCP 600
        $mandatoryDocs = [
            'SIGNED_COMMERCIAL_INVOICE',
            'CLEAN_ON_BOARD_OCEAN_BILL_OF_LADING',
            'SPS_HEALTH_CERTIFICATE_KKP',
            'CERTIFICATE_OF_ORIGIN_FORM',
            'PACKING_LIST',
        ];

        $presented = $lcDetails['presented_documents'] ?? [];
        foreach ($mandatoryDocs as $doc) {
            if (! in_array($doc, $presented, true)) {
                $discrepancies[] = "DOKUMEN_KURANG: Dokumen wajib {$doc} belum dipresentasikan.";
            }
        }

        $isCompliant = empty($discrepancies);

        if ($isCompliant) {
            $order->update([
                'letter_of_credit_number' => (string) ($lcDetails['lc_number'] ?? 'LC-UNKNOWN'),
                'issuing_bank_swift' => (string) ($lcDetails['issuing_bank_swift'] ?? 'SWIFT-DEFAULT'),
                'status' => 'CONFIRMED',
            ]);
        }

        return [
            'is_compliant' => $isCompliant,
            'order_number' => $order->order_number,
            'discrepancies' => $discrepancies,
            'ucp600_clearance' => $isCompliant ? 'UCP600_DOCUMENTS_CLEARED' : 'DISCREPANT_DOCUMENTS_PRESENTED',
            'effective_contract_value_minor_cents' => $orderAmount,
        ];
    }

    /**
     * Verifikasi toleransi volume pemuatan kontainer reefer terhadap kuota pesanan.
     */
    public function verifyShipmentVolumeTolerance(int $orderId, float $actualShippedWeightKg): bool
    {
        $order = AquacultureExportOrder::findOrFail($orderId);
        $contractWeight = (float) $order->total_ordered_weight_kg;

        if ($contractWeight <= 0.0 || $actualShippedWeightKg <= 0.0) {
            throw new DomainException('Bobot kontrak dan bobot muat aktual harus positif.');
        }

        $deviationKg = abs($actualShippedWeightKg - $contractWeight);
        $deviationPct = ($deviationKg / $contractWeight) * 100.0;

        return $deviationPct <= self::UCP600_QUANTITY_TOLERANCE_PCT;
    }
}
