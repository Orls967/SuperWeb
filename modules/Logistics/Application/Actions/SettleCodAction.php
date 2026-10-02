<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Services\CodFeeCalculator;

class SettleCodAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger,
        private readonly CodFeeCalculator $fees
    ) {}

    /**
     * Alur 8: cairkan dana COD yang sudah disetor ke dompet shipper dikurangi fee COD (pendapatan cod_fee_revenue).
     *
     * @return bool true jika baris ini baru saja dicairkan
     */
    public function execute(CodCollection $collection): bool
    {
        return DB::transaction(function () use ($collection) {
            $collection = CodCollection::whereKey($collection->id)->lockForUpdate()->firstOrFail();

            if ($collection->status !== CodCollection::STATUS_DEPOSITED) {
                return false;
            }

            $fee = $this->fees->feeFor($collection->amount_idr);
            $net = $collection->amount_idr - $fee;
            $shipperWallet = $collection->shipper->walletAccount('IDR');

            $this->ledger->post(
                type: TransactionType::LOGISTICS_COD_SETTLEMENT,
                description: "Pencairan COD resi #{$collection->shipment_id} ke shipper (fee Rp ".number_format($fee, 0, ',', '.').')',
                idempotencyKey: "lgx:cod_settle:{$collection->id}",
                entries: [
                    [LogisticsLedger::codPayableCode($collection->shipper_id), -$collection->amount_idr],
                    [$shipperWallet->id, $net],
                    [LogisticsLedger::COD_FEE_REVENUE, $fee],
                ],
                referenceType: CodCollection::class,
                referenceId: $collection->id,
            );

            $collection->update([
                'fee_idr' => $fee,
                'net_amount_idr' => $net,
                'status' => CodCollection::STATUS_SETTLED,
                'settled_at' => now(),
            ]);

            return true;
        });
    }
}
