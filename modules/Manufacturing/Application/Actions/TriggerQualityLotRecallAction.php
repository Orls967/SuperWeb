<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Application\Actions;

use InvalidArgumentException;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\Recall;
use Modules\Shared\Application\BaseAction;

final class TriggerQualityLotRecallAction extends BaseAction
{
    /**
     * Triggers quality lot recall, places lot on quarantine, and initializes recall incident.
     */
    public function execute(
        MaterialLot $lot,
        string $reason,
        int $estimatedCostIdr = 0,
        ?int $ncrId = null
    ): Recall {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan penarikan produk (recall) wajib diisi.');
        }

        return $this->transaction(function () use ($lot, $reason, $estimatedCostIdr, $ncrId) {
            /** @var MaterialLot $lockedLot */
            $lockedLot = MaterialLot::query()->lockForUpdate()->findOrFail($lot->id);

            // Put lot on quarantine / blocked status
            $lockedLot->update([
                'status' => 'quarantine',
                'qc_passed' => false,
            ]);

            $recall = Recall::create([
                'lot_id' => $lockedLot->id,
                'ncr_id' => $ncrId,
                'reason' => $reason,
                'status' => 'initiated',
                'cost_idr' => max(0, $estimatedCostIdr),
                'started_at' => now(),
                'created_by_user_id' => auth()->id() ?? 1,
            ]);

            $this->audit('mfg.quality_lot_recall_triggered', $recall, null, [
                'lot_number' => $lockedLot->lot_number,
                'reason' => $reason,
                'estimated_cost_idr' => $estimatedCostIdr,
            ]);

            return $recall;
        });
    }
}
