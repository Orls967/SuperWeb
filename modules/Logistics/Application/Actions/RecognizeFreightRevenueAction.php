<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Enums\PaymentTerms;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Exceptions\InvalidDeliveryOperationException;
use Modules\Logistics\Domain\Models\Shipment;

class RecognizeFreightRevenueAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger
    ) {}

    /**
     * Akui pendapatan freight saat resi Delivered.
     * - Prabayar (Alur 2): debit unearned_freight, kredit freight_revenue.
     * - Pascabayar (Alur 4): debit piutang shipper (AR), kredit freight_revenue.
     * Idempoten per resi (kunci jurnal + penanda revenue_recognized_at).
     *
     * @return bool true jika jurnal baru diposting
     */
    public function execute(Shipment $shipment): bool
    {
        return DB::transaction(function () use ($shipment) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($shipment->status !== ShipmentStatus::Delivered) {
                throw new InvalidDeliveryOperationException("Pendapatan resi {$shipment->tracking_number} hanya diakui setelah berstatus Terkirim.");
            }

            if ($shipment->revenue_recognized_at !== null) {
                return false;
            }

            $amount = (int) $shipment->total_amount_idr;
            if ($amount > 0) {
                $debitAccount = $shipment->payment_terms === PaymentTerms::Postpaid
                    ? LogisticsLedger::arCode($shipment->shipper_id)
                    : LogisticsLedger::UNEARNED_FREIGHT;

                $this->ledger->post(
                    type: TransactionType::LOGISTICS_REVENUE,
                    description: "Pengakuan pendapatan freight resi {$shipment->tracking_number}",
                    idempotencyKey: "lgx:revenue:{$shipment->id}",
                    entries: [
                        [$debitAccount, -$amount],
                        [LogisticsLedger::FREIGHT_REVENUE, $amount],
                    ],
                    referenceType: Shipment::class,
                    referenceId: $shipment->id,
                );
            }

            $shipment->revenue_recognized_at = now();
            $shipment->save();

            return $amount > 0;
        });
    }
}
