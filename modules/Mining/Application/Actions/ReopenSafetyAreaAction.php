<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Shared\Application\BaseAction;

class ReopenSafetyAreaAction extends BaseAction
{
    /**
     * @return object{area: MiningArea}
     */
    public function handle(
        string $areaId,
        int $approverUserId,
        string $clearanceNotes,
        string $idempotencyKey = ''
    ): object {
        return DB::transaction(function () use ($areaId) {
            $area = MiningArea::lockForUpdate()->findOrFail($areaId);
            $area->status = 'ACTIVE';
            $area->stop_reason = null;
            $area->stopped_at = null;
            $area->save();

            return (object) ['area' => $area];
        });
    }
}
