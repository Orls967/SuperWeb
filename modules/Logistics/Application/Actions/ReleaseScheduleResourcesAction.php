<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Enums\FleetStatus;
use Modules\Logistics\Domain\Enums\ScheduleStatus;
use Modules\Logistics\Domain\Exceptions\InvalidDispatchException;
use Modules\Logistics\Domain\Models\DispatchAssignment;
use Modules\Logistics\Domain\Models\Schedule;
use Modules\Logistics\Domain\Models\Truck;

class ReleaseScheduleResourcesAction
{
    /**
     * Lepas penugasan truk + pengemudi dari jadwal yang belum berangkat.
     */
    public function execute(Schedule $schedule, string $reason = 'Dilepas oleh dispatcher'): void
    {
        DB::transaction(function () use ($schedule, $reason) {
            $schedule = Schedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if (! in_array($schedule->status, [ScheduleStatus::Scheduled, ScheduleStatus::Loading], true)) {
                throw new InvalidDispatchException("Jadwal {$schedule->schedule_number} sudah berstatus '{$schedule->status->value}'; penugasan tidak dapat dilepas.");
            }

            if ($schedule->asset_type === Truck::class && $schedule->asset_id) {
                $truck = Truck::whereKey($schedule->asset_id)->lockForUpdate()->first();
                if ($truck && $truck->status === FleetStatus::ASSIGNED) {
                    $truck->status = FleetStatus::AVAILABLE;
                    $truck->save();
                }
            }

            DispatchAssignment::where('schedule_id', $schedule->id)
                ->where('status', DispatchAssignment::STATUS_ACTIVE)
                ->update([
                    'status' => DispatchAssignment::STATUS_RELEASED,
                    'release_reason' => $reason,
                    'released_at' => now(),
                ]);

            $schedule->asset_type = null;
            $schedule->asset_id = null;
            $schedule->driver_id = null;
            $schedule->save();
        });
    }
}
