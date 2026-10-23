<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Actions;

use App\Models\User;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\CycleCount;
use Modules\Wms\Domain\Models\Warehouse;

final class RecordCycleCountAdjustmentAction extends BaseAction
{
    public function __construct(
        private readonly WmsService $wmsService
    ) {}

    /**
     * Records a cycle count count, submits variance for approval, and applies adjustment under 4-eyes rule.
     *
     * @param  list<array{bin_id: int, product_id: int, counted_qty: float, lot_number?: string}>  $lines
     */
    public function execute(
        Warehouse $warehouse,
        array $lines,
        User $counter,
        User $approver
    ): CycleCount {
        if ($counter->id === $approver->id) {
            throw new InvalidArgumentException('Prinsip empat mata (four-eyes): Penghitung fisik tidak boleh menyetujui penyesuaian sendiri.');
        }

        return $this->transaction(function () use ($warehouse, $lines, $counter, $approver) {
            $count = $this->wmsService->startCycleCount($warehouse, $lines, $counter);

            if (abs((float) $count->variance_qty) < 0.000001) {
                // No variance to adjust, mark count closed
                $count->update(['status' => 'completed']);

                return $count;
            }

            $count = $this->wmsService->submitAdjustment($count, $counter);
            $applied = $this->wmsService->approveAndApply($count, $approver);

            $this->audit('wms.cycle_count_adjusted', $applied, null, [
                'count_number' => $applied->number,
                'counter_id' => $counter->id,
                'approver_id' => $approver->id,
                'variance_qty' => (float) $applied->variance_qty,
            ]);

            return $applied;
        });
    }
}
