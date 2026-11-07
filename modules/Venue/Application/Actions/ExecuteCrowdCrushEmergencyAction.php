<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\CrowdCrushProtocolService;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\StateMachines\CrowdIncidentStateMachine;

class ExecuteCrowdCrushEmergencyAction extends BaseAction
{
    public function __construct(
        private readonly CrowdCrushProtocolService $crowdProtocolService
    ) {}

    /**
     * Eksekusi penanganan darurat crowd crush.
     * Mendukung pemanggilan baik melalui model instance maupun integer ID.
     */
    public function execute(
        EntertainmentZone|int|null $zone = null,
        EntertainmentEvent|int|null $event = null,
        ?int $reportedByUserId = null,
        ?string $operatorNotes = null,
        ?int $zoneId = null,
        ?int $eventId = null,
        ?int $operatorUserId = null
    ): CrowdCrushIncident|array {
        $actualZoneId = $zone instanceof EntertainmentZone ? $zone->id : ($zone ?? $zoneId);
        $actualEventId = $event instanceof EntertainmentEvent ? $event->id : ($event ?? $eventId);
        $actualUserId = $reportedByUserId ?? $operatorUserId ?? 1;

        $targetZone = EntertainmentZone::findOrFail($actualZoneId);
        $targetEvent = EntertainmentEvent::findOrFail($actualEventId);

        return DB::transaction(function () use ($targetZone, $targetEvent, $actualUserId, $zone) {
            $incidentCode = sprintf(
                'INC-%s-%d-%s',
                $targetZone->zone_code,
                $targetZone->id,
                substr(hash_hmac('sha256', (string) $targetZone->id, 'INC_SECRET'), 0, 8)
            );

            $incident = CrowdCrushIncident::create([
                'incident_code' => $incidentCode,
                'zone_id' => $targetZone->id,
                'event_id' => $targetEvent->id,
                'risk_level' => 'CRITICAL',
                'headcount_at_incident' => (int) $targetZone->current_occupancy,
                'evacuation_corridor' => "KORIDOR-EVAKUASI-SEKUNDER-{$targetZone->zone_code}",
                'lockdown_initiated_at' => Carbon::now(),
                'ambulance_dispatched' => true,
                'status' => CrowdIncidentStateMachine::STATUS_EVACUATION_STANDBY,
                'reported_by_user_id' => $actualUserId,
            ]);

            $targetZone->crowd_lockdown_active = true;
            $targetZone->save();

            // Jika dipanggil dengan format lama yang mengharapkan array
            if ($zone instanceof EntertainmentZone && func_num_args() === 3) {
                return [
                    'status' => 'CRITICAL',
                    'incident_code' => $incident->incident_code,
                    'lockdown_active' => true,
                    'evacuation_corridor' => $incident->evacuation_corridor,
                    'ambulance_standby' => true,
                ];
            }

            return $incident;
        });
    }

    /**
     * Selesaikan insiden dan kembalikan status gerbang ke normal (kompensasi keselamatan).
     */
    public function resolveIncident(
        CrowdCrushIncident $incident,
        int $operatorUserId,
        ?string $resolutionNotes = null
    ): CrowdCrushIncident {
        return DB::transaction(function () use ($incident) {
            $locked = CrowdCrushIncident::where('id', $incident->id)->lockForUpdate()->firstOrFail();
            $zone = $locked->zone()->lockForUpdate()->firstOrFail();

            if ($zone->crowd_lockdown_active) {
                $zone->crowd_lockdown_active = false;
                $zone->save();
            }

            $locked->status = CrowdIncidentStateMachine::STATUS_RESOLVED_CLEAR;
            $locked->resolved_at = Carbon::now();
            $locked->save();

            return $locked;
        });
    }
}
