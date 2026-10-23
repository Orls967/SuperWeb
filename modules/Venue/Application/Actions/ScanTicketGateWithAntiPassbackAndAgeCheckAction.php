<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentZone;

class ScanTicketGateWithAntiPassbackAndAgeCheckAction extends BaseAction
{
    /**
     * Scan ticket at access turnstile with anti-passback and legal age enforcement.
     *
     * @return array{status: string, reason: string, ticket: EntertainmentTicket, zone_occupancy: int}
     */
    public function handle(
        int $ticketId,
        int $attendeeAge,
        int $zoneId,
        string $gateCode = 'GATE-MAIN-01'
    ): array {
        return DB::transaction(function () use ($ticketId, $attendeeAge, $zoneId, $gateCode) {
            $ticket = EntertainmentTicket::lockForUpdate()->findOrFail($ticketId);
            $zone = EntertainmentZone::lockForUpdate()->findOrFail($zoneId);

            // 1. Age check
            if ($attendeeAge < 21) {
                return [
                    'status' => 'DENIED',
                    'reason' => 'Underage attendee: venue legal threshold is 21 years.',
                    'ticket' => $ticket,
                    'zone_occupancy' => $zone->current_occupancy,
                ];
            }

            // 2. Anti-passback check: ticket must be in VALID status
            if ($ticket->status !== 'VALID') {
                return [
                    'status' => 'DENIED',
                    'reason' => "Anti-passback violation: ticket already {$ticket->status}.",
                    'ticket' => $ticket,
                    'zone_occupancy' => $zone->current_occupancy,
                ];
            }

            // 3. Zone crowd safety capacity check
            if ($zone->current_occupancy >= $zone->capacity_limit || $zone->crowd_lockdown_active) {
                return [
                    'status' => 'DENIED',
                    'reason' => "Zone capacity exceeded ({$zone->current_occupancy}/{$zone->capacity_limit}) or crowd lockdown active.",
                    'ticket' => $ticket,
                    'zone_occupancy' => $zone->current_occupancy,
                ];
            }

            $ticket->status = 'USED';
            $ticket->scanned_at = now();
            $ticket->save();

            $zone->current_occupancy += 1;
            $zone->save();

            $this->audit(
                action: 'venue.gate.admitted',
                auditable: $ticket,
                oldValues: ['status' => 'VALID'],
                newValues: [
                    'ticket_number' => $ticket->ticket_number,
                    'gate_code' => $gateCode,
                    'zone_id' => $zone->id,
                    'zone_occupancy' => $zone->current_occupancy,
                ],
                correlationId: "ven_gate_{$ticket->ticket_number}",
                impactType: 'access_control'
            );

            return [
                'status' => 'ALLOWED',
                'reason' => 'Access granted. Enjoy the event!',
                'ticket' => $ticket,
                'zone_occupancy' => $zone->current_occupancy,
            ];
        });
    }
}
