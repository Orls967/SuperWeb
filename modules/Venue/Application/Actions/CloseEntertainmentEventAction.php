<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Enums\EventStatus;
use Modules\Venue\Domain\Enums\TicketStatus;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\StateMachines\EventStateMachine;
use Modules\Venue\Domain\StateMachines\TicketStateMachine;
use Modules\Venue\Ledger\VenueTransactionTypes;

class CloseEntertainmentEventAction
{
    public function __construct(
        private readonly ?Ledger $ledgerService = null,
    ) {}

    public function execute(EntertainmentEvent $event, ?string $idempotencyKey = null): EntertainmentEvent
    {
        $key = $idempotencyKey ?? "ven:event_close:{$event->event_code}";

        return DB::transaction(function () use ($event, $key) {
            $lockedEvent = EntertainmentEvent::lockForUpdate()->findOrFail($event->id);

            if ($lockedEvent->status === EventStatus::CLOSED->value || $lockedEvent->status === EventStatus::SETTLED->value) {
                return $lockedEvent;
            }

            // Hitung total tiket yang terjual dan belum refund
            $tickets = EntertainmentTicket::where('event_id', $lockedEvent->id)
                ->whereNotIn('status', [TicketStatus::REFUNDED->value, TicketStatus::VOID->value])
                ->get();

            $totalUnearned = (int) $tickets->sum('price_paid_idr');

            if ($totalUnearned > 0) {
                $ledger = $this->ledgerService ?? app(Ledger::class);
                $ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_EVENT_CLOSING->value,
                    description: "Pengakuan pendapatan unearned event {$lockedEvent->event_code}",
                    idempotencyKey: $key,
                    entries: Posting::lines()
                        ->debit('ven:ticket_unearned:IDR', $totalUnearned)
                        ->credit('ven:ticket_revenue:IDR', $totalUnearned)
                        ->toEntries('IDR'),
                    referenceType: 'VENUE_EVENT',
                    referenceId: (string) $lockedEvent->id
                ));
            }

            // Update status tiket yang aktif menjadi used
            foreach ($tickets as $t) {
                if (in_array($t->status, [TicketStatus::ISSUED->value, TicketStatus::SCANNED_IN->value, TicketStatus::SCANNED_OUT->value], true)) {
                    $t->status = TicketStatus::USED->value;
                    $t->save();
                }
            }

            EventStateMachine::transition($lockedEvent, EventStatus::CLOSED);

            return $lockedEvent;
        });
    }

    /**
     * @return array{success: bool, event: EntertainmentEvent, total_unearned_recognized_idr: int, platform_fee_idr: int, organizer_net_share_idr: int}
     */
    public function handle(
        int $eventId,
        int $platformFeeBps = 500,
        string $idempotencyKey = ''
    ): array {
        $event = EntertainmentEvent::findOrFail($eventId);
        $key = trim($idempotencyKey) !== '' ? $idempotencyKey : "ven:event_close:{$event->event_code}";
        $closed = $this->execute($event, $key);

        $totalUnearned = (int) EntertainmentTicket::where('event_id', $eventId)
            ->whereNotIn('status', ['REFUNDED', 'refunded', 'void', 'VOID'])
            ->sum('price_paid_idr');
        $platformFee = intdiv($totalUnearned * $platformFeeBps, 10000);

        return [
            'success' => true,
            'event' => $closed,
            'total_unearned_recognized_idr' => $totalUnearned,
            'platform_fee_idr' => $platformFee,
            'organizer_net_share_idr' => $totalUnearned - $platformFee,
        ];
    }
}
