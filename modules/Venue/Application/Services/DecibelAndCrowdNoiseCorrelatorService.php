<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\ValueObjects\AcousticNoiseTelemetry;

/**
 * Service to correlate acoustic noise sensor data (dB SPL) with crowd movement dynamics.
 * Detects sudden volume surges (mosh pit precursors, crowd distress screams)
 * and verifies compliance with occupational noise health and safety regulations.
 */
class DecibelAndCrowdNoiseCorrelatorService
{
    /**
     * Thresholds in decibels (A-weighted SPL)
     */
    public const NOISE_LEVEL_NORMAL_DB = 85.0;

    public const NOISE_LEVEL_HIGH_DB = 100.0;

    public const NOISE_LEVEL_CRITICAL_DB = 115.0;

    public const SURGE_VELOCITY_DB_PER_SEC = 6.0;

    /**
     * Analyze acoustic sensor telemetry against zone geometry and crowd density.
     *
     * @param  array<int, array{timestamp: int, decibel_spl: float, frequency_hz: float, sensor_id: string}>  $sensorReadings
     * @return array{
     *     zone_id: int,
     *     mean_decibel_spl: float,
     *     peak_decibel_spl: float,
     *     surge_detected: bool,
     *     surge_risk_rating: string,
     *     noise_exposure_status: string,
     *     mosh_pit_probability: float,
     *     advisory_action: string,
     *     telemetry: AcousticNoiseTelemetry
     * }
     */
    public function analyzeZoneAcoustics(
        EntertainmentZone $zone,
        EntertainmentEvent $event,
        array $sensorReadings
    ): array {
        if (empty($sensorReadings)) {
            $telemetry = new AcousticNoiseTelemetry(0.0, 0.0, 0.0, 'SENSOR_OFFLINE');

            return [
                'zone_id' => $zone->id,
                'mean_decibel_spl' => 0.0,
                'peak_decibel_spl' => 0.0,
                'surge_detected' => false,
                'surge_risk_rating' => 'LOW',
                'noise_exposure_status' => 'SAFE',
                'mosh_pit_probability' => 0.0,
                'advisory_action' => 'CHECK_SENSOR_CONNECTIVITY',
                'telemetry' => $telemetry,
            ];
        }

        $totalDb = 0.0;
        $peakDb = 0.0;
        $count = count($sensorReadings);
        $previousReading = null;
        $maxRateOfChange = 0.0;

        foreach ($sensorReadings as $reading) {
            $db = (float) $reading['decibel_spl'];
            $totalDb += $db;
            if ($db > $peakDb) {
                $peakDb = $db;
            }

            if ($previousReading !== null) {
                $timeDelta = max(1, $reading['timestamp'] - $previousReading['timestamp']);
                $dbDelta = abs($db - $previousReading['decibel_spl']);
                $rate = $dbDelta / $timeDelta;
                if ($rate > $maxRateOfChange) {
                    $maxRateOfChange = $rate;
                }
            }
            $previousReading = $reading;
        }

        $meanDb = round($totalDb / $count, 2);
        $surgeDetected = ($maxRateOfChange >= self::SURGE_VELOCITY_DB_PER_SEC) || ($peakDb >= self::NOISE_LEVEL_CRITICAL_DB);

        // Mosh pit heuristic: High decibel + rapid fluctuations in low-frequency band (sub-bass / kick)
        $moshPitScore = 0.0;
        if ($meanDb >= 95.0 && $maxRateOfChange > 4.0) {
            $moshPitScore = min(0.98, 0.40 + ($maxRateOfChange * 0.08) + (($meanDb - 95.0) * 0.02));
        } elseif ($meanDb >= 90.0) {
            $moshPitScore = min(0.60, 0.20 + (($meanDb - 90.0) * 0.03));
        }

        $riskRating = 'LOW';
        if ($peakDb >= self::NOISE_LEVEL_CRITICAL_DB || $moshPitScore > 0.80) {
            $riskRating = 'CRITICAL';
        } elseif ($peakDb >= self::NOISE_LEVEL_HIGH_DB || $moshPitScore > 0.50) {
            $riskRating = 'ELEVATED';
        }

        $exposureStatus = 'SAFE';
        if ($meanDb > 105.0) {
            $exposureStatus = 'HAZARDOUS_LIMIT_EXCEEDED';
        } elseif ($meanDb > 95.0) {
            $exposureStatus = 'WARNING_EAR_PROTECTION_RECOMMENDED';
        }

        $advisory = 'NORMAL_MONITORING';
        if ($riskRating === 'CRITICAL') {
            $advisory = 'DISPATCH_STAGE_SECURITY_AND_ATTENUATE_FRONT_FILLS';
        } elseif ($riskRating === 'ELEVATED') {
            $advisory = 'ALERT_PIT_MARSHALS';
        }

        $telemetryObj = new AcousticNoiseTelemetry(
            $meanDb,
            $peakDb,
            $maxRateOfChange,
            $riskRating
        );

        return [
            'zone_id' => $zone->id,
            'mean_decibel_spl' => $meanDb,
            'peak_decibel_spl' => $peakDb,
            'surge_detected' => $surgeDetected,
            'surge_risk_rating' => $riskRating,
            'noise_exposure_status' => $exposureStatus,
            'mosh_pit_probability' => round($moshPitScore, 3),
            'advisory_action' => $advisory,
            'telemetry' => $telemetryObj,
        ];
    }

    /**
     * Compute cumulative noise dose percentage for stage barrier staff.
     * Based on NIOSH 85 dBA recommended exposure limit with 3 dB exchange rate.
     */
    public function computeWorkerNoiseDose(float $exposureHours, float $meanDecibels): float
    {
        if ($exposureHours <= 0.0 || $meanDecibels <= 0.0) {
            return 0.0;
        }

        // T = 8 / (2 ^ ((L - 85) / 3))
        $exponent = ($meanDecibels - 85.0) / 3.0;
        $maxAllowedHours = 8.0 / pow(2.0, $exponent);

        $dosePercentage = ($exposureHours / $maxAllowedHours) * 100.0;

        return round(min(999.9, $dosePercentage), 2);
    }
}
