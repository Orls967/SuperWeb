<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use DomainException;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Service Optimasi Navigasi Jarak Lingkaran Besar (Great Circle Navigation & Ocean Currents).
 * Standar: Bowditch American Practical Navigator & IMO Navigational Guidance.
 */
final class OceanFleetRouteOptimizationService
{
    // Radius bumi dalam mil laut (Earth mean radius in Nautical Miles)
    public const EARTH_RADIUS_NM = 3440.065;

    /**
     * Hitung jarak lingkaran besar (Great Circle Distance) dan haluan awal (Initial True Course).
     *
     * @param float $lat1 Latitude titik awal (-90 s.d. 90)
     * @param float $lon1 Longitude titik awal (-180 s.d. 180)
     * @param float $lat2 Latitude titik tujuan (-90 s.d. 90)
     * @param float $lon2 Longitude titik tujuan (-180 s.d. 180)
     * @return array{
     *     great_circle_distance_nm: float,
     *     rhumb_line_distance_nm: float,
     *     distance_saved_nm: float,
     *     initial_course_degrees: float,
     *     final_course_degrees: float
     * }
     */
    public function calculateGreatCircleRoute(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): array {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaPhi = deg2rad($lat2 - $lat1);
        $deltaLambda = deg2rad($lon2 - $lon1);

        // 1. Great Circle Distance (Haversine Formula)
        $a = (sin($deltaPhi / 2.0) ** 2.0) +
            (cos($phi1) * cos($phi2) * (sin($deltaLambda / 2.0) ** 2.0));
        $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        $gcDistance = self::EARTH_RADIUS_NM * $c;

        // 2. Initial True Bearing
        $y = sin($deltaLambda) * cos($phi2);
        $x = (cos($phi1) * sin($phi2)) -
            (sin($phi1) * cos($phi2) * cos($deltaLambda));
        $initialBearing = fmod((rad2deg(atan2($y, $x)) + 360.0), 360.0);

        // 3. Final Bearing
        $yFin = sin(-$deltaLambda) * cos($phi1);
        $xFin = (cos($phi2) * sin($phi1)) -
            (sin($phi2) * cos($phi1) * cos(-$deltaLambda));
        $finalBearing = fmod((rad2deg(atan2($yFin, $xFin)) + 180.0 + 360.0), 360.0);

        // 4. Rhumb Line Distance (Mercator Sailing approximation)
        $deltaPsi = log(tan(($phi2 / 2.0) + (M_PI / 4.0)) / tan(($phi1 / 2.0) + (M_PI / 4.0)));
        $q = abs($deltaPsi) > 1e-12 ? ($deltaPhi / $deltaPsi) : cos($phi1);
        $dLon = abs($deltaLambda);
        if ($dLon > M_PI) {
            $dLon = (2.0 * M_PI) - $dLon;
        }
        $rhumbDistance = self::EARTH_RADIUS_NM * sqrt(($deltaPhi ** 2.0) + (($q * $dLon) ** 2.0));

        $savedDistance = max(0.0, $rhumbDistance - $gcDistance);

        return [
            'great_circle_distance_nm' => round($gcDistance, 2),
            'rhumb_line_distance_nm' => round($rhumbDistance, 2),
            'distance_saved_nm' => round($savedDistance, 2),
            'initial_course_degrees' => round($initialBearing, 1),
            'final_course_degrees' => round($finalBearing, 1),
        ];
    }

    /**
     * Hasilkan interpolasi titik lintasan (Waypoints) lingkaran besar sepanjang rute pelayaran.
     *
     * @return array<int, array{sequence: int, latitude: float, longitude: float}>
     */
    public function generateWaypointsAlongTrack(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        int $numberOfIntervals = 5
    ): array {
        $phi1 = deg2rad($lat1);
        $lambda1 = deg2rad($lon1);
        $phi2 = deg2rad($lat2);
        $lambda2 = deg2rad($lon2);

        $deltaPhi = $phi2 - $phi1;
        $deltaLambda = $lambda2 - $lambda1;
        $a = (sin($deltaPhi / 2.0) ** 2.0) + (cos($phi1) * cos($phi2) * (sin($deltaLambda / 2.0) ** 2.0));
        $delta = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        $waypoints = [];

        for ($i = 0; $i <= $numberOfIntervals; $i++) {
            $f = $i / (float) $numberOfIntervals;

            if ($delta < 1e-12) {
                $waypoints[] = [
                    'sequence' => $i + 1,
                    'latitude' => round($lat1, 4),
                    'longitude' => round($lon1, 4),
                ];
                continue;
            }

            $A = sin((1.0 - $f) * $delta) / sin($delta);
            $B = sin($f * $delta) / sin($delta);

            $x = ($A * cos($phi1) * cos($lambda1)) + ($B * cos($phi2) * cos($lambda2));
            $y = ($A * cos($phi1) * sin($lambda1)) + ($B * cos($phi2) * sin($lambda2));
            $z = ($A * sin($phi1)) + ($B * sin($phi2));

            $phiI = atan2($z, sqrt(($x ** 2.0) + ($y ** 2.0)));
            $lambdaI = atan2($y, $x);

            $waypoints[] = [
                'sequence' => $i + 1,
                'latitude' => round(rad2deg($phiI), 4),
                'longitude' => round(rad2deg($lambdaI), 4),
            ];
        }

        return $waypoints;
    }

    /**
     * Hitung dampak arus laut (Indonesian Throughflow / Arlindo atau Agulhas) terhadap kecepatan kapal (Speed Made Good / SMG).
     */
    public function evaluateOceanCurrentVector(
        float $engineSpeedKnots,
        float $vesselHeadingDegrees,
        float $currentSpeedKnots,
        float $currentDirectionDegrees
    ): array {
        $vesselHeadingRad = deg2rad($vesselHeadingDegrees);
        $currentDirRad = deg2rad($currentDirectionDegrees);

        // Komponen vektor kapal
        $vx = $engineSpeedKnots * sin($vesselHeadingRad);
        $vy = $engineSpeedKnots * cos($vesselHeadingRad);

        // Komponen vektor arus laut
        $cx = $currentSpeedKnots * sin($currentDirRad);
        $cy = $currentSpeedKnots * cos($currentDirRad);

        // Vektor resultan di atas tanah (Over Ground)
        $rgx = $vx + $cx;
        $rgy = $vy + $cy;

        $speedMadeGood = sqrt(($rgx ** 2.0) + ($rgy ** 2.0));
        $courseMadeGood = fmod((rad2deg(atan2($rgx, $rgy)) + 360.0), 360.0);
        $speedDelta = $speedMadeGood - $engineSpeedKnots;

        return [
            'engine_speed_knots' => round($engineSpeedKnots, 2),
            'current_speed_knots' => round($currentSpeedKnots, 2),
            'speed_made_good_knots' => round($speedMadeGood, 2),
            'course_made_good_degrees' => round($courseMadeGood, 1),
            'speed_gain_or_loss_knots' => round($speedDelta, 2),
            'effect' => $speedDelta > 0.1 ? 'FAVORABLE_CURRENT' : ($speedDelta < -0.1 ? 'ADVERSE_CURRENT' : 'NEUTRAL'),
        ];
    }
}
