<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\TurnstileDevice;

class VenueTurnstileSimulatorAdapter
{
    /**
     * Mengirim sinyal pembukaan palang gerbang turnstile (actuator solenoid release).
     *
     * @return array{
     *     device_code: string,
     *     latch_released: bool,
     *     unlocked_seconds: int,
     *     beam_broken: bool,
     *     turnstile_telemetry: array<string, mixed>
     * }
     */
    public function pulseGateActuator(
        TurnstileDevice $device,
        string $direction,
        int $unlockTimeoutSeconds = 5
    ): array {
        $device->optical_beam_trips += 1;
        $device->last_heartbeat_at = now();
        $device->save();

        return [
            'device_code' => $device->device_code,
            'latch_released' => true,
            'unlocked_seconds' => $unlockTimeoutSeconds,
            'beam_broken' => true,
            'turnstile_telemetry' => [
                'direction' => strtoupper($direction),
                'solenoid_voltage_mv' => 12150,
                'optical_sensor_status' => 'OK',
                'hardware_serial' => $device->hardware_serial,
                'firmware' => $device->firmware_version,
                'heartbeat' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Memeriksa status kesehatan koneksi turnstile fisik.
     */
    public function pingTurnstile(TurnstileDevice $device): bool
    {
        $device->last_heartbeat_at = now();
        $device->turnstile_status = 'ONLINE';
        $device->save();

        return true;
    }
}
