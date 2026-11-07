<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Enums\TicketStatus;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\StateMachines\TicketStateMachine;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class RefundVenueTicketAction
{
    public function __construct(
        private readonly ?Ledger $ledger = null,
    ) {}

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

            TicketStateMachine::transition($locked, TicketStatus::REFUNDED, $reason);

            return $locked;
        });
    }
}
