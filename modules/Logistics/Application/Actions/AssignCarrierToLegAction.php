<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Exceptions\CarrierException;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\ShipmentLeg;

class AssignCarrierToLegAction
{
    /**
     * Subkontrakkan leg ke carrier dengan biaya yang disepakati (belum diakrual sampai leg selesai).
     */
    public function execute(ShipmentLeg $leg, Carrier $carrier, int $costIdr): ShipmentLeg
    {
        if ($costIdr <= 0) {
            throw CarrierException::invalidCost();
        }

        if (! $carrier->is_active) {
            throw CarrierException::inactive($carrier->name);
        }

        return DB::transaction(function () use ($leg, $carrier, $costIdr) {
            $leg = ShipmentLeg::whereKey($leg->id)->lockForUpdate()->firstOrFail();

            if (in_array($leg->status, ['completed', 'cancelled'], true) || $leg->cost_accrued_at !== null) {
                throw CarrierException::legClosed($leg->id);
            }

            $leg->update(['carrier_id' => $carrier->id, 'carrier_cost_idr' => $costIdr]);

            return $leg;
        });
    }
}
