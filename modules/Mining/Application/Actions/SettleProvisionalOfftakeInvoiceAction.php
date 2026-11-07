<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Enums\OfftakeShipmentStatus;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;
use Modules\Mining\Domain\StateMachines\OfftakeShipmentStateMachine;
use Modules\Mining\Ledger\MiningFase94LedgerPostingService;

class SettleProvisionalOfftakeInvoiceAction
{
    public function __construct(
        private readonly OfftakeShipmentStateMachine $stateMachine,
        private readonly MiningFase94LedgerPostingService $ledgerPosting
    ) {}

    /**
     * Terbitkan tagihan provisional (80%-90%) atas pemuatan kargo pengapalan offtake (D94-7).
     *
     * @param array{
     *     shipment_id: string,
     *     provisional_percentage_bps?: int,
     *     exchange_rate_idr?: int
     * } $data
     * @return array{
     *     shipment_id: string,
     *     shipment_number: string,
     *     provisional_invoice_ref: int,
     *     invoiced_volume_tons: int,
     *     provisional_amount_usd_cents: int,
     *     provisional_amount_idr: int,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $shipmentId = (string) $data['shipment_id'];
            $pctBps = (int) ($data['provisional_percentage_bps'] ?? 8500); // 85% provisional
            $rateIdr = (int) ($data['exchange_rate_idr'] ?? 16250);

            $shipment = MiningOfftakeShipment::with('contract')->findOrFail($shipmentId);

            $volume = $shipment->loaded_volume_tons > 0
                ? (int) $shipment->loaded_volume_tons
                : (int) $shipment->nominated_volume_tons;

            if ($volume <= 0) {
                throw new DomainException("Pengapalan {$shipment->shipment_number} belum memiliki volume muat yang valid.");
            }

            $contract = $shipment->contract;
            $unitPriceCents = (int) ($contract->base_price_cents ?: $contract->base_price_usd_cents ?: 8500);

            $fullAmountUsdCents = $volume * $unitPriceCents;
            $provisionalUsdCents = intdiv($fullAmountUsdCents * $pctBps, 10000);
            $provisionalIdr = intdiv($provisionalUsdCents * $rateIdr, 100);

            $invoiceRef = (abs(crc32($shipment->shipment_number.':prov')) % 900000) + 100000;

            $shipment->update([
                'provisional_invoice_ref' => $invoiceRef,
            ]);

            $this->stateMachine->transition(
                $shipment,
                OfftakeShipmentStatus::PROVISIONAL_INVOICED,
                'finance_officer',
                "Invoice provisional {$pctBps} bps diterbitkan #INV-PROV-{$invoiceRef}"
            );

            // Jurnal akuntansi piutang dagang provisional
            $this->ledgerPosting->postProvisionalInvoice(
                shipmentId: (string) $shipment->id,
                shipmentNumber: $shipment->shipment_number,
                amountIdr: $provisionalIdr
            );

            return [
                'shipment_id' => (string) $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'provisional_invoice_ref' => $invoiceRef,
                'invoiced_volume_tons' => $volume,
                'provisional_amount_usd_cents' => $provisionalUsdCents,
                'provisional_amount_idr' => $provisionalIdr,
                'status' => $shipment->status->value,
            ];
        });
    }
}
