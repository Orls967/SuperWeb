<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\AquacultureExportOrder;

/**
 * Service portal pengadaan buyer internasional & resolusi sengketa mutu (Fase 171 Sub-item 171.4).
 */
class BuyerProcurementPortalService
{
    // Batas toleransi standar deviasi bobot dan glazing internasional
    public const MAX_GLAZE_DEFROST_DEVIATION_PCT = 2.0; // Deviasi es maksimum 2%

    public const MAX_ORGANOLEPTIC_DEFECT_TOLERANCE_PCT = 1.0; // Maksimum cacat fisik 1%

    /**
     * Daftarkan pesanan kontrak pengadaan baru dari buyer internasional.
     *
     * @param  array<string, mixed>  $orderData
     */
    public function createProcurementContract(int $buyerUserId, array $orderData): AquacultureExportOrder
    {
        $weightKg = (float) ($orderData['total_ordered_weight_kg'] ?? 0.0);
        if ($weightKg <= 0.0) {
            throw new DomainException('Volume pesanan ekspor harus lebih besar dari 0 kg.');
        }

        $contractValueMinor = (int) ($orderData['total_contract_value_minor_cents'] ?? 0);
        if ($contractValueMinor <= 0) {
            throw new DomainException('Nilai kontrak ekspor harus lebih besar dari 0 minor cents.');
        }

        $country = strtoupper((string) ($orderData['destination_country_code'] ?? 'US'));
        $port = strtoupper((string) ($orderData['destination_port_unlocode'] ?? 'USLAX'));
        $incoterm = strtoupper((string) ($orderData['incoterm'] ?? 'CIF'));

        $orderNumber = 'ORD-EXP-'.$country.'-'.strtoupper(substr(hash('sha256', $buyerUserId.microtime()), 0, 8));

        return AquacultureExportOrder::create([
            'order_number' => $orderNumber,
            'buyer_party_id' => $buyerUserId,
            'destination_country_code' => $country,
            'destination_port_unlocode' => $port,
            'incoterm' => $incoterm,
            'total_ordered_weight_kg' => $weightKg,
            'total_contract_value_minor_cents' => $contractValueMinor,
            'payment_instrument' => (string) ($orderData['payment_instrument'] ?? 'LETTER_OF_CREDIT'),
            'letter_of_credit_number' => $orderData['letter_of_credit_number'] ?? null,
            'issuing_bank_swift' => $orderData['issuing_bank_swift'] ?? null,
            'status' => 'CONFIRMED',
        ]);
    }

    /**
     * Evaluasi klaim susut bobot (weight loss / defrost shrinkage claim) dari buyer pelabuhan tujuan.
     *
     * @return array{
     *     lot_tracking_number: string,
     *     shipped_net_weight_kg: float,
     *     received_defrost_weight_kg: float,
     *     actual_shrinkage_percentage: float,
     *     allowable_tolerance_percentage: float,
     *     claim_accepted: bool,
     *     penalty_amount_minor_cents: int,
     *     resolution_verdict: string
     * }
     */
    public function evaluateDefrostShrinkageClaim(
        int $exportLotId,
        float $receivedDefrostWeightKg,
        int $priceMinorCentsPerKg
    ): array {
        $lot = AquacultureExportLot::findOrFail($exportLotId);
        $shippedWeight = (float) $lot->net_lot_weight_kg;

        if ($receivedDefrostWeightKg <= 0.0 || $shippedWeight <= 0.0) {
            throw new DomainException('Bobot produk yang diterima dan bobot kirim harus positif.');
        }

        $shrinkageKg = max(0.0, $shippedWeight - $receivedDefrostWeightKg);
        $shrinkagePct = round(($shrinkageKg / $shippedWeight) * 100.0, 2);

        $claimAccepted = ($shrinkagePct > self::MAX_GLAZE_DEFROST_DEVIATION_PCT);
        $excessShrinkageKg = $claimAccepted ? ($shrinkageKg - ($shippedWeight * (self::MAX_GLAZE_DEFROST_DEVIATION_PCT / 100.0))) : 0.0;
        $penaltyMinor = (int) round($excessShrinkageKg * $priceMinorCentsPerKg);

        $verdict = $claimAccepted
            ? 'DISPUTE_CLAIM_APPROVED_PENALTY_DEDUCTED'
            : 'NORMAL_SHRINKAGE_WITHIN_CONTRACT_TOLERANCE';

        return [
            'lot_tracking_number' => $lot->lot_tracking_number,
            'shipped_net_weight_kg' => $shippedWeight,
            'received_defrost_weight_kg' => $receivedDefrostWeightKg,
            'actual_shrinkage_percentage' => $shrinkagePct,
            'allowable_tolerance_percentage' => self::MAX_GLAZE_DEFROST_DEVIATION_PCT,
            'claim_accepted' => $claimAccepted,
            'penalty_amount_minor_cents' => $penaltyMinor,
            'resolution_verdict' => $verdict,
        ];
    }

    /**
     * Alokasikan slot kontainer pendingin pada pesanan ekspor.
     */
    public function allocateShipmentSlot(
        int $orderId,
        int $exportLotId,
        string $containerNumber,
        string $carrier,
        string $bookingRef,
        string $billOfLading
    ): void {
        $order = AquacultureExportOrder::findOrFail($orderId);
        $lot = AquacultureExportLot::findOrFail($exportLotId);

        $order->shipments()->create([
            'shipment_tracking_number' => 'REEF-SLOT-'.strtoupper(substr(hash('sha256', $containerNumber.microtime()), 0, 10)),
            'export_lot_id' => $lot->id,
            'container_iso_number' => $containerNumber,
            'shipping_line_carrier' => $carrier,
            'booking_reference' => $bookingRef,
            'ocean_bill_of_lading' => $billOfLading,
            'health_certificate_official_number' => 'HC-KKP-PENDING-'.substr(hash('sha256', $containerNumber), 0, 6),
            'temperature_setpoint_celsius' => -22.0,
            'stuffed_and_sealed_at' => now(),
            'estimated_departure_at' => now()->addDays(3),
            'estimated_arrival_at' => now()->addDays(24),
            'shipment_status' => 'BOOKED',
        ]);
    }
}
