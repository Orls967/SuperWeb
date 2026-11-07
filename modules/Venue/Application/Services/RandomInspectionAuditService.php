<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\VenueBagInspection;
use Modules\Venue\Domain\StateMachines\InspectionStateMachine;

class RandomInspectionAuditService
{
    /**
     * Mencatat hasil pemeriksaan tas acak di gerbang tanpa menyimpan data medis atau sensitif (Kepatuhan PDP).
     */
    public function recordInspection(
        EntertainmentEvent $event,
        string $gateCode,
        string $inspectorBadge,
        ?EntertainmentTicket $ticket = null,
        ?string $contrabandFound = null,
        bool $policeReferred = false
    ): VenueBagInspection {
        $hasContraband = $contrabandFound !== null && trim($contrabandFound) !== '';
        $inspectionCode = 'INSP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $status = match (true) {
            $policeReferred => InspectionStateMachine::STATUS_POLICE_REFERRED,
            $hasContraband => InspectionStateMachine::STATUS_CONFISCATED,
            default => InspectionStateMachine::STATUS_CLEARED,
        };

        return VenueBagInspection::create([
            'inspection_code' => $inspectionCode,
            'event_id' => $event->id,
            'gate_code' => $gateCode,
            'patron_ticket_id' => $ticket?->id,
            'inspector_badge' => $inspectorBadge,
            'contraband_found' => $hasContraband,
            'contraband_type' => $contrabandFound,
            'police_referred' => $policeReferred,
            'status' => $status,
            'inspected_at' => now(),
        ]);
    }

    /**
     * Menghitung statistik kepatuhan keamanan gerbang per acara.
     *
     * @return array{
     *     total_inspections: int,
     *     cleared_count: int,
     *     contraband_seizures: int,
     *     police_escalations: int,
     *     contraband_breakdown: array<string, int>
     * }
     */
    public function getEventInspectionSummary(int $eventId): array
    {
        $inspections = VenueBagInspection::where('event_id', $eventId)->get();

        $cleared = $inspections->where('status', InspectionStateMachine::STATUS_CLEARED)->count();
        $contraband = $inspections->where('contraband_found', true)->count();
        $police = $inspections->where('police_referred', true)->count();

        $breakdown = [];
        foreach ($inspections->where('contraband_found', true) as $item) {
            $type = $item->contraband_type ?? 'OTHER';
            $breakdown[$type] = ($breakdown[$type] ?? 0) + 1;
        }

        return [
            'total_inspections' => $inspections->count(),
            'cleared_count' => $cleared,
            'contraband_seizures' => $contraband,
            'police_escalations' => $police,
            'contraband_breakdown' => $breakdown,
        ];
    }
}
