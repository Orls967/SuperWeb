<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Venue\Application\Services\VenueSecondaryLedgerService;
use Modules\Venue\Domain\Events\TicketResaleSettledEvent;

final class RecordSecondaryLedgerListener
{
    public function __construct(
        private readonly VenueSecondaryLedgerService $ledgerService
    ) {}

    public function handle(TicketResaleSettledEvent $event): void
    {
        Log::info('Auditing secondary resale settlement ledger distribution', [
            'resale_id' => $event->resale->id,
            'buyer_user_id' => $event->buyerUserId,
            'settled_price_idr' => $event->settledPriceIdr,
            'pbjt_tax_idr' => $event->pbjtTaxIdr,
            'platform_fee_idr' => $event->platformFeeIdr,
            'promoter_royalty_idr' => $event->promoterRoyaltyIdr,
            'seller_payout_idr' => $event->sellerPayoutIdr,
            'rotated_ticket_hash' => $event->rotatedTicketHash,
        ]);
    }
}
