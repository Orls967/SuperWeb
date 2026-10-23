<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;

class IssueFestivalTicketWithGaplessHashAction extends BaseAction
{
    /**
     * Issue tamper-evident festival ticket with gapless sequence and cryptographic hash.
     *
     * @return array{ticket: EntertainmentTicket, ticket_number: string, ticket_hash: string, price_paid_idr: int}
     */
    public function handle(
        int $eventId,
        int $buyerUserId,
        int $pricePaidIdr,
        string $ticketTier = 'GENERAL_ADMISSION'
    ): array {
        return DB::transaction(function () use ($eventId, $buyerUserId, $pricePaidIdr, $ticketTier) {
            $event = EntertainmentEvent::lockForUpdate()->findOrFail($eventId);

            // Compute gapless sequential ticket count
            $ticketCount = EntertainmentTicket::where('event_id', $event->id)->count();
            $seqNumber = $ticketCount + 1;
            $ticketNumber = sprintf('TKT-%04d-%06d', $event->id, $seqNumber);

            $timestamp = time();
            $ticketHash = hash('sha256', "TKT_FESTIVAL_{$event->id}_{$ticketNumber}_{$buyerUserId}_{$timestamp}");

            $ticket = EntertainmentTicket::create([
                'ticket_number' => $ticketNumber,
                'event_id' => $event->id,
                'buyer_user_id' => $buyerUserId,
                'price_paid_idr' => $pricePaidIdr,
                'ticket_hash' => $ticketHash,
                'status' => 'VALID',
            ]);

            $this->audit(
                action: 'venue.ticket.issued',
                auditable: $ticket,
                oldValues: null,
                newValues: [
                    'event_id' => $event->id,
                    'ticket_number' => $ticketNumber,
                    'buyer_user_id' => $buyerUserId,
                    'price_paid_idr' => $pricePaidIdr,
                    'tier' => $ticketTier,
                ],
                correlationId: "ven_tkt_{$ticketNumber}",
                impactType: 'ticket_issuance'
            );

            $this->outbox(
                eventType: 'FestivalTicketIssued',
                payload: [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticketNumber,
                    'event_id' => $event->id,
                    'price_paid_idr' => $pricePaidIdr,
                ],
                idempotencyKey: hash('sha256', "issue_tkt_{$ticketNumber}")
            );

            return [
                'ticket' => $ticket,
                'ticket_number' => $ticketNumber,
                'ticket_hash' => $ticketHash,
                'price_paid_idr' => $pricePaidIdr,
            ];
        });
    }
}
