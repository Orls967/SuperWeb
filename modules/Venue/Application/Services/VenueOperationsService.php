<?php

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\EntertainmentZone;

class VenueOperationsService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 89.3 Purchase non-fungible digital ticket with anti-replay hash
     */
    public function purchaseTicket(
        EntertainmentEvent $event,
        int $buyerUserId,
        int $pricePaidIdr
    ): EntertainmentTicket {
        return DB::transaction(function () use ($event, $buyerUserId, $pricePaidIdr) {
            $ticketNo = 'TCK-'.strtoupper(bin2hex(random_bytes(6)));
            $ticketHash = hash('sha256', "{$ticketNo}:{$event->id}:{$buyerUserId}:{$pricePaidIdr}:".now()->toIso8601String());

            $ticket = EntertainmentTicket::create([
                'ticket_number' => $ticketNo,
                'event_id' => $event->id,
                'buyer_user_id' => $buyerUserId,
                'price_paid_idr' => $pricePaidIdr,
                'ticket_hash' => $ticketHash,
                'status' => 'VALID',
            ]);

            // Post ticket sales to ledger: Debit Customer Wallet, Credit Venue Ticket Revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'VENUE_TICKET_SALE',
                description: "Ticket sale for event {$event->event_code} ticket {$ticketNo}",
                idempotencyKey: "TCK-SALE-{$ticketNo}",
                entries: [
                    PostingEntryDTO::forCode('ven:ticket_clearing:IDR', 'IDR', $pricePaidIdr),
                    PostingEntryDTO::forCode('ven:ticket_revenue:IDR', 'IDR', -$pricePaidIdr),
                ],
                referenceType: 'VENUE_TICKET',
                referenceId: (string) $ticket->id,
            ));

            return $ticket;
        });
    }

    /**
     * 89.3 Gate Access Control: Scan ticket, anti-replay check, age verification
     */
    public function scanGateAccess(
        EntertainmentTicket $ticket,
        EntertainmentZone $zone,
        int $patronAge
    ): EntertainmentTicket {
        return DB::transaction(function () use ($ticket, $zone, $patronAge) {
            $lockedTicket = EntertainmentTicket::where('id', $ticket->id)->lockForUpdate()->firstOrFail();
            $venue = $lockedTicket->event->venue;

            // 1. Anti-replay check
            if ($lockedTicket->status !== 'VALID') {
                throw new \RuntimeException("Ticket is not valid (Status: {$lockedTicket->status}). Replay access denied.");
            }

            // 2. Age verification
            if ($patronAge < $venue->min_age_requirement) {
                throw new \RuntimeException("Patron age ({$patronAge}) is below venue minimum requirement ({$venue->min_age_requirement}+)");
            }

            // 3. Zone density & crowd safety check
            $lockedZone = EntertainmentZone::where('id', $zone->id)->lockForUpdate()->firstOrFail();
            if ($lockedZone->current_occupancy >= $lockedZone->capacity_limit || $lockedZone->crowd_lockdown_active) {
                throw new \RuntimeException("Zone {$lockedZone->name} is at maximum capacity. Entry temporarily blocked.");
            }

            // Mark ticket used & increment zone occupancy
            $lockedTicket->update([
                'status' => 'USED',
                'scanned_at' => now(),
            ]);

            $lockedZone->increment('current_occupancy', 1);

            return $lockedTicket;
        });
    }

    /**
     * 89.5 VIP Table booking with minimum spend escrow deposit hold
     */
    public function bookVipTable(
        EntertainmentEvent $event,
        string $tableNumber,
        int $userId,
        int $minimumSpendIdr
    ): EntertainmentTableBooking {
        return DB::transaction(function () use ($event, $tableNumber, $userId, $minimumSpendIdr) {
            $depositAmount = (int) round($minimumSpendIdr * 0.50); // 50% deposit

            $booking = EntertainmentTableBooking::create([
                'booking_code' => 'TBL-'.strtoupper(bin2hex(random_bytes(6))),
                'event_id' => $event->id,
                'table_number' => $tableNumber,
                'user_id' => $userId,
                'minimum_spend_idr' => $minimumSpendIdr,
                'deposit_amount_idr' => $depositAmount,
                'status' => 'HELD',
            ]);

            // Hold deposit in ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'VENUE_VIP_TABLE_ESCROW',
                description: "VIP table deposit for {$tableNumber} event {$event->event_code}",
                idempotencyKey: "TBL-DEP-{$booking->booking_code}",
                entries: [
                    PostingEntryDTO::forCode('ven:vip_escrow:IDR', 'IDR', $depositAmount),
                    PostingEntryDTO::forCode('ven:vip_unearned_rev:IDR', 'IDR', -$depositAmount),
                ],
                referenceType: 'VENUE_TABLE_BOOKING',
                referenceId: (string) $booking->id,
            ));

            return $booking;
        });
    }

    /**
     * Handle table no-show: retain deposit as no-show fee revenue
     */
    public function handleTableNoShow(EntertainmentTableBooking $booking): EntertainmentTableBooking
    {
        return DB::transaction(function () use ($booking) {
            $locked = EntertainmentTableBooking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'HELD') {
                return $locked;
            }

            $locked->update(['status' => 'NO_SHOW']);
            $dep = $locked->deposit_amount_idr;

            // Recognize forfeiture as penalty revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'VENUE_TABLE_NO_SHOW_FEE',
                description: "Table no-show fee forfeited for {$locked->booking_code}",
                idempotencyKey: "TBL-NS-{$locked->booking_code}",
                entries: [
                    PostingEntryDTO::forCode('ven:vip_unearned_rev:IDR', 'IDR', $dep),
                    PostingEntryDTO::forCode('ven:vip_noshow_rev:IDR', 'IDR', -$dep),
                ],
                referenceType: 'VENUE_TABLE_BOOKING',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }
}
