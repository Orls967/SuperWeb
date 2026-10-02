<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Shipment;

class RecordCodCollectionAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger
    ) {}

    /**
     * Alur 6: driver menerima uang tunai COD dari penerima.
     * Debit kas COD di tangan driver, kredit titipan COD milik shipper. Idempoten per resi.
     * Dipanggil di dalam transaksi penyelesaian pengantaran.
     */
    public function execute(Shipment $shipment, Driver $driver): CodCollection
    {
        $existing = CodCollection::where('shipment_id', $shipment->id)->first();
        if ($existing) {
            return $existing;
        }

        $amount = (int) $shipment->cod_amount_idr;

        $this->ledger->post(
            type: TransactionType::LOGISTICS_COD_COLLECTION,
            description: "Penerimaan COD resi {$shipment->tracking_number} oleh driver {$driver->driver_number}",
            idempotencyKey: "lgx:cod_collect:{$shipment->id}",
            entries: [
                [LogisticsLedger::driverCashCode($driver->id), -$amount],
                [LogisticsLedger::codPayableCode($shipment->shipper_id), $amount],
            ],
            referenceType: Shipment::class,
            referenceId: $shipment->id,
            createdBy: $driver->user_id,
        );

        return CodCollection::create([
            'shipment_id' => $shipment->id,
            'shipper_id' => $shipment->shipper_id,
            'driver_id' => $driver->id,
            'amount_idr' => $amount,
            'status' => CodCollection::STATUS_COLLECTED,
            'collected_at' => now(),
        ]);
    }
}
