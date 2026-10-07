<?php

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\Models\TicketWaitlist;
use RuntimeException;

class VenueSecondaryMarketService
{
    public const MAX_RESALE_MARKUP_PERCENT = 20; // 120% cap

    public const PLATFORM_FEE_PERCENT = 10; // 10% platform fee

    public const TAX_PERCENT = 10; // 10% entertainment tax

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 116.1 List Ticket on Official Anti-Scalping Resale Marketplace
     */
    public function listTicketForResale(
        EntertainmentTicket $ticket,
        int $sellerUserId,
        int $askingPriceIdr
    ): TicketResale {
        // Validation 116.6 (a): 1 transfer maks
        $alreadyTransferred = TicketResale::where('ticket_id', $ticket->id)
            ->where('status', 'SOLD')
            ->count();

        if ($alreadyTransferred >= 1) {
            throw new RuntimeException("Resale rejected: Ticket {$ticket->ticket_number} has already reached maximum of 1 secondary transfer");
        }

        // Validation 116.6 (b): Price cap 120% of original face value
        $maxAllowedPrice = (int) round(($ticket->price_paid_idr * (100 + self::MAX_RESALE_MARKUP_PERCENT)) / 100);
        if ($askingPriceIdr > $maxAllowedPrice) {
            throw new RuntimeException("Resale rejected: Asking price {$askingPriceIdr} IDR exceeds anti-scalping price cap of {$maxAllowedPrice} IDR (120% of face value)");
        }

        return TicketResale::create([
            'resale_code' => 'RSL-'.strtoupper(bin2hex(random_bytes(4))),
            'ticket_id' => $ticket->id,
            'seller_user_id' => $sellerUserId,
            'original_face_value_idr' => $ticket->price_paid_idr,
            'resale_price_idr' => $askingPriceIdr,
            'transfer_count' => $alreadyTransferred,
            'status' => 'LISTED',
        ]);
    }

    /**
     * 116.1 & 116.5 Buy Resale Ticket, Transfer Ownership & Post Ledger Entries
     */
    public function purchaseResaleTicket(TicketResale $resale, int $buyerUserId): TicketResale
    {
        if ($resale->status !== 'LISTED') {
            throw new RuntimeException("Cannot purchase: Resale listing {$resale->resale_code} is {$resale->status}");
        }

        return DB::transaction(function () use ($resale, $buyerUserId) {
            $totalPrice = $resale->resale_price_idr;
            $platformFee = (int) round(($totalPrice * self::PLATFORM_FEE_PERCENT) / 100);
            $entertainmentTax = (int) round(($totalPrice * self::TAX_PERCENT) / 100);
            $sellerPayout = $totalPrice - $platformFee - $entertainmentTax;

            // Generate new ticket tamper-proof hash for buyer
            $newTicketHash = hash('sha256', "RESALE-{$resale->ticket_id}-{$buyerUserId}-".time());

            $resale->update([
                'buyer_user_id' => $buyerUserId,
                'platform_fee_idr' => $platformFee,
                'entertainment_tax_idr' => $entertainmentTax,
                'transfer_count' => $resale->transfer_count + 1,
                'new_ticket_hash' => $newTicketHash,
                'status' => 'SOLD',
            ]);

            // Update original ticket ownership
            $resale->ticket->update([
                'buyer_user_id' => $buyerUserId,
                'ticket_hash' => $newTicketHash,
            ]);

            // Ledger posting: Buyer pays totalPrice -> Seller payout + Platform Fee + Entertainment Tax
            $this->ledgerService->post(new PostingDTO(
                type: 'TICKET_SECONDARY_RESALE_SETTLEMENT',
                description: "Secondary ticket resale settlement for listing {$resale->resale_code}",
                idempotencyKey: "VEN-RSL-{$resale->resale_code}",
                entries: [
                    PostingEntryDTO::forCode('ven:secondary_ticket_clearing:IDR', 'IDR', $totalPrice),
                    PostingEntryDTO::forCode('ven:resale_seller_payable:IDR', 'IDR', -$sellerPayout),
                    PostingEntryDTO::forCode('ven:resale_platform_fee_revenue:IDR', 'IDR', -$platformFee),
                    PostingEntryDTO::forCode('ven:entertainment_tax_payable:IDR', 'IDR', -$entertainmentTax),
                ],
                referenceType: 'TICKET_RESALE',
                referenceId: (string) $resale->id,
            ));

            return $resale;
        });
    }

    /**
     * 116.4 Waitlist & Timed Seat Release
     */
    public function joinWaitlist(int $eventId, int $zoneId, int $userId): TicketWaitlist
    {
        return TicketWaitlist::create([
            'waitlist_code' => 'WTL-'.strtoupper(bin2hex(random_bytes(4))),
            'event_id' => $eventId,
            'zone_id' => $zoneId,
            'user_id' => $userId,
            'status' => 'WAITING',
        ]);
    }

    public function offerSeatToWaitlist(TicketWaitlist $waitlist, int $validMinutes = 15): TicketWaitlist
    {
        $waitlist->update([
            'offer_expires_at' => now()->addMinutes($validMinutes),
            'status' => 'OFFERED',
        ]);

        return $waitlist;
    }
}
