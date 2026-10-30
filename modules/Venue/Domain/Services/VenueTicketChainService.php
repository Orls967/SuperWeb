<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Services;

use Modules\Shared\Domain\Algorithms\Chain\CanonicalHashChain;
use Modules\Venue\Domain\Models\EntertainmentTicket;

class VenueTicketChainService
{
    private readonly CanonicalHashChain $chain;

    public function __construct(?CanonicalHashChain $chain = null)
    {
        $this->chain = $chain ?? new CanonicalHashChain;
    }

    public function computeGenesisHash(int $eventId): string
    {
        return hash('sha256', "GENESIS:EVENT:{$eventId}");
    }

    /**
     * @return array{prev_hash: string, ticket_hash: string}
     */
    public function computeTicketHash(int $eventId, string $ticketNumber, string $ticketUuid, int $pricePaidIdr, ?string $latestTicketHash = null): array
    {
        $prevHash = $latestTicketHash ?? $this->computeGenesisHash($eventId);

        $payload = [
            'event_id' => $eventId,
            'ticket_number' => $ticketNumber,
            'ticket_uuid' => $ticketUuid,
            'price_paid_idr' => $pricePaidIdr,
        ];

        $ticketHash = $this->chain->link($prevHash, $payload);

        return [
            'prev_hash' => $prevHash,
            'ticket_hash' => $ticketHash,
        ];
    }

    public function verifyEventTicketChain(int $eventId): bool
    {
        $tickets = EntertainmentTicket::where('event_id', $eventId)->orderBy('id')->get();
        if ($tickets->isEmpty()) {
            return true;
        }

        $expectedPrev = $this->computeGenesisHash($eventId);

        foreach ($tickets as $ticket) {
            if ($ticket->prev_hash !== $expectedPrev) {
                return false;
            }

            $payload = [
                'event_id' => $ticket->event_id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_uuid' => $ticket->ticket_uuid,
                'price_paid_idr' => $ticket->price_paid_idr,
            ];

            $computed = $this->chain->link((string) $ticket->prev_hash, $payload);
            if (! hash_equals($computed, (string) $ticket->ticket_hash)) {
                return false;
            }

            $expectedPrev = (string) $ticket->ticket_hash;
        }

        return true;
    }
}
