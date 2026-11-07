<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

use Carbon\CarbonImmutable;

/**
 * Adaptor Integrasi Core DigitalTwinInterface untuk status twin kamar hotel.
 */
final class CoreDigitalTwinAdapter
{
    /**
     * @param  array<string, mixed>  $telemetry
     * @return array{twin_id: string, sync_status: string, updated_at: string}
     */
    public static function syncRoomTwin(int $roomId, array $telemetry): array
    {
        return [
            'twin_id' => "TWIN-HTL-RM-{$roomId}",
            'sync_status' => 'SYNCHRONIZED',
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
