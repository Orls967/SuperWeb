<?php

declare(strict_types=1);

namespace Modules\Wms\Contracts;

/**
 * Kontrak antarmuka perangkat robotik SIMULASI (IZIN-5, U-7).
 *
 * Tidak terhubung ke PLC, WCS, atau robot fisik nyata.
 */
interface SimulatedDeviceAdapterInterface
{
    public function isSimulated(): bool;

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{status: string, simulated: bool, device_code: string, ack_code: string}
     */
    public function sendCommand(string $deviceCode, string $command, array $parameters = []): array;

    /**
     * @return array{device_code: string, battery_pct: int, error_count: int, online: bool, simulated: bool}
     */
    public function pollTelemetry(string $deviceCode): array;

    public function triggerEmergencyStop(string $zoneCode, string $reason): bool;
}
