<?php

declare(strict_types=1);

namespace Modules\Core\Application\Projections\Reducers;

use Modules\Core\Application\Projections\ProjectionReducerInterface;
use Modules\Core\Domain\Models\SimEventSpine;

/**
 * Reducer Proyeksi Tanggap Darurat & Code Blue (Hospital)
 */
class HospitalEmergencyProjectionReducer implements ProjectionReducerInterface
{
    public function supportedTopics(): array
    {
        return [
            'hospital.code_blue.*',
            'hospital.code_blue.activated.v1',
        ];
    }

    public function reduce(array $currentState, SimEventSpine $event): array
    {
        $payload = (array) $event->payload;
        $incidentId = (string) ($payload['incident_id'] ?? 'unknown_incident');

        $incidents = $currentState['domains']['hospital']['code_blues'] ?? [];
        $incidents[$incidentId] = [
            'status' => 'active',
            'room_id' => $payload['room_id'] ?? null,
            'patient_id' => $payload['patient_id'] ?? null,
            'activated_at' => $payload['activated_at'] ?? (string) $event->occurred_at,
            'lead_physician_id' => $payload['lead_physician_id'] ?? null,
        ];

        $currentState['domains']['hospital']['code_blues'] = $incidents;
        $currentState['domains']['hospital']['active_incidents_count'] = count($incidents);

        return $currentState;
    }
}

