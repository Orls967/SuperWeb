<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Modules\Hospital\Contracts\MedicalEmergencyResponseDispatcher;

final class MedicalEmergencyResponseDispatcherService implements MedicalEmergencyResponseDispatcher
{
    public function dispatchMedicalTeam(int $venueId, int $zoneId, string $triageLevel, string $reason): array
    {
        $dispatchCode = sprintf(
            'MED-DISP-%d-%d-%s',
            $venueId,
            $zoneId,
            substr(hash_hmac('sha256', "{$venueId}:{$zoneId}:{$triageLevel}", 'HOSP_SALT'), 0, 8)
        );

        $ambulances = match (strtoupper($triageLevel)) {
            'CRITICAL', 'RED' => 3,
            'HIGH', 'YELLOW' => 2,
            default => 1,
        };

        return [
            'dispatch_code' => $dispatchCode,
            'venue_id' => $venueId,
            'zone_id' => $zoneId,
            'triage_level' => strtoupper($triageLevel),
            'dispatched_ambulance_count' => $ambulances,
            'paramedic_team_size' => $ambulances * 2,
            'estimated_eta_minutes' => 7,
            'status' => 'DISPATCHED_EN_ROUTE',
        ];
    }
}
