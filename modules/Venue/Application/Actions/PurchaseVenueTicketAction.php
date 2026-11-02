<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\ClockInterface;
use Modules\Venue\Domain\Events\VenueTicketIssued;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\InventoryCounter;
use Modules\Venue\Domain\Models\QuotaHold;
use Modules\Venue\Domain\Models\SeatClaim;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Domain\Services\VenueTicketChainService;

class PurchaseVenueTicketAction
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly VenueTicketChainService $chainService,
        private readonly Ledger $ledgerService
    ) {}

    /**
     * @return array{success: bool, tickets: list<array{id: int, ticket_number: string, ticket_uuid: string, ticket_hash: string, prev_hash: string, price_paid_idr: int, status: string}>}
     */
    public function handle(
        string $holdToken,
        int $buyerUserId,
        string $paymentMethod,
        string $idempotencyKey
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        // Idempotency check
        $existingTickets = EntertainmentTicket::where('idempotency_key', $idempotencyKey)->orderBy('id')->get();
        if ($existingTickets->isNotEmpty()) {
            return [
                'success' => true,
                'tickets' => $existingTickets->map(fn (EntertainmentTicket $t) => [
                    'id' => $t->id,
                    'ticket_number' => (string) $t->ticket_number,
                    'ticket_uuid' => (string) $t->ticket_uuid,
                    'ticket_hash' => (string) $t->ticket_hash,
                    'prev_hash' => (string) $t->prev_hash,
                    'price_paid_idr' => (int) $t->price_paid_idr,
                    'status' => (string) $t->status,
                ])->all(),
            ];
        }

        $now = $this->clock->now();

        // Check hold type: GA QuotaHold or Seated SeatClaim
        $quotaHold = QuotaHold::where('hold_token', $holdToken)->first();
        $seatClaims = $quotaHold === null ? SeatClaim::where('hold_token', $holdToken)->get() : collect();

        if ($quotaHold === null && $seatClaims->isEmpty()) {
            throw new \RuntimeException('Hold token tidak ditemukan atau telah kedaluwarsa');
        }

        if ($quotaHold !== null) {
            if ($quotaHold->status !== 'HELD' || $quotaHold->held_until->lessThan($now)) {
                throw new \RuntimeException('Hold token tidak ditemukan atau telah kedaluwarsa');
            }
        } else {
            foreach ($seatClaims as $seat) {
                if ($seat->status !== 'HELD' || ($seat->held_until !== null && $seat->held_until->lessThan($now))) {
                    throw new \RuntimeException('Hold token tidak ditemukan atau telah kedaluwarsa');
                }
            }
        }

        return DB::transaction(function () use ($quotaHold, $seatClaims, $buyerUserId, $idempotencyKey) {
            $issuedTickets = [];

            if ($quotaHold !== null) {
                $counter = InventoryCounter::where('event_id', $quotaHold->event_id)
                    ->where('tier_id', $quotaHold->tier_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $tier = TicketTier::findOrFail($quotaHold->tier_id);

                $counter->held_count = max(0, $counter->held_count - $quotaHold->quantity);
                $counter->sold_count += $quotaHold->quantity;
                $counter->save();

                $quotaHold->update(['status' => 'CONSUMED']);

                $latestTicket = EntertainmentTicket::where('event_id', $quotaHold->event_id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $prevHash = $latestTicket ? (string) $latestTicket->ticket_hash : null;
                $seqBase = EntertainmentTicket::where('event_id', $quotaHold->event_id)->count();

                for ($i = 0; $i < $quotaHold->quantity; $i++) {
                    $seq = $seqBase + $i + 1;
                    $ticketNumber = sprintf('TKT-%04d-%06d', $quotaHold->event_id, $seq);
                    $ticketUuid = (string) Str::uuid();

                    $hashData = $this->chainService->computeTicketHash(
                        eventId: $quotaHold->event_id,
                        ticketNumber: $ticketNumber,
                        ticketUuid: $ticketUuid,
                        pricePaidIdr: (int) $tier->price_idr,
                        latestTicketHash: $prevHash
                    );

                    $ticket = EntertainmentTicket::create([
                        'ticket_number' => $ticketNumber,
                        'ticket_uuid' => $ticketUuid,
                        'event_id' => $quotaHold->event_id,
                        'tier_id' => $quotaHold->tier_id,
                        'seat_claim_id' => null,
                        'buyer_user_id' => $buyerUserId,
                        'price_paid_idr' => $tier->price_idr,
                        'ticket_hash' => $hashData['ticket_hash'],
                        'prev_hash' => $hashData['prev_hash'],
                        'idempotency_key' => $idempotencyKey,
                        'status' => 'ISSUED',
                    ]);

                    $prevHash = $hashData['ticket_hash'];
                    $issuedTickets[] = $ticket;
                }

                $totalAmount = $quotaHold->quantity * $tier->price_idr;
                $eventId = $quotaHold->event_id;
            } else {
                /** @var Collection<int, SeatClaim> $seatClaims */
                $firstSeat = $seatClaims->firstOrFail();
                $tier = TicketTier::findOrFail($firstSeat->tier_id);
                $eventId = $firstSeat->event_id;

                $counter = InventoryCounter::where('event_id', $eventId)
                    ->where('tier_id', $tier->id)
                    ->lockForUpdate()
                    ->first();

                if ($counter) {
                    $counter->held_count = max(0, $counter->held_count - $seatClaims->count());
                    $counter->sold_count += $seatClaims->count();
                    $counter->save();
                }

                $latestTicket = EntertainmentTicket::where('event_id', $eventId)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $prevHash = $latestTicket ? (string) $latestTicket->ticket_hash : null;
                $seqBase = EntertainmentTicket::where('event_id', $eventId)->count();

                $idx = 0;
                foreach ($seatClaims as $seat) {
                    $seat->update(['status' => 'BOOKED']);

                    $seq = $seqBase + $idx + 1;
                    $ticketNumber = sprintf('TKT-%04d-%06d', $eventId, $seq);
                    $ticketUuid = (string) Str::uuid();

                    $hashData = $this->chainService->computeTicketHash(
                        eventId: $eventId,
                        ticketNumber: $ticketNumber,
                        ticketUuid: $ticketUuid,
                        pricePaidIdr: (int) $tier->price_idr,
                        latestTicketHash: $prevHash
                    );

                    $ticket = EntertainmentTicket::create([
                        'ticket_number' => $ticketNumber,
                        'ticket_uuid' => $ticketUuid,
                        'event_id' => $eventId,
                        'tier_id' => $tier->id,
                        'seat_claim_id' => $seat->id,
                        'buyer_user_id' => $buyerUserId,
                        'price_paid_idr' => $tier->price_idr,
                        'ticket_hash' => $hashData['ticket_hash'],
                        'prev_hash' => $hashData['prev_hash'],
                        'idempotency_key' => $idempotencyKey,
                        'status' => 'ISSUED',
                    ]);

                    $prevHash = $hashData['ticket_hash'];
                    $issuedTickets[] = $ticket;
                    $idx++;
                }

                $totalAmount = $seatClaims->count() * $tier->price_idr;
            }

            // Post double-entry journal to ledger (V-7)
            $this->ledgerService->post(new PostingDTO(
                type: 'VENUE_TICKET_SALE',
                description: "Venue ticket sale for event {$eventId}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ven:ticket_clearing:IDR', $totalAmount)
                    ->credit('ven:ticket_unearned:IDR', $totalAmount)
                    ->toEntries('IDR'),
                referenceType: 'VENUE_PURCHASE',
                referenceId: (string) $issuedTickets[0]->id,
            ));

            foreach ($issuedTickets as $ticket) {
                event(new VenueTicketIssued($ticket));
            }

            return [
                'success' => true,
                'tickets' => collect($issuedTickets)->map(fn (EntertainmentTicket $t) => [
                    'id' => $t->id,
                    'ticket_number' => (string) $t->ticket_number,
                    'ticket_uuid' => (string) $t->ticket_uuid,
                    'ticket_hash' => (string) $t->ticket_hash,
                    'prev_hash' => (string) $t->prev_hash,
                    'price_paid_idr' => (int) $t->price_paid_idr,
                    'status' => (string) $t->status,
                ])->all(),
            ];
        });
    }
}
