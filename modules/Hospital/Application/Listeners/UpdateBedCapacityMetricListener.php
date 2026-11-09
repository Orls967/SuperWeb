<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Hospital\Application\Events\BedAssignedEvent;
use Modules\Hospital\Domain\Models\HospitalBed;

final class UpdateBedCapacityMetricListener
{
    public function handle(BedAssignedEvent $event): void
    {
        $occupied = HospitalBed::where('status', 'OCCUPIED')->count();
        $total = HospitalBed::count();
        $bor = $total > 0 ? round(($occupied / $total) * 100, 2) : 0.0;

        Log::info("BED_CAPACITY_METRIC_UPDATED: Occupied={$occupied}/{$total} BOR={$bor}%");
    }
}
