<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * G7-K24: Kontrak Aditif Telemetri Pengawasan Optik & Sensor Kepadatan Zona.
 */
interface VenueZoneSurveillanceFeedGateway
{
    /**
     * Dapatkan snapshot telemetri sensor kepadatan zona (LiDAR/CCTV optik) tanpa data video mentah.
     *
     * @return array{
     *   venue_id: int,
     *   zone_id: int,
     *   optical_headcount: int,
     *   active_camera_nodes: int,
     *   lidar_points_per_sec: int,
     *   tamper_evident_hash: string,
     *   timestamp: int
     * }
     */
    public function getZoneTelemetrySnapshot(int $venueId, int $zoneId): array;
}
