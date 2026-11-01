<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Events\VenueTicketRefunded;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\SeatClaim;

class RefundVenueTicketAction
{
    public function __construct(
        private readonly Ledger $ledgerService
    ) {}

    /**
     * @return array{success: bool, ticket: EntertainmentTicket}
     */
    public function handle(
        int $ticketId,
        string $reason,
        string $idempotencyKey
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        $ticket = EntertainmentTicket::findOrFail($ticketId);
        if ($ticket->status === 'REFUNDED') {
            return [
                'success' => true,
                'ticket' => $ticket,
            ];
        }

        $event = EntertainmentEvent::findOrFail($ticket->event_id);
        if ($event->status === 'CLOSED') {
            throw new \RuntimeException('Tiket tidak dapat direfund karena event telah ditutup');
        }

        return DB::transaction(function () use ($ticket, $idempotencyKey, $reason) {
            $ticket->update(['status' => 'REFUNDED']);

            if ($ticket->seat_claim_id !== null) {
                $seat = SeatClaim::find($ticket->seat_claim_id);
                if ($seat) {
                    $seat->update([
                        'status' => 'AVAILABLE',
                        'buyer_user_id' => null,
                        'held_until' => null,
                        'hold_token' => null,
                        'idempotency_key' => null,
                    ]);
                }
            }

            if ($ticket->tier_id !== null) {
                $counter = InventoryCounter::where('event_id', $ticket->event_id)
                    ->where('tier_id', $ticket->tier_id)
                    ->lockForUpdate()
                    ->first();

                if ($counter) {
                    $counter->sold_count = max(0, $counter->sold_count - 1);
                    $counter->save();
                }
            }

            // Post reversal entry to ledger: Debit unearned, Credit clearing
            $this->ledgerService->post(new PostingDTO(
                type: 'VENUE_TICKET_REFUND',
                description: "Refund for ticket {$ticket->ticket_number}: {$reason}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ven:ticket_unearned:IDR', $ticket->price_paid_idr)
                    ->credit('ven:ticket_clearing:IDR', $ticket->price_paid_idr)
                    ->toEntries('IDR'),
                referenceType: 'VENUE_TICKET',
                referenceId: (string) $ticket->id,
            ));

            event(new VenueTicketRefunded($ticket));

            return [
                'success' => true,
                'ticket' => $ticket,
            ];
        });
    }
}
