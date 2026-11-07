<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\BarInventoryItem;
use Modules\Venue\Domain\Models\BarShiftLog;

final class RecordBarSpillageAction extends BaseAction
{
    public function execute(
        int $shiftLogId,
        int $inventoryItemId,
        int $spillageMl,
        string $reason = 'Over-pour / Bar Spill'
    ): array {
        return DB::transaction(function () use ($shiftLogId, $inventoryItemId, $spillageMl, $reason) {
            if ($spillageMl <= 0) {
                throw new InvalidArgumentException('Volume tumpahan harus lebih besar dari 0 ml.');
            }

            $shift = BarShiftLog::query()->lockForUpdate()->findOrFail($shiftLogId);
            $item = BarInventoryItem::query()->lockForUpdate()->findOrFail($inventoryItemId);

            $shift->increment('spillage_ml', $spillageMl);

            $bottleUnitMl = max(1, $item->unit_volume_ml);
            $spillageBottlesEquivalent = (int) intdiv($shift->spillage_ml, $bottleUnitMl);

            return [
                'shift_log_id' => $shift->id,
                'inventory_item_id' => $item->id,
                'brand_name' => $item->brand_name,
                'added_spillage_ml' => $spillageMl,
                'total_shift_spillage_ml' => $shift->spillage_ml,
                'spillage_bottles_equivalent' => $spillageBottlesEquivalent,
                'reason' => $reason,
            ];
        });
    }
}
