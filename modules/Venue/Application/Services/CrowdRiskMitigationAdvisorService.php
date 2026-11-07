<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;

/**
 * Service to aggregate multi-vector risk telemetry (crowd density, weather heat index,
 * sound decibels, alcohol sales velocity) into actionable tactical directives.
 */
class CrowdRiskMitigationAdvisorService
{
    /**
     * Compute composite safety risk index across environmental and operational vectors.
     *
     * @return array{
     *     zone_id: int,
     *     composite_risk_score: float,
     *     threat_level: string,
     *     heat_stress_advisory: string,
     *     acoustic_hazard_advisory: string,
     *     hydration_station_recommendation: bool,
     *     mitigation_steps: array<int, string>
     * }
     */
    public function evaluateCompositeRisk(
        EntertainmentVenue $venue,
        EntertainmentEvent $event,
        EntertainmentZone $zone,
        float $densitySqm,
        float $heatIndexCelsius,
        float $meanDecibels,
        int $drinksSoldPerHour
    ): array {
        $score = 0.0;
        $mitigations = [];

        // Density vector (weight: 40%)
        if ($densitySqm >= 4.5) {
            $score += 40.0;
            $mitigations[] = 'ACTIVATE_EMERGENCY_EGRESS_AND_RESTRICT_INGRESS';
        } elseif ($densitySqm >= 3.5) {
            $score += 25.0;
            $mitigations[] = 'DEPLOY_SECURITY_STANCHIONS_TO_REDIRECT_FLOW';
        } else {
            $score += ($densitySqm / 3.5) * 15.0;
        }

        // Heat Index vector (weight: 25%)
        $heatAdvisory = 'NORMAL';
        if ($heatIndexCelsius >= 38.0) {
            $score += 25.0;
            $heatAdvisory = 'EXTREME_DANGER_HEAT_STROKE';
            $mitigations[] = 'DEPLOY_MISTING_FANS_AND_FREE_WATER_POINTS';
        } elseif ($heatIndexCelsius >= 32.0) {
            $score += 15.0;
            $heatAdvisory = 'HEAT_EXHAUSTION_CAUTION';
            $mitigations[] = 'INCREASE_PARAMEDIC_ROVING_PATROLS';
        }

        // Acoustic vector (weight: 20%)
        $acousticAdvisory = 'SAFE';
        if ($meanDecibels >= 105.0) {
            $score += 20.0;
            $acousticAdvisory = 'SEVERE_HEARING_HAZARD';
            $mitigations[] = 'MANDATE_EARPLUG_DISTRIBUTION_AT_ZONE_ENTRANCE';
        } elseif ($meanDecibels >= 95.0) {
            $score += 10.0;
            $acousticAdvisory = 'ELEVATED_SOUND_EXPOSURE';
        }

        // Alcohol sales velocity vector (weight: 15%)
        if ($drinksSoldPerHour > 300) {
            $score += 15.0;
            $mitigations[] = 'ENFORCE_ALCOHOL_CUTOFF_AND_MANDATORY_WATER_PAIRS';
        } elseif ($drinksSoldPerHour > 150) {
            $score += 8.0;
        }

        $compositeScore = round(min(100.0, $score), 1);

        $threatLevel = 'LOW';
        if ($compositeScore >= 80.0) {
            $threatLevel = 'CODE_RED_CRITICAL';
        } elseif ($compositeScore >= 60.0) {
            $threatLevel = 'CODE_ORANGE_HIGH';
        } elseif ($compositeScore >= 40.0) {
            $threatLevel = 'CODE_YELLOW_ELEVATED';
        }

        return [
            'zone_id' => $zone->id,
            'composite_risk_score' => $compositeScore,
            'threat_level' => $threatLevel,
            'heat_stress_advisory' => $heatAdvisory,
            'acoustic_hazard_advisory' => $acousticAdvisory,
            'hydration_station_recommendation' => ($heatIndexCelsius >= 32.0 || $compositeScore >= 50.0),
            'mitigation_steps' => $mitigations,
        ];
    }
}
