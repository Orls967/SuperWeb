<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\CrowdCrushProtocolService;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;

class ExecuteCrowdCrushEmergencyAction extends BaseAction
{
    public function __construct(
        private readonly CrowdCrushProtocolService $crowdProtocolService
    ) {}

    /**
     * @return array{
     *     status: string,
     *     incident_code: ?string,
     *     lockdown_active: bool,
     *     evacuation_corridor: ?string,
     *     ambulance_standby: bool
     * }
     */
    public function execute(
        EntertainmentZone $zone,
        EntertainmentEvent $event,
        int $reportedByUserId
    ): array {
        return DB::transaction(function () use ($zone, $event, $reportedByUserId) {
            $result = $this->crowdProtocolService->assessZoneDensity(
                zone: $zone,
                event: $event,
                reportedByUserId: $reportedByUserId
            );

            $incident = CrowdCrushIncident::where('zone_id', $zone->id)
                ->where('event_id', $event->id)
                ->latest()
                ->first();

            return [
                'status' => $result['status'],
                'incident_code' => $incident?->incident_code,
                'lockdown_active' => $result['lockdown_triggered'],
                'evacuation_corridor' => $result['evacuation_route'],
                'ambulance_standby' => $result['ambulance_standby'],
            ];
        });
    }
}
