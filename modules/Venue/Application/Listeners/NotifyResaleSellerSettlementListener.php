<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Venue\Domain\Events\TicketResaleSettledEvent;

final class NotifyResaleSellerSettlementListener
{
    public function handle(TicketResaleSettledEvent $event): void
    {
        Log::info('Secondary ticket resale settlement notification sent to seller', [
            'resale_id' => $event->resale->id,
            'seller_user_id' => $event->resale->seller_user_id,
            'seller_payout_idr' => $event->sellerPayoutIdr,
            'deducted_platform_fee_idr' => $event->platformFeeIdr,
        ]);
    }
}
