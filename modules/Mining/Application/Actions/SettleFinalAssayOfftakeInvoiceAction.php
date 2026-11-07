<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Algorithms\CoalPriceAdjuster;
use Modules\Mining\Domain\Enums\OfftakeShipmentStatus;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;
use Modules\Mining\Domain\StateMachines\OfftakeShipmentStateMachine;
use Modules\Mining\Ledger\MiningFase94LedgerPostingService;

class SettleFinalAssayOfftakeInvoiceAction
{
    public function __construct(
        private readonly OfftakeShipmentStateMachine $stateMachine,
        private readonly MiningFase94LedgerPostingService $ledgerPosting,
        private readonly CoalPriceAdjuster $coalAdjuster = new CoalPriceAdjuster
    ) {}

    /**
     * Pelunasan akhir pengapalan offtake berdasarkan sertifikat assay lab / surveyor independen (D94-7).
     *
     * @param array{
     *     shipment_id: string,
     *     actual_gar_kcal?: int,
     *     actual_ash_permil?: int,
     *     actual_sulfur_permil?: int,
     *     settlement_exchange_rate_idr?: int
     * } $data
     * @return array{
     *     shipment_id: string,
     *     shipment_number: string,
     *     final_invoice_ref: int,
     *     final_adjusted_amount_idr: int,
     *     fx_diff_idr: int,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $shipmentId = (string) $data['shipment_id'];
            $settleRateIdr = (int) ($data['settlement_exchange_rate_idr'] ?? 16300);

            $shipment = MiningOfftakeShipment::with('contract')->findOrFail($shipmentId);

            $contract = $shipment->contract;
            $volume = $shipment->loaded_volume_tons > 0
                ? (int) $shipment->loaded_volume_tons
                : (int) $shipment->nominated_volume_tons;

            $basePriceCents = (int) ($contract->base_price_cents ?: $contract->base_price_usd_cents ?: 8500);

            // Penyesuaian komoditas batubara
            $adjustedPriceCents = $basePriceCents;
            if (isset($data['actual_gar_kcal'])) {
                $adjustResult = $this->coalAdjuster->adjust(
                    basePriceCents: $basePriceCents,
                    actualGarKcal: (int) $data['actual_gar_kcal'],
                    referenceGarKcal: (int) ($contract->benchmark_gar_kcal ?: 5000),
                    actualAshPermil: (int) ($data['actual_ash_permil'] ?? 90),
                    baseAshLimitPermil: 100,
                    actualSulfurPermil: (int) ($data['actual_sulfur_permil'] ?? 8),
                    baseSulfurLimitPermil: 10,
                    volumeTons: $volume
                );
                $adjustedPriceCents = $adjustResult->finalPriceCentsPerTon;
            }

            $finalTotalUsdCents = $volume * $adjustedPriceCents;
            $finalTotalIdr = intdiv($finalTotalUsdCents * $settleRateIdr, 100);

            // Selisih terhadap provisional
            $provisionalRef = (int) ($shipment->provisional_invoice_ref ?? 0);
            $provisionalIdr = intdiv($volume * $basePriceCents * 8500 * 16250, 10000 * 100);
            $adjustmentIdr = $finalTotalIdr - $provisionalIdr;
            $fxDiffIdr = intdiv($finalTotalUsdCents * ($settleRateIdr - 16250), 100);

            $finalInvoiceRef = (abs(crc32($shipment->shipment_number.':final')) % 900000) + 100000;

            $shipment->update([
                'final_invoice_ref' => $finalInvoiceRef,
            ]);

            $this->stateMachine->transition(
                $shipment,
                OfftakeShipmentStatus::FINAL_ASSAYED,
                'metallurgist',
                'CoA dan hasil uji umpire surveyor independen diverifikasi'
            );

            if ($adjustmentIdr !== 0) {
                $this->ledgerPosting->postFinalAssayAdjustment(
                    shipmentId: (string) $shipment->id,
                    shipmentNumber: $shipment->shipment_number,
                    adjustmentIdr: $adjustmentIdr
                );
            }

            $this->stateMachine->transition(
                $shipment,
                OfftakeShipmentStatus::FINAL_INVOICED,
                'finance_officer',
                "Invoice final #INV-FNL-{$finalInvoiceRef} disesuaikan"
            );

            if ($fxDiffIdr !== 0) {
                $this->ledgerPosting->postFxGainLoss(
                    shipmentId: (string) $shipment->id,
                    shipmentNumber: $shipment->shipment_number,
                    fxDiffIdr: $fxDiffIdr
                );
            }

            $this->stateMachine->transition(
                $shipment,
                OfftakeShipmentStatus::SETTLED,
                'finance_officer',
                'Pembayaran L/C dan invoice final dilunasi'
            );

            return [
                'shipment_id' => (string) $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'final_invoice_ref' => $finalInvoiceRef,
                'final_adjusted_amount_idr' => $finalTotalIdr,
                'fx_diff_idr' => $fxDiffIdr,
                'status' => $shipment->status->value,
            ];
        });
    }
}
