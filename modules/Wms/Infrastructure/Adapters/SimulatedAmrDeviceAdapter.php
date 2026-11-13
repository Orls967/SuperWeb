<?php

declare(strict_types=1);

namespace Modules\Wms\Infrastructure\Adapters;

use Modules\Wms\Contracts\SimulatedDeviceAdapterInterface;
use Modules\Wms\Models\Robot;
use RuntimeException;

/**
 * Implementasi tiruan perangkat AMR untuk simulasi deterministik (IZIN-5, §A4.5).
 */
final class SimulatedAmrDeviceAdapter implements SimulatedDeviceAdapterInterface
{
    public function __construct(
        private bool $forceFailure = false,
    ) {}

    public function setForceFailure(bool $forceFailure): void
    {
        $this->forceFailure = $forceFailure;
    }

    public function isSimulated(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{status: string, simulated: bool, device_code: string, ack_code: string}
     */
    public function sendCommand(string $deviceCode, string $command, array $parameters = []): array
    {
        if ($this->forceFailure) {
            throw new RuntimeException("simulated_device_fault: Perintah {$command} ke {$deviceCode} gagal (simulasi).");
        }

        return [
            'status' => 'simulated_ack',
            'simulated' => true,
            'device_code' => $deviceCode,
            'ack_code' => substr(hash('sha256', "{$deviceCode}|{$command}|".json_encode($parameters)), 0, 16),
        ];
    }

    /**
     * @return array{device_code: string, battery_pct: int, error_count: int, online: bool, simulated: bool}
     */
    public function pollTelemetry(string $deviceCode): array
    {
        if ($this->forceFailure) {
            return [
                'device_code' => $deviceCode,
                'battery_pct' => 5,
                'error_count' => 4,
                'online' => false,
                'simulated' => true,
            ];
        }

        $robot = Robot::query()->where('robot_code', $deviceCode)->first();

        return [
            'device_code' => $deviceCode,
            'battery_pct' => $robot !== null ? (int) $robot->battery_pct : 85,
            'error_count' => 0,
            'online' => $robot !== null ? (bool) $robot->is_online : true,
            'simulated' => true,
        ];
    }

    public function triggerEmergencyStop(string $zoneCode, string $reason): bool
    {
        return ! $this->forceFailure;
    }
}
