<?php

declare(strict_types=1);

namespace Modules\Tlx\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Tlx\Contracts\IotDeviceRegistry;
use Modules\Tlx\Domain\Models\IotDevice;
use Modules\Tlx\Domain\Models\SimProfile;

class IotDeviceRegistryService implements IotDeviceRegistry
{
    /**
     * Mendaftarkan perangkat IoT baru ke registry tunggal.
     */
    public function registerDevice(array $payload): array
    {
        if (empty($payload['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency key is required for device registration.');
        }

        if (empty($payload['device_code'])) {
            throw new InvalidArgumentException('Device code is required.');
        }

        if (empty($payload['device_type'])) {
            throw new InvalidArgumentException('Device type is required.');
        }

        if (empty($payload['owner_entity_id'])) {
            throw new InvalidArgumentException('Owner entity id is required.');
        }

        // Idempotency check by idempotency_key
        $existing = IotDevice::where('idempotency_key', $payload['idempotency_key'])->first();
        if ($existing) {
            return $existing->toArray();
        }

        // Also check if device_code already registered
        $existingCode = IotDevice::where('device_code', $payload['device_code'])->first();
        if ($existingCode) {
            return $existingCode->toArray();
        }

        $device = IotDevice::create([
            'id' => (string) Str::uuid(),
            'device_uuid' => (string) Str::uuid(),
            'device_code' => $payload['device_code'],
            'device_type' => $payload['device_type'],
            'owner_entity_id' => $payload['owner_entity_id'],
            'using_module' => $payload['using_module'] ?? null,
            'monthly_fee_minor' => (int) ($payload['monthly_fee_minor'] ?? 0),
            'idempotency_key' => $payload['idempotency_key'],
            'status' => 'PROVISIONED',
        ]);

        return $device->fresh()->toArray();
    }

    /**
     * Mengaktifkan profil SIM pada perangkat IoT tertentu.
     */
    public function activateSim(string $deviceCode, string $iccid, ?string $idempotencyKey = null): array
    {
        return DB::transaction(function () use ($deviceCode, $iccid) {
            $device = IotDevice::where('device_code', $deviceCode)->lockForUpdate()->first();
            if (! $device) {
                throw new InvalidArgumentException("Device with code [{$deviceCode}] not found.");
            }

            if ($device->status === 'RETIRED') {
                throw new InvalidArgumentException("Cannot activate SIM on retired device [{$deviceCode}].");
            }

            // Check if device already has a different active SIM
            if ($device->active_sim_iccid !== null && $device->active_sim_iccid !== $iccid && $device->status === 'ACTIVE') {
                throw new InvalidArgumentException("Device [{$deviceCode}] already has an active SIM [{$device->active_sim_iccid}]. Only one active SIM allowed.");
            }

            $sim = SimProfile::where('iccid', $iccid)->lockForUpdate()->first();
            if (! $sim) {
                throw new InvalidArgumentException("SIM profile with ICCID [{$iccid}] not found.");
            }

            if ($sim->status === 'ALLOCATED' && $sim->assigned_device_id !== $device->id) {
                throw new InvalidArgumentException("SIM [{$iccid}] is already allocated to another device.");
            }

            $sim->update([
                'status' => 'ALLOCATED',
                'assigned_device_id' => $device->id,
            ]);

            $device->update([
                'status' => 'ACTIVE',
                'active_sim_iccid' => $iccid,
                'activated_at' => now(),
            ]);

            return $device->fresh()->toArray();
        });
    }

    /**
     * Menangguhkan perangkat (suspension).
     */
    public function suspendDevice(string $deviceCode, string $reason, ?string $idempotencyKey = null): array
    {
        return DB::transaction(function () use ($deviceCode) {
            $device = IotDevice::where('device_code', $deviceCode)->lockForUpdate()->first();
            if (! $device) {
                throw new InvalidArgumentException("Device [{$deviceCode}] not found.");
            }

            if ($device->status === 'RETIRED') {
                throw new InvalidArgumentException("Cannot suspend retired device [{$deviceCode}].");
            }

            $device->update([
                'status' => 'SUSPENDED',
                'suspended_at' => now(),
            ]);

            return $device->fresh()->toArray();
        });
    }

    /**
     * Memensiunkan perangkat (retirement).
     */
    public function retireDevice(string $deviceCode, ?string $idempotencyKey = null): array
    {
        return DB::transaction(function () use ($deviceCode) {
            $device = IotDevice::where('device_code', $deviceCode)->lockForUpdate()->first();
            if (! $device) {
                throw new InvalidArgumentException("Device [{$deviceCode}] not found.");
            }

            if ($device->active_sim_iccid) {
                SimProfile::where('iccid', $device->active_sim_iccid)->update([
                    'status' => 'AVAILABLE',
                    'assigned_device_id' => null,
                ]);
            }

            $device->update([
                'status' => 'RETIRED',
                'retired_at' => now(),
            ]);

            return $device->fresh()->toArray();
        });
    }

    /**
     * Mencari perangkat berdasarkan device_code atau ICCID SIM.
     */
    public function findDevice(string $identifier): ?array
    {
        $device = IotDevice::where('device_code', $identifier)
            ->orWhere('active_sim_iccid', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        return $device ? $device->toArray() : null;
    }

    /**
     * Memvalidasi bahwa perangkat aktif pada timestamp tertentu.
     */
    public function assertActive(string $deviceCode, CarbonImmutable $at): bool
    {
        $device = IotDevice::where('device_code', $deviceCode)->first();
        if (! $device) {
            return false;
        }

        return $device->status === 'ACTIVE';
    }
}
