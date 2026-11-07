<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Venue\Domain\Algorithms\TierCountdownPricer;
use Modules\Venue\Domain\Enums\TicketStatus;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketTier;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class IssueTicketWithGaplessHashAction
{
    public function __construct(
        private readonly ?DocumentNumberingInterface $numbering = null,
        private readonly ?Ledger $ledger = null,
    ) {}

    public function execute(
        EntertainmentEvent $event,
        TicketTier $tier,
        int $buyerUserId,
        string $idempotencyKey,
        int $demandBps = 0,
        int $rainProbabilityBps = 0,
    ): EntertainmentTicket {
        return DB::transaction(function () use ($event, $tier, $buyerUserId, $idempotencyKey, $demandBps, $rainProbabilityBps) {
            $existing = EntertainmentTicket::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            // Hitung harga dinamis V1
            $pricing = TierCountdownPricer::calculate(
                basePriceIdr: (int) $tier->price_idr,
                demandBps: $demandBps,
                rainProbabilityBps: $rainProbabilityBps,
                floorPriceIdr: (int) $event->ticket_floor_price_idr,
                ceilingPriceIdr: (int) $event->ticket_ceiling_price_idr,
            );
            $finalPriceIdr = $pricing['price_idr'];

            // Gapless ticket number
            $count = EntertainmentTicket::where('event_id', $event->id)->count() + 1;
            $ticketNumber = sprintf('TCK-%s-%06d', $event->event_code, $count);

            // Previous hash for hash-chain
            $lastTicket = EntertainmentTicket::where('event_id', $event->id)
                ->orderByDesc('id')
                ->first();
            $prevHash = $lastTicket ? $lastTicket->ticket_hash : hash('sha256', "EVENT_ROOT:{$event->event_code}");

            // Canonical hash
            $ticketUuid = (string) Str::uuid();
            $ticketHash = hash('sha256', "{$ticketNumber}:{$event->id}:{$buyerUserId}:{$finalPriceIdr}:{$prevHash}");

            $ticket = EntertainmentTicket::create([
                'ticket_uuid' => $ticketUuid,
                'ticket_number' => $ticketNumber,
                'event_id' => $event->id,
                'tier_id' => $tier->id,
                'buyer_user_id' => $buyerUserId,
                'price_paid_idr' => $finalPriceIdr,
                'tax_paid_idr' => 0,
                'ticket_hash' => $ticketHash,
                'prev_hash' => $prevHash,
                'idempotency_key' => $idempotencyKey,
                'status' => TicketStatus::ISSUED->value,
                'reentry_count' => 0,
                'last_direction' => null,
            ]);

            // Ledger posting: Debit Clearing, Kredit Unearned Revenue
            $ledger = $this->ledger ?? app(Ledger::class);
            $ledger->post(new PostingDTO(
                type: VenueTransactionTypes::VENUE_TICKET_SALE->value,
                description: "Penjualan tiket {$ticketNumber} event {$event->event_code}",
                idempotencyKey: "ven:ledger:tck_sale:{$ticketNumber}",
                entries: Posting::lines()
                    ->debit('ven:ticket_clearing:IDR', $finalPriceIdr)
                    ->credit('ven:ticket_unearned:IDR', $finalPriceIdr)
                    ->toEntries('IDR'),
                referenceType: 'VENUE_TICKET',
                referenceId: (string) $ticket->id
            ));

            return $ticket;
        });
    }
}
