<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Exceptions\CarrierException;
use Modules\Logistics\Domain\Models\ShipmentLeg;

class CompleteShipmentLegAction
{
    public function __construct(
        private readonly AccrueLegCostAction $accrue
    ) {}

    /**
     * Tandai leg selesai; bila disubkontrakkan, biayanya diakrual pada saat yang sama (satu transaksi).
     */
    public function execute(ShipmentLeg $leg): ShipmentLeg
    {
        return DB::transaction(function () use ($leg) {
            $leg = ShipmentLeg::whereKey($leg->id)->lockForUpdate()->firstOrFail();

            if ($leg->status === 'cancelled') {
                throw CarrierException::legClosed($leg->id);
            }

            if ($leg->status !== 'completed') {
                $leg->update(['status' => 'completed', 'actual_arrival' => $leg->actual_arrival ?? now()]);
            }

            $this->accrue->execute($leg);

            return $leg->fresh();
        });
    }
}
