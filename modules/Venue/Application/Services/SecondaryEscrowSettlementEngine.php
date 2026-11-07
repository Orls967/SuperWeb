<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallEngine;
use Modules\Venue\Domain\Algorithms\Marketplace\SecondaryMarketTaxWaterfallInput;
use Modules\Venue\Domain\Models\SecondaryLedgerEntry;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\StateMachines\TicketResaleStateMachine;

final class SecondaryEscrowSettlementEngine
{
    public function __construct(
        private readonly SecondaryMarketTaxWaterfallEngine $waterfallEngine
    ) {}

    /**
     * Tahan dana dalam rekening penampungan escrow saat pembeli mengajukan pembayaran.
     */
    public function holdInEscrow(TicketResale $resale, int $buyerUserId, int $escrowAmountIdr): TicketResale
    {
        return DB::transaction(function () use ($resale, $buyerUserId, $escrowAmountIdr): TicketResale {
            $lockedResale = TicketResale::query()->lockForUpdate()->findOrFail($resale->id);

            if ($lockedResale->status !== TicketResaleStateMachine::STATUS_LISTED) {
                throw new \DomainException("Hanya tiket berstatus LISTED yang dapat dipindahkan ke penampungan escrow, status saat ini: {$lockedResale->status}");
            }

            if ($escrowAmountIdr !== $lockedResale->resale_price_idr) {
                throw new \DomainException("Jumlah dana escrow ({$escrowAmountIdr}) tidak cocok dengan harga listing ({$lockedResale->resale_price_idr})");
            }

            $lockedResale->buyer_user_id = $buyerUserId;
            $lockedResale->transitionTo(TicketResaleStateMachine::STATUS_ESCROW_HOLD);
            $lockedResale->save();

            // Catat transaksi holding escrow
            SecondaryLedgerEntry::create([
                'resale_id' => $lockedResale->id,
                'entry_type' => 'ESCROW_DEPOSIT_HOLD',
                'gross_amount_idr' => $escrowAmountIdr,
                'pbjt_tax_idr' => 0,
                'platform_fee_idr' => 0,
                'promoter_royalty_idr' => 0,
                'seller_net_idr' => 0,
                'description' => "Deposit dana escrow dari pembeli #{$buyerUserId} untuk tiket #{$lockedResale->ticket_id}",
            ]);

            return $lockedResale;
        });
    }

    /**
     * Cairkan dana escrow setelah verifikasi penerbitan barcode baru berhasil.
     */
    public function disburseEscrow(TicketResale $resale): TicketResale
    {
        return DB::transaction(function () use ($resale): TicketResale {
            $lockedResale = TicketResale::query()->lockForUpdate()->findOrFail($resale->id);

            if ($lockedResale->status !== TicketResaleStateMachine::STATUS_ESCROW_HOLD) {
                throw new \DomainException('Hanya tiket berstatus ESCROW_HOLD yang dapat dicairkan.');
            }

            $input = new SecondaryMarketTaxWaterfallInput($lockedResale->resale_price_idr, 1000, 800, 200);
            $split = $this->waterfallEngine->calculate($input);

            $lockedResale->pbjt_tax_idr = $split->pbjtTaxIdr;
            $lockedResale->platform_fee_idr = $split->platformFeeIdr;
            $lockedResale->promoter_royalty_idr = $split->promoterRoyaltyIdr;
            $lockedResale->seller_payout_idr = $split->sellerPayoutIdr;
            $lockedResale->settled_at = now();
            $lockedResale->transitionTo(TicketResaleStateMachine::STATUS_SETTLED);
            $lockedResale->save();

            // Catat mutasi settlement tertutup
            SecondaryLedgerEntry::create([
                'resale_id' => $lockedResale->id,
                'entry_type' => 'ESCROW_DISBURSEMENT_WATERFALL',
                'gross_amount_idr' => $lockedResale->resale_price_idr,
                'pbjt_tax_idr' => $split->pbjtTaxIdr,
                'platform_fee_idr' => $split->platformFeeIdr,
                'promoter_royalty_idr' => $split->promoterRoyaltyIdr,
                'seller_net_idr' => $split->sellerPayoutIdr,
                'description' => "Pencairan waterfall escrow untuk listing #{$lockedResale->id}",
            ]);

            return $lockedResale;
        });
    }

    /**
     * Kembalikan dana escrow kepada pembeli bila verifikasi gagal.
     */
    public function refundEscrowToBuyer(TicketResale $resale, string $cancellationReason): TicketResale
    {
        return DB::transaction(function () use ($resale, $cancellationReason): TicketResale {
            $lockedResale = TicketResale::query()->lockForUpdate()->findOrFail($resale->id);

            if ($lockedResale->status !== TicketResaleStateMachine::STATUS_ESCROW_HOLD) {
                throw new \DomainException('Status listing bukan ESCROW_HOLD.');
            }

            $buyerId = $lockedResale->buyer_user_id;

            $lockedResale->buyer_user_id = null;
            $lockedResale->transitionTo(TicketResaleStateMachine::STATUS_LISTED);
            $lockedResale->save();

            SecondaryLedgerEntry::create([
                'resale_id' => $lockedResale->id,
                'entry_type' => 'ESCROW_REFUND_CANCEL',
                'gross_amount_idr' => $lockedResale->resale_price_idr,
                'pbjt_tax_idr' => 0,
                'platform_fee_idr' => 0,
                'promoter_royalty_idr' => 0,
                'seller_net_idr' => 0,
                'description' => "Pengembalian dana escrow ke pembeli #{$buyerId}: {$cancellationReason}",
            ]);

            return $lockedResale;
        });
    }
}
