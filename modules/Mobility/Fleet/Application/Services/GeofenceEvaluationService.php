<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Application\Services;

use Modules\Mobility\Fleet\Domain\Models\GeofenceZone;
use Modules\Shared\Domain\Algorithms\Geo\PointInPolygon;

class GeofenceEvaluationService
{
    public function __construct(
        private readonly PointInPolygon $pip = new PointInPolygon
    ) {}

    public function isInsideZone(GeofenceZone $zone, int $latE6, int $lonE6): bool
    {
        // Bounding box filter
        if ($latE6 < $zone->min_lat_e6 || $latE6 > $zone->max_lat_e6 ||
            $lonE6 < $zone->min_lon_e6 || $lonE6 > $zone->max_lon_e6) {
            return false;
        }

        $polygon = [];
        foreach ($zone->polygon_json as $vertex) {
            $polygon[] = [
                'x' => (int) ($vertex['lon'] ?? $vertex['x']),
                'y' => (int) ($vertex['lat'] ?? $vertex['y']),
            ];
        }

        $point = ['x' => $lonE6, 'y' => $latE6];

        return $this->pip->contains($polygon, $point);
    }

    public function evaluateTransition(
        GeofenceZone $zone,
        int $prevLatE6,
        int $prevLonE6,
        int $newLatE6,
        int $newLonE6
    ): string {
        $wasInside = $this->isInsideZone($zone, $prevLatE6, $prevLonE6);
        $isInside = $this->isInsideZone($zone, $newLatE6, $newLonE6);

        if (! $wasInside && $isInside) {
            return 'ENTERED';
        }

        if ($wasInside && ! $isInside) {
            return 'EXITED';
        }

        return $isInside ? 'INSIDE' : 'OUTSIDE';
    }
}
