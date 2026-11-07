<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Algorithms\ZoneCapacityGovernor;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\StateMachines\IncidentStateMachine;

class CrowdCrushProtocolService
{
    /**
     * Mengevaluasi kepadatan zona secara real-time dan mengaktifkan protokol crowd-crush bila ambang batas terlampaui.
     *
     * @return array{
     *     status: string,
     *     incident_created: bool,
     *     risk_level: string,
     *     lockdown_triggered: bool,
     *     evacuation_route: ?string,
     *     ambulance_standby: bool
     * }
     */
    public function assessZoneDensity(
        EntertainmentZone $zone,
        EntertainmentEvent $event,
        int $reportedByUserId
    ): array {
        return DB::transaction(function () use ($zone, $event, $reportedByUserId) {
            $lockedZone = EntertainmentZone::where('id', $zone->id)->lockForUpdate()->firstOrFail();

            $legalCap = (int) $lockedZone->capacity_limit;
            $effectiveCap = ZoneCapacityGovernor::calculateEffectiveCapacity($legalCap, 500);
            $occupancy = (int) $lockedZone->current_occupancy;

            $densityBps = $effectiveCap > 0 ? (int) round(($occupancy / $effectiveCap) * 10000) : 0;

            $riskLevel = match (true) {
                $densityBps >= 9500 => 'CRITICAL',
                $densityBps >= 8500 => 'WARNING',
                $densityBps >= 7000 => 'ELEVATED',
                default => 'NORMAL',
            };

            $lockdownTriggered = false;
            $evacuationRoute = null;
            $ambulanceStandby = false;
            $incidentCreated = false;

            if ($densityBps >= 8500) {
                $incidentCreated = true;
                $lockdownTriggered = ($densityBps >= 9500);
                $ambulanceStandby = ($densityBps >= 9000);
                $evacuationRoute = "KORIDOR-EVAKUASI-SEKUNDER-{$lockedZone->zone_code}";

                if ($lockdownTriggered) {
                    $lockedZone->crowd_lockdown_active = true;
                    $lockedZone->save();
                }

                $incidentCode = 'INC-'.strtoupper($lockedZone->zone_code).'-'.time();
                $incident = CrowdCrushIncident::create([
                    'incident_code' => $incidentCode,
                    'zone_id' => $lockedZone->id,
                    'event_id' => $event->id,
                    'risk_level' => $riskLevel,
                    'headcount_at_incident' => $occupancy,
                    'evacuation_corridor' => $evacuationRoute,
                    'lockdown_initiated_at' => $lockdownTriggered ? now() : null,
                    'ambulance_dispatched' => $ambulanceStandby,
                    'status' => $lockdownTriggered ? IncidentStateMachine::STATUS_LOCKDOWN_ACTIVE : IncidentStateMachine::STATUS_REPORTED,
                    'reported_by_user_id' => $reportedByUserId,
                ]);
            }

            return [
                'status' => $riskLevel,
                'incident_created' => $incidentCreated,
                'risk_level' => $riskLevel,
                'lockdown_triggered' => $lockdownTriggered,
                'evacuation_route' => $evacuationRoute,
                'ambulance_standby' => $ambulanceStandby,
            ];
        });
    }

    /**
     * Menyelesaikan status darurat crowd-crush setelah kepadatan kembali normal.
     */
    public function resolveIncident(CrowdCrushIncident $incident, ?string $notes = null): void
    {
        DB::transaction(function () use ($incident, $notes) {
            $locked = CrowdCrushIncident::where('id', $incident->id)->lockForUpdate()->firstOrFail();
            $zone = $locked->zone()->lockForUpdate()->firstOrFail();

            if ($zone->crowd_lockdown_active) {
                $zone->crowd_lockdown_active = false;
                $zone->save();
            }

            IncidentStateMachine::transition($locked, IncidentStateMachine::STATUS_RESOLVED, $notes);
            IncidentStateMachine::transition($locked, IncidentStateMachine::STATUS_CLOSED, 'Semua pengunjung aman dievakuasi/dialihkan');
        });
    }
}
