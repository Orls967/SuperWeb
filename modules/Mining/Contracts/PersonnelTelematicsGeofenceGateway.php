<?php

declare(strict_types=1);

namespace Modules\Mining\Contracts;

/**
 * Kontrak G7-K13: Gateway Telematika GPS & Geofence Personel Tambang.
 * Menyediakan pemindaian posisi real-time pekerja dan armada di zona bahaya.
 */
interface PersonnelTelematicsGeofenceGateway
{
    /**
     * Memindai seluruh personel aktif di dalam radius lingkaran bahaya tertentu.
     *
     * @return list<array{worker_id: string, name: string, lat: float, lng: float, distance_meters: float}>
     */
    public function scanPersonnelInRadius(
        float $centerLat,
        float $centerLng,
        float $radiusMeters
    ): array;

    /**
     * Memeriksa apakah radius perimeter peledakan telah steril 100%.
     */
    public function isPerimeterClear(
        float $centerLat,
        float $centerLng,
        float $radiusMeters
    ): bool;
}
