<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingCapEngine;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingInput;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketResale;
use RuntimeException;

class ListTicketForResaleAction
{
    public function __construct(
        protected AntiScalpingCapEngine $antiScalpingEngine
    ) {}

    public function execute(
        EntertainmentTicket $ticket,
        int $sellerUserId,
        int $askingPriceIdr,
        float $velocitySeconds = 2.0
    ): TicketResale {
        $settledTransfers = TicketResale::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', 'SETTLED')
            ->count();

        $transferCount = max((int) ($ticket->transfer_count ?? 0), $settledTransfers);

        if ($transferCount >= 1) {
            throw new RuntimeException('Tiket telah dipindahtangankan maksimum 1 kali; transfer sekunder berulang dilarang (single transfer rule)');
        }

        // Hitung tiket aktif yang saat ini dimiliki pengguna
        $activeTicketCount = EntertainmentTicket::query()
            ->where('buyer_user_id', $sellerUserId)
            ->whereIn('status', ['VALID', 'ISSUED'])
            ->count();

        $input = new AntiScalpingInput(
            originalFaceValueIdr: (int) $ticket->price_paid_idr,
            askingPriceIdr: $askingPriceIdr,
            transferCount: $transferCount,
            userActiveTicketCount: $activeTicketCount,
            velocitySeconds: $velocitySeconds
        );

        $evaluation = $this->antiScalpingEngine->evaluate($input);

        if (! $evaluation->isAllowed) {
            throw new RuntimeException($evaluation->rejectionReason ?? 'Penjualan tiket ditolak oleh mesin anti-scalping');
        }

        $resaleCode = 'RSL-'.strtoupper(substr(hash('sha256', $ticket->id.'-'.(string) $sellerUserId.'-'.(string) $askingPriceIdr.'-'.uniqid('', true)), 0, 12));

        return TicketResale::query()->create([
            'resale_code' => $resaleCode,
            'ticket_id' => $ticket->id,
            'seller_user_id' => $sellerUserId,
            'buyer_user_id' => null,
            'original_face_value_idr' => (int) $ticket->price_paid_idr,
            'resale_price_idr' => $evaluation->effectivePriceIdr,
            'platform_fee_idr' => 0,
            'entertainment_tax_idr' => 0,
            'promoter_royalty_idr' => 0,
            'seller_payout_idr' => 0,
            'transfer_count' => $transferCount,
            'status' => 'LISTED',
        ]);
    }
}
