<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Venue\Application\Services\VenueSecondaryLedgerService;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallEngine;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallInput;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;
use RuntimeException;

class ExecuteSecondaryTicketResaleAction
{
    public function __construct(
        protected SecondaryMarketTaxWaterfallEngine $waterfallEngine,
        protected VenueSecondaryLedgerService $ledgerService
    ) {}

    public function execute(int $resaleId, int $buyerUserId): TicketResale
    {
        return DB::transaction(function () use ($resaleId, $buyerUserId) {
            /** @var TicketResale $resale */
            $resale = TicketResale::query()->lockForUpdate()->findOrFail($resaleId);

            if ($resale->status !== 'LISTED' && $resale->status !== 'ESCROW_HOLD') {
                throw new RuntimeException(sprintf('Listing tiket sekunder tidak dalam status siap ditransaksikan (status: %s)', $resale->status));
            }

            /** @var EntertainmentTicket $ticket */
            $ticket = EntertainmentTicket::query()->lockForUpdate()->findOrFail($resale->ticket_id);

            if ((int) ($ticket->transfer_count ?? 0) >= 1) {
                throw new RuntimeException('Tiket telah dipindahtangankan maksimum 1 kali');
            }

            // 1. Hitung pembagian pajak, platform fee, royalti promotor & seller net payout
            $split = $this->waterfallEngine->calculateWaterfall(new SecondaryMarketTaxWaterfallInput(
                resalePriceIdr: (int) $resale->resale_price_idr,
                originalFaceValueIdr: (int) $resale->original_face_value_idr,
                taxRateBps: 1000,
                platformFeeRateBps: 800,
                promoterRoyaltyBps: 200
            ));

            // 2. Alihkan kepemilikan tiket sah dan catat transfer count ke pembeli baru
            $newTicketHash = hash('sha256', sprintf('TCK-%s-BUYER-%d-%s', $ticket->ticket_number, $buyerUserId, uniqid('', true)));

            $ticket->update([
                'buyer_user_id' => $buyerUserId,
                'transfer_count' => 1,
            ]);

            // 3. Update status listing sekunder menjadi SETTLED
            TicketResaleStateMachine::transition($resale, TicketResaleStateMachine::STATUS_SETTLED);
            $resale->update([
                'buyer_user_id' => $buyerUserId,
                'tax_pbjt_idr' => $split->taxPbjtIdr,
                'platform_fee_idr' => $split->platformFeeIdr,
                'promoter_royalty_idr' => $split->promoterRoyaltyIdr,
                'seller_payout_idr' => $split->sellerPayoutIdr,
                'transfer_count' => 1,
                'new_ticket_hash' => $newTicketHash,
                'settled_at' => Carbon::now(),
            ]);

            // 4. Catat penjurnalan buku besar (Escrow & Waterfall settlement)
            $this->ledgerService->recordEscrowDeposit($resale);
            $this->ledgerService->recordSettlementWaterfall(
                $resale,
                $split->taxPbjtIdr,
                $split->platformFeeIdr,
                $split->promoterRoyaltyIdr,
                $split->sellerPayoutIdr
            );

            return $resale->fresh();
        });
    }
}
