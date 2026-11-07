<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\TurnstileDevice;

final class EmergencyEvacuationCoordinatorService
{
    /**
     * Coordinate complete evacuation protocol across venue zones.
     *
     * @return array{
     *     incident_code: string,
     *     status: string,
     *     unlocked_turnstiles: int,
     *     evacuated_zones: array<string>,
     *     dispatch_timestamp: string,
     *     siren_activated: bool
     * }
     */
    public function initiateFullEvacuation(int $venueId, string $triggerReason, string $authorizedBy): array
    {
        return DB::transaction(function () use ($venueId, $triggerReason, $authorizedBy) {
            $incidentCode = 'EVAC-'.strtoupper(bin2hex(random_bytes(4)));

            // 1. Catat insiden evakuasi darurat
            $incident = CrowdCrushIncident::create([
                'incident_code' => $incidentCode,
                'zone_id' => null,
                'severity_level' => 'CRITICAL',
                'description' => "Protokol evakuasi menyeluruh dipicu: {$triggerReason} oleh {$authorizedBy}",
                'triggered_by' => $authorizedBy,
                'status' => 'escalated',
                'gate_lockdown_applied' => false,
                'evacuation_route_opened' => true,
                'ambulance_dispatched' => true,
                'metadata' => [
                    'venue_id' => $venueId,
                    'protocol' => 'TOTAL_SITE_EGRESS',
                    'authorized_by' => $authorizedBy,
                ],
            ]);

            // 2. Lepaskan semua gerbang turnstile ke mode rotasi bebas (Free-Spin bidirectional)
            $affectedTurnstiles = TurnstileDevice::where('is_active', true)->get();
            foreach ($affectedTurnstiles as $device) {
                $device->mode = 'EMERGENCY_FREE_SPIN';
                $device->is_locked = false;
                $device->save();
            }

            // 3. Update status zona
            $zones = EntertainmentZone::where('venue_id', $venueId)->get();
            $evacuatedZoneCodes = [];
            foreach ($zones as $z) {
                $z->status = 'EVACUATING';
                $z->save();
                $evacuatedZoneCodes[] = $z->zone_code;
            }

            return [
                'incident_code' => $incidentCode,
                'status' => 'EVACUATION_IN_PROGRESS',
                'unlocked_turnstiles' => $affectedTurnstiles->count(),
                'evacuated_zones' => $evacuatedZoneCodes,
                'dispatch_timestamp' => CarbonImmutable::now()->toIso8601String(),
                'siren_activated' => true,
            ];
        });
    }

    /**
     * Mark evacuation cleared and restore normal turnstile interlocking.
     */
    public function restoreNormalOperations(string $incidentCode, string $officerName): void
    {
        DB::transaction(function () use ($incidentCode) {
            $incident = CrowdCrushIncident::where('incident_code', $incidentCode)->first();
            if ($incident !== null) {
                $incident->status = 'resolved';
                $incident->resolved_at = CarbonImmutable::now();
                $incident->save();
            }

            $turnstiles = TurnstileDevice::where('mode', 'EMERGENCY_FREE_SPIN')->get();
            foreach ($turnstiles as $t) {
                $t->mode = 'NORMAL';
                $t->is_locked = false;
                $t->save();
            }
        });
    }
}
