<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Modules\Mall\Domain\Enums\OvertimeStatus;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\OvertimeRequest;

class RequestOvertimeAction
{
    public function execute(
        Lease $lease,
        string $date,
        string $startTime,
        string $endTime,
        float $hours,
        string $reason,
        int $ratePerHour = 250000
    ): OvertimeRequest {
        $totalCost = (int) round($hours * $ratePerHour);

        return OvertimeRequest::create([
            'lease_id' => $lease->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'hours' => $hours,
            'rate_per_hour' => $ratePerHour,
            'total_cost' => $totalCost,
            'reason' => $reason,
            'status' => OvertimeStatus::REQUESTED,
        ]);
    }
}
