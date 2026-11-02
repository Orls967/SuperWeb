<?php

declare(strict_types=1);

namespace Modules\Tlx\Contracts;

use Carbon\CarbonImmutable;

interface IotDeviceRegistry
{
    /**
     * Mendaftarkan perangkat IoT baru ke registry tunggal.
     *
     * @param  array{device_code: string, device_type: string, owner_entity_id: string, using_module?: string, monthly_fee_minor?: int, idempotency_key: string}  $payload
     * @return array<string, mixed>
     */
    public function registerDevice(array $payload): array;

    /**
     * Mengaktifkan profil SIM pada perangkat IoT tertentu.
     *
     * @return array<string, mixed>
     */
    public function activateSim(string $deviceCode, string $iccid, ?string $idempotencyKey = null): array;

    /**
     * Menangguhkan perangkat (suspension).
     *
     * @return array<string, mixed>
     */
    public function suspendDevice(string $deviceCode, string $reason, ?string $idempotencyKey = null): array;

    /**
     * Memensiunkan perangkat (retirement).
     *
     * @return array<string, mixed>
     */
    public function retireDevice(string $deviceCode, ?string $idempotencyKey = null): array;

    /**
     * Mencari perangkat berdasarkan device_code atau ICCID SIM.
     *
     * @return array<string, mixed>|null
     */
    public function findDevice(string $identifier): ?array;

    /**
     * Memvalidasi bahwa perangkat aktif pada timestamp tertentu (dipakai modul lain/patch).
     */
    public function assertActive(string $deviceCode, CarbonImmutable $at): bool;
}
