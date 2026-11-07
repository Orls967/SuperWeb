<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Contracts\VenueZoneSurveillanceFeedGateway;

final class VenueZoneSurveillanceFeedService implements VenueZoneSurveillanceFeedGateway
{
    public function getZoneTelemetrySnapshot(int $venueId, int $zoneId): array
    {
        $now = time();
        $hashPayload = sprintf('SURV-FEED-%d-%d-%d', $venueId, $zoneId, $now);
        $hash = hash_hmac('sha256', $hashPayload, 'FEED_GATEWAY_SALT_2026');

        return [
            'venue_id' => $venueId,
            'zone_id' => $zoneId,
            'optical_headcount' => 1250,
            'active_camera_nodes' => 8,
            'lidar_points_per_sec' => 300000,
            'tamper_evident_hash' => $hash,
            'timestamp' => $now,
        ];
    }
}
