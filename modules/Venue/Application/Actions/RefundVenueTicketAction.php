<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Enums\EventStatus;
use Modules\Venue\Domain\Enums\TicketStatus;
use Modules\Venue\Domain\Events\VenueTicketRefunded;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\SeatClaim;
use Modules\Venue\Domain\StateMachines\TicketStateMachine;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class RefundVenueTicketAction
{
    public function __construct(
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * Backward-compatible handler for legacy tests and callers.
     *
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
        if ($ticket->status === 'REFUNDED' || $ticket->status === TicketStatus::REFUNDED->value) {
            return [
                'success' => true,
                'ticket' => $ticket,
            ];
        }

        $event = EntertainmentEvent::findOrFail($ticket->event_id);
        if ($event->status === 'CLOSED' || $event->status === EventStatus::CLOSED->value) {
            throw new \RuntimeException('Tiket tidak dapat direfund karena event telah ditutup');
        }

        $refundedTicket = $this->execute($ticket, $reason, $idempotencyKey);
        $refundedTicket->status = 'REFUNDED';
        $refundedTicket->save();

        return [
            'success' => true,
            'ticket' => $refundedTicket,
        ];
    }

    public function execute(EntertainmentTicket $ticket, string $reason, string $idempotencyKey): EntertainmentTicket
    {
        return DB::transaction(function () use ($ticket, $reason, $idempotencyKey) {
            $locked = EntertainmentTicket::lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status === TicketStatus::REFUNDED->value) {
                return $locked;
            }

            $pricePaid = (int) $locked->price_paid_idr;

            // Balik jurnal unearned
            if ($pricePaid > 0) {
                $ledger = $this->ledger ?? app(Ledger::class);
                $ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_TICKET_REFUND->value,
                    description: "Refund tiket {$locked->ticket_number}: {$reason}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ven:ticket_unearned:IDR', $pricePaid)
                        ->credit('ven:ticket_clearing:IDR', $pricePaid)
                        ->toEntries('IDR'),
                    referenceType: 'VENUE_TICKET',
                    referenceId: (string) $locked->id
                ));
            }

            if ($locked->seat_claim_id !== null) {
                $seat = SeatClaim::find($locked->seat_claim_id);
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

            if ($locked->tier_id !== null) {
                $counter = InventoryCounter::where('event_id', $locked->event_id)
                    ->where('tier_id', $locked->tier_id)
                    ->lockForUpdate()
                    ->first();

                if ($counter) {
                    $counter->sold_count = max(0, $counter->sold_count - 1);
                    $counter->save();
                }
            }

            TicketStateMachine::transition($locked, TicketStatus::REFUNDED, $reason);

            event(new VenueTicketRefunded($locked));

            return $locked;
        });
    }
}
