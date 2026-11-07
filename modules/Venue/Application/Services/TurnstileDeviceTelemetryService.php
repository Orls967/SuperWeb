<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Venue\Domain\Models\TurnstileDevice;

final class TurnstileDeviceTelemetryService
{
    /**
     * Process hardware health telemetry from turnstile controller.
     *
     * @return array{
     *     device_code: string,
     *     status: string,
     *     is_online: bool,
     *     requires_maintenance: bool,
     *     action_taken: string
     * }
     */
    public function recordDeviceHealth(
        string $deviceCode,
        int $batteryLevelPercent,
        int $motorTorqueErrorCount,
        bool $opticalSensorObstructed
    ): array {
        $device = TurnstileDevice::where('device_code', $deviceCode)->first();
        if ($device === null) {
            $device = TurnstileDevice::create([
                'device_code' => $deviceCode,
                'device_name' => "Turnstile Lane {$deviceCode}",
                'ip_address' => '192.168.10.101',
                'zone_id' => 1,
                'direction' => 'BIDIRECTIONAL',
                'mode' => 'NORMAL',
                'is_active' => true,
                'is_locked' => false,
            ]);
        }

        $device->last_heartbeat_at = CarbonImmutable::now();

        $requiresMaintenance = false;
        $action = 'Perangkat normal';

        if ($motorTorqueErrorCount > 3 || $opticalSensorObstructed) {
            $requiresMaintenance = true;
            $device->is_active = false;
            $device->mode = 'MAINTENANCE_LOCK';
            $action = 'Kegagalan motor/sensor terdeteksi: Lane dimatikan untuk servis keselamatan.';
        } elseif ($batteryLevelPercent < 20) {
            $action = 'Peringatan baterai cadangan rendah (<20%). Hubungkan UPS.';
        }

        $device->save();

        return [
            'device_code' => $deviceCode,
            'status' => $device->mode,
            'is_online' => true,
            'requires_maintenance' => $requiresMaintenance,
            'action_taken' => $action,
        ];
    }
}
