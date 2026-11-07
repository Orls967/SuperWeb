<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;

final class VenuePerimeterSecurityDispatchService
{
    /**
     * Dispatch perimeter security guard patrols upon fence beam break or perimeter alarm.
     *
     * @return array{
     *     dispatch_id: string,
     *     sector_code: string,
     *     assigned_patrol_unit: string,
     *     radio_channel: string,
     *     priority: string,
     *     dispatch_time: string
     * }
     */
    public function dispatchPerimeterIncident(
        string $sectorCode,
        string $alarmType,
        string $severity = 'HIGH'
    ): array {
        $dispatchId = 'DISP-' . strtoupper(bin2hex(random_bytes(4)));

        $unit = match ($sectorCode) {
            'SECTOR_NORTH_VIP' => 'ALPHA-PATROL-1',
            'SECTOR_EAST_STAGE' => 'BRAVO-PATROL-2',
            'SECTOR_WEST_BEACH' => 'CHARLIE-MARINE-1',
            default => 'DELTA-RAPID-RESPONSE',
        };

        $channel = match ($severity) {
            'CRITICAL' => 'VHF-CH-01-TACTICAL',
            'HIGH' => 'VHF-CH-02-OPERATIONS',
            default => 'VHF-CH-03-LOGISTICS',
        };

        return [
            'dispatch_id' => $dispatchId,
            'sector_code' => $sectorCode,
            'assigned_patrol_unit' => $unit,
            'radio_channel' => $channel,
            'priority' => $severity,
            'dispatch_time' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
