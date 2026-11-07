<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Contracts\PersonnelTelematicsGeofenceGateway;
use Modules\Mining\Domain\Models\MiningWorkPermit;

/**
 * Layanan implementasi G7-K13: Gateway Telematika GPS & Geofence Personel Tambang.
 * Mengintegrasikan izin kerja aktif dan simulasi radar GPS pekerja / armada tambang.
 */
class PersonnelTelematicsGeofenceGatewayService implements PersonnelTelematicsGeofenceGateway
{
    /**
     * Memindai seluruh personel aktif di dalam radius bahaya tertentu.
     * Menggunakan rumus Haversine untuk menghitung jarak geospasial real-time.
     *
     * @return list<array{worker_id: string, name: string, lat: float, lng: float, distance_meters: float}>
     */
    public function scanPersonnelInRadius(
        float $centerLat,
        float $centerLng,
        float $radiusMeters
    ): array {
        $activePermits = MiningWorkPermit::query()
            ->whereIn('approval_status', ['APPROVED', 'ISSUED', 'ACTIVE'])
            ->whereNotNull('allowed_latitude')
            ->whereNotNull('allowed_longitude')
            ->get();

        $breachedWorkers = [];

        foreach ($activePermits as $permit) {
            $lat = (float) $permit->allowed_latitude;
            $lng = (float) $permit->allowed_longitude;

            $distance = $this->calculateHaversineDistanceMeters($centerLat, $centerLng, $lat, $lng);

            if ($distance <= $radiusMeters) {
                $breachedWorkers[] = [
                    'worker_id' => (string) ($permit->worker_party_id ?? $permit->id),
                    'name' => 'Worker Permit '.$permit->permit_number,
                    'lat' => $lat,
                    'lng' => $lng,
                    'distance_meters' => round($distance, 2),
                ];
            }
        }

        return $breachedWorkers;
    }

    /**
     * Memeriksa apakah perimeter peledakan telah steril sepenuhnya (0 personel di zona bahaya).
     */
    public function isPerimeterClear(
        float $centerLat,
        float $centerLng,
        float $radiusMeters
    ): bool {
        $personnel = $this->scanPersonnelInRadius($centerLat, $centerLng, $radiusMeters);

        return count($personnel) === 0;
    }

    /**
     * Menghitung jarak geodesic Great-Circle antara dua koordinat (meter) via Haversine.
     */
    public function calculateHaversineDistanceMeters(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000.0; // Meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
