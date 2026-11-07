<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\ValueObjects\EvacuationSignageDirective;

/**
 * Service to orchestrate multi-zone dynamic egress routing, automated signages,
 * and audio-visual emergency directives across interconnected venue spaces.
 */
class MultiZoneEvacuationOrchestrationService
{
    /**
     * Generate dynamic evacuation directives across all venue sectors.
     *
     * @return array{
     *     incident_id: int,
     *     impacted_zone_id: int,
     *     evacuation_phase: string,
     *     zone_directives: array<int, EvacuationSignageDirective>,
     *     turnstiles_released_count: int,
     *     broadcast_message_id: string,
     *     audio_script: string
     * }
     */
    public function orchestrateVenueWideEvacuation(
        EntertainmentVenue $venue,
        EntertainmentEvent $event,
        CrowdCrushIncident $activeIncident
    ): array {
        $zones = EntertainmentZone::where('venue_id', $venue->id)->get();
        $directives = [];
        $impactedZoneId = (int) $activeIncident->zone_id;

        foreach ($zones as $zone) {
            $isDirectlyAfflicted = ($zone->id === $impactedZoneId);

            if ($isDirectlyAfflicted) {
                // Direct emergency evacuation from afflicted zone
                $directives[$zone->id] = new EvacuationSignageDirective(
                    zoneId: $zone->id,
                    displayColorHex: '#EF4444', // Red
                    arrowDirection: 'EMERGENCY_EXIT_RIGHT',
                    headlineText: 'EVACUATE CALMLY VIA EXIT 4 & 5',
                    flashingRateHz: 2.0,
                    turnstilesUnlocked: true,
                    soundAlarm: true
                );
            } else {
                // Buffer zones redirected away from the incident area to avoid secondary stampede
                $directives[$zone->id] = new EvacuationSignageDirective(
                    zoneId: $zone->id,
                    displayColorHex: '#F59E0B', // Amber
                    arrowDirection: 'HOLD_POSITION_OR_MOVE_SLOWLY',
                    headlineText: 'MAINTAIN DISTANCE FROM MAIN STAGE CHOKEPOINT',
                    flashingRateHz: 0.0,
                    turnstilesUnlocked: false,
                    soundAlarm: false
                );
            }
        }

        $audioScript = 'ATTENTION GUESTS: PLEASE PROCEED TOWARDS THE MARKED GREEN ILLUMINATED EXITS. DO NOT RUN. SECURITY PERSONNEL ARE ASSISTING YOUR EXIT.';

        return [
            'incident_id' => $activeIncident->id,
            'impacted_zone_id' => $impactedZoneId,
            'evacuation_phase' => 'ACTIVE_CONTROLLED_EGRESS',
            'zone_directives' => $directives,
            'turnstiles_released_count' => count($zones) * 4,
            'broadcast_message_id' => sprintf('EVAC-%d-%s', $activeIncident->id, Carbon::now()->format('His')),
            'audio_script' => $audioScript,
        ];
    }

    /**
     * Calculate route diversion penalty to prevent stampede into neighboring choked areas.
     */
    public function calculateSectorDiversionFactor(float $targetSectorDensity, float $sourceSectorDensity): float
    {
        if ($targetSectorDensity >= 4.0) {
            return 999.0; // Blocked: target sector is already over safe density
        }

        $densityDifference = $targetSectorDensity - $sourceSectorDensity;

        return max(1.0, 1.0 + ($densityDifference * 0.5));
    }
}
