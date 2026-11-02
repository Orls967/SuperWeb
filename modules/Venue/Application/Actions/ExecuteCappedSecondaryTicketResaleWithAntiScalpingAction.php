<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use RuntimeException;

class ExecuteCappedSecondaryTicketResaleWithAntiScalpingAction extends BaseAction
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
     * List ticket on official secondary market with 120% price cap and execute secondary sale.
     *
     * @return array{resale: TicketResale, ticket: EntertainmentTicket, seller_payout_idr: int, platform_fee_idr: int, entertainment_tax_idr: int}
     */
    public function handle(
        int $ticketId,
        int $sellerUserId,
        int $askingPriceIdr,
        ?int $buyerUserId = null
    ): array {
        return DB::transaction(function () use (
            $ticketId,
            $sellerUserId,
            $askingPriceIdr,
            $buyerUserId
        ) {
            $ticket = EntertainmentTicket::lockForUpdate()->findOrFail($ticketId);

            // Validation 116.6 (a): 1 secondary transfer maximum
            $alreadyTransferred = TicketResale::where('ticket_id', $ticket->id)
                ->where('status', 'SOLD')
                ->count();

            if ($alreadyTransferred >= 1) {
                throw new RuntimeException("Resale rejected: Ticket {$ticket->ticket_number} has already reached maximum of 1 secondary transfer");
            }

            // Validation 116.6 (b): Anti-scalping 120% price cap of original face value
            $maxAllowedPrice = (int) round(($ticket->price_paid_idr * (100 + self::MAX_RESALE_MARKUP_PERCENT)) / 100);
            if ($askingPriceIdr > $maxAllowedPrice) {
                throw new RuntimeException("Resale rejected: Asking price {$askingPriceIdr} IDR exceeds anti-scalping price cap of {$maxAllowedPrice} IDR (120% of face value)");
            }

            $count = TicketResale::count() + 1;
            $resaleCode = sprintf('RSL-%06d', $count);

            $resale = TicketResale::create([
                'resale_code' => $resaleCode,
                'ticket_id' => $ticket->id,
                'seller_user_id' => $sellerUserId,
                'original_face_value_idr' => $ticket->price_paid_idr,
                'resale_price_idr' => $askingPriceIdr,
                'transfer_count' => $alreadyTransferred,
                'status' => 'LISTED',
            ]);

            $platformFee = 0;
            $entertainmentTax = 0;
            $sellerPayout = 0;

            if ($buyerUserId !== null) {
                $totalPrice = $resale->resale_price_idr;
                $platformFee = (int) round(($totalPrice * self::PLATFORM_FEE_PERCENT) / 100);
                $entertainmentTax = (int) round(($totalPrice * self::TAX_PERCENT) / 100);
                $sellerPayout = $totalPrice - $platformFee - $entertainmentTax;

                $newTicketHash = hash('sha256', "RESALE-{$resale->ticket_id}-{$buyerUserId}-".time());

                $resale->update([
                    'buyer_user_id' => $buyerUserId,
                    'platform_fee_idr' => $platformFee,
                    'entertainment_tax_idr' => $entertainmentTax,
                    'transfer_count' => $resale->transfer_count + 1,
                    'new_ticket_hash' => $newTicketHash,
                    'status' => 'SOLD',
                ]);

                $ticket->update([
                    'buyer_user_id' => $buyerUserId,
                ]);

                $this->ledgerService->post(new PostingDTO(
                    type: 'TICKET_SECONDARY_RESALE_SETTLE',
                    description: "Secondary ticket resale settlement for listing {$resale->resale_code}",
                    idempotencyKey: "VEN-RSL-{$resale->resale_code}",
                    entries: Posting::lines()
                        ->debit('ven:secondary_ticket_clearing:IDR', $totalPrice)
                        ->credit('ven:resale_seller_payable:IDR', $sellerPayout)
                        ->credit('ven:resale_platform_fee_revenue:IDR', $platformFee)
                        ->credit('ven:entertainment_tax_payable:IDR', $entertainmentTax)
                        ->toEntries('IDR'),
                    referenceType: 'TICKET_RESALE',
                    referenceId: (string) $resale->id,
                ));
            }

            $this->audit(
                action: 'venue.ticket_resale.processed',
                auditable: $resale,
                oldValues: null,
                newValues: [
                    'resale_code' => $resaleCode,
                    'ticket_id' => $ticket->id,
                    'asking_price_idr' => $askingPriceIdr,
                    'buyer_user_id' => $buyerUserId,
                    'seller_payout_idr' => $sellerPayout,
                ],
                correlationId: "ven_resale_{$resaleCode}",
                impactType: 'secondary_ticket_resale'
            );

            return [
                'resale' => $resale,
                'ticket' => $ticket,
                'seller_payout_idr' => $sellerPayout,
                'platform_fee_idr' => $platformFee,
                'entertainment_tax_idr' => $entertainmentTax,
            ];
        });
    }
}
