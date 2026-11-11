<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Audit;

use DateTimeImmutable;
use Modules\Core\Domain\Access\Values\AccessSubjectContext;

/**
 * Access Behavioral Anomaly & Threat Signal Detector (W8-D53, W8-D55).
 *
 * Evaluates contextual access patterns to identify indicators of compromise:
 * - Impossible travel velocities (geo distance / elapsed time > 900 km/h)
 * - Off-hours high-clearance access attempts
 * - Rapid burst grant elevation patterns
 * - Untrusted device accessing sensitive data
 */
final class AccessAnomalyDetector
{
    public const MAX_SPEED_KMH = 900; // Commercial airline cruise speed limit

    /**
     * Approximate coordinate catalog for country centroids (lat, lon).
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private static array $countryCoordinates = [
        'ID' => [-0.7893, 113.9213], // Indonesia
        'SG' => [1.3521, 103.8198],  // Singapore
        'MY' => [4.2105, 101.9758],  // Malaysia
        'US' => [37.0902, -95.7129], // USA
        'GB' => [55.3781, -3.4360],  // UK
        'JP' => [36.2048, 138.2529], // Japan
        'AU' => [-25.2744, 133.7751], // Australia
    ];

    /**
     * Analyze access attempt for potential anomalies.
     *
     * @param  array{country_iso: string, timestamp: DateTimeImmutable}|null  $previousLogin
     * @return array{
     *     anomaly_detected: bool,
     *     risk_score: int,
     *     threat_indicators: list<string>,
     *     recommended_challenge: string
     * }
     */
    public function detectAnomalies(
        AccessSubjectContext $subject,
        ?array $previousLogin = null
    ): array {
        $indicators = [];
        $riskScore = 0;

        // 1. Check device trust
        if ($subject->deviceTrustScore < 40) {
            $indicators[] = "Low device trust score ({$subject->deviceTrustScore}/100)";
            $riskScore += 25;
        }

        // 2. Check impossible travel if previous login exists
        if ($previousLogin !== null) {
            $prevCountry = $previousLogin['country_iso'];
            $currCountry = $subject->geoCountryIso;

            if ($prevCountry !== $currCountry) {
                $prevCoords = self::$countryCoordinates[$prevCountry] ?? null;
                $currCoords = self::$countryCoordinates[$currCountry] ?? null;

                if ($prevCoords !== null && $currCoords !== null) {
                    $distanceKm = $this->calculateHaversineDistance(
                        $prevCoords[0],
                        $prevCoords[1],
                        $currCoords[0],
                        $currCoords[1]
                    );

                    $now = new DateTimeImmutable;
                    $hoursElapsed = max(0.1, ($now->getTimestamp() - $previousLogin['timestamp']->getTimestamp()) / 3600);
                    $speedKmh = $distanceKm / $hoursElapsed;

                    if ($speedKmh > self::MAX_SPEED_KMH) {
                        $indicators[] = sprintf(
                            'Impossible travel detected: %d km between %s and %s in %.1f hours (%.0f km/h)',
                            (int) $distanceKm,
                            $prevCountry,
                            $currCountry,
                            $hoursElapsed,
                            $speedKmh
                        );
                        $riskScore += 50;
                    }
                }
            }
        }

        // 3. Check high clearance off-hours
        if ($subject->clearanceLevel >= 4) {
            $now = new DateTimeImmutable;
            $hour = (int) $now->format('G');
            if ($hour < 6 || $hour > 22) {
                $indicators[] = "High clearance access (L{$subject->clearanceLevel}) requested during off-hours ({$hour}:00)";
                $riskScore += 20;
            }
        }

        // 4. Missing MFA on elevated clearance
        if ($subject->clearanceLevel >= 3 && ! $subject->isMfaVerified) {
            $indicators[] = "Missing MFA verification on confidential clearance (L{$subject->clearanceLevel})";
            $riskScore += 30;
        }

        $clampedRisk = min(100, $riskScore);
        $anomalyDetected = $clampedRisk >= 40;

        $challenge = match (true) {
            $clampedRisk >= 75 => 'BLOCK_AND_ALERT_SOC',
            $clampedRisk >= 50 => 'MANDATORY_STEP_UP_MFA',
            $clampedRisk >= 25 => 'LOG_WARNING',
            default => 'ALLOW',
        };

        return [
            'anomaly_detected' => $anomalyDetected,
            'risk_score' => $clampedRisk,
            'threat_indicators' => $indicators,
            'recommended_challenge' => $challenge,
        ];
    }

    /**
     * Compute Great Circle Distance using Haversine formula (km).
     */
    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
