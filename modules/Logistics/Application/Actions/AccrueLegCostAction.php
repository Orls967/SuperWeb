<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Models\ShipmentLeg;

class AccrueLegCostAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger
    ) {}

    /**
     * Alur 9: akrual biaya leg subkontrak. Debit beban carrier, kredit utang carrier. Idempoten per leg.
     *
     * @return bool true jika jurnal baru diposting
     */
    public function execute(ShipmentLeg $leg): bool
    {
        return DB::transaction(function () use ($leg) {
            $leg = ShipmentLeg::whereKey($leg->id)->lockForUpdate()->firstOrFail();

            if ($leg->carrier_id === null || $leg->carrier_cost_idr <= 0 || $leg->cost_accrued_at !== null) {
                return false;
            }

            $this->ledger->post(
                type: TransactionType::LOGISTICS_CARRIER_ACCRUAL,
                description: "Akrual biaya carrier leg #{$leg->id} resi #{$leg->shipment_id}",
                idempotencyKey: "lgx:leg_accrual:{$leg->id}",
                entries: [
                    [LogisticsLedger::CARRIER_COST, -$leg->carrier_cost_idr],
                    [LogisticsLedger::carrierPayableCode($leg->carrier_id), $leg->carrier_cost_idr],
                ],
                referenceType: ShipmentLeg::class,
                referenceId: $leg->id,
            );

            $leg->update(['cost_accrued_at' => now()]);

            return true;
        });
    }
}
