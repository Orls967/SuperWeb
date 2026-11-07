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

            if (in_array(strtoupper((string) $lockedEvent->status), ['CLOSED', 'SETTLED'], true)) {
                return $lockedEvent;
            }

            // Hitung total tiket yang terjual dan belum refund
            $tickets = EntertainmentTicket::where('event_id', $lockedEvent->id)
                ->whereNotIn('status', [TicketStatus::REFUNDED->value, TicketStatus::VOID->value, 'REFUNDED', 'refunded'])
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
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        return DB::transaction(function () use ($eventId, $platformFeeBps, $idempotencyKey) {
            $event = EntertainmentEvent::lockForUpdate()->findOrFail($eventId);

            $tickets = EntertainmentTicket::where('event_id', $eventId)
                ->whereNotIn('status', ['REFUNDED', 'refunded', 'void', 'VOID'])
                ->get();

            $totalUnearned = (int) $tickets->sum('price_paid_idr');
            $platformFee = intdiv($totalUnearned * $platformFeeBps, 10000);
            $organizerNetShare = $totalUnearned - $platformFee;

            if ($event->status === 'CLOSED') {
                return [
                    'success' => true,
                    'event' => $event,
                    'total_unearned_recognized_idr' => $totalUnearned,
                    'platform_fee_idr' => $platformFee,
                    'organizer_net_share_idr' => $organizerNetShare,
                ];
            }

            if ($totalUnearned > 0) {
                $ledger = $this->ledgerService ?? app(Ledger::class);
                $ledger->post(new PostingDTO(
                    type: 'VENUE_EVENT_CLOSING',
                    description: "Event revenue recognition closing for {$event->event_code}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ven:ticket_unearned:IDR', $totalUnearned)
                        ->credit('ven:ticket_revenue:IDR', $organizerNetShare)
                        ->credit('ven:platform_fee_revenue:IDR', $platformFee)
                        ->toEntries('IDR'),
                    referenceType: 'EVENT_CLOSING',
                    referenceId: (string) $event->id,
                ));
            }

            $event->update(['status' => 'CLOSED']);

            return [
                'success' => true,
                'event' => $event,
                'total_unearned_recognized_idr' => $totalUnearned,
                'platform_fee_idr' => $platformFee,
                'organizer_net_share_idr' => $organizerNetShare,
            ];
        });
    }
}
