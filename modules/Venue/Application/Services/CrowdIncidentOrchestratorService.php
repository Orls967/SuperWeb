<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Hospital\Contracts\MedicalEmergencyResponseDispatcher;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\StateMachines\CrowdIncidentStateMachine;
use Modules\Venue\Domain\ValueObjects\IncidentEgressRoute;

/**
 * Service to orchestrate crowd safety incidents, gate diversions, and medical dispatches.
 */
class CrowdIncidentOrchestratorService
{
    public function __construct(
        protected MedicalEmergencyResponseDispatcher $medicalDispatcher,
        protected ZoneEmergencyEgressPathfinderService $pathfinderService,
        protected TurnstileHardwareControllerService $turnstileHardwareService
    ) {}

    /**
     * Declare and escalate a crowd safety incident.
     */
    public function reportAndTriageIncident(
        EntertainmentVenue $venue,
        EntertainmentEvent $event,
        EntertainmentZone $zone,
        float $densitySqm,
        string $reportedByRole,
        int $reportedByUserId
    ): CrowdCrushIncident {
        $stateMachine = CrowdIncidentStateMachine::initial();

        // Calculate severity rating
        $severity = 'LOW';
        if ($densitySqm >= 5.0) {
            $severity = 'CRITICAL';
        } elseif ($densitySqm >= 4.0) {
            $severity = 'HIGH';
        } elseif ($densitySqm >= 3.0) {
            $severity = 'MODERATE';
        }

        $incidentCode = sprintf('CRUSH-%d-%d-%s', $event->id, $zone->id, strtoupper(substr(md5($zone->id.microtime()), 0, 6)));

        $incident = CrowdCrushIncident::create([
            'event_id' => $event->id,
            'zone_id' => $zone->id,
            'incident_code' => $incidentCode,
            'risk_level' => $severity,
            'headcount_at_incident' => (int) ($densitySqm * 250),
            'evacuation_corridor' => 'CORRIDOR-EAST',
            'reported_by_user_id' => $reportedByUserId,
            'status' => $stateMachine->getState(),
            'ambulance_dispatched' => ($severity === 'CRITICAL'),
            'lockdown_initiated_at' => ($severity === 'CRITICAL' ? Carbon::now() : null),
        ]);

        // Auto transition state machine based on risk
        if ($severity === 'CRITICAL' || $severity === 'HIGH') {
            $stateMachine = $stateMachine->transition('CONFIRM_SURGE');
            $incident->update([
                'status' => $stateMachine->getState(),
                'evacuation_corridor' => 'CORRIDOR-EAST-EXPANDED',
            ]);

            // Dispatch medical if CRITICAL
            if ($severity === 'CRITICAL') {
                $this->medicalDispatcher->dispatchFirstResponders(
                    zoneIdentifier: $zone->name,
                    triageSeverity: 'CRITICAL',
                    estimatedCasualties: 0
                );
            }

            // Release emergency egress turnstiles to fail-safe free rotation
            $this->turnstileHardwareService->setTurnstileMode('GATE-ALL-EMERGENCY', 'FAIL_SAFE_FREE');
        }

        return $incident;
    }

    /**
     * Resolve incident and return venue to normal operations.
     */
    public function resolveIncident(CrowdCrushIncident $incident, int $resolverUserId, string $notes): CrowdCrushIncident
    {
        $stateMachine = new CrowdIncidentStateMachine($incident->status);

        if ($stateMachine->getState() !== 'RESOLVED' && $stateMachine->getState() !== 'CLOSED') {
            if ($stateMachine->canTransition('RESOLVE')) {
                $stateMachine = $stateMachine->transition('RESOLVE');
            } elseif ($stateMachine->canTransition('STAND_DOWN')) {
                $stateMachine = $stateMachine->transition('STAND_DOWN');
            }
        }

        $incident->update([
            'status' => $stateMachine->getState(),
            'resolved_at' => Carbon::now(),
        ]);

        return $incident;
    }

    /**
     * Compute safe emergency egress route from an afflicted zone.
     */
    public function getEmergencyEvacuationGuidance(EntertainmentZone $zone): IncidentEgressRoute
    {
        return $this->pathfinderService->findOptimalEgressRoute($zone);
    }
}
