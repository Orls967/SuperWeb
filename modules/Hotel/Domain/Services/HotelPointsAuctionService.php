<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Algorithms\PointsLiabilityFifo;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelPointsAuctionService
{
    public function __construct(
        protected ?PointsLiabilityFifo $fifo = null,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->fifo = $fifo ?? new PointsLiabilityFifo;
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     auction_settlement_id: string,
     *     auction_title: string,
     *     winning_pass_id: string,
     *     winning_bid_points: int,
     *     lot_reference: string,
     *     status: string,
     *     settled_at: string
     * }
     */
    public function settleWinningAuctionBid(
        string $travelPassId,
        string $auctionLotCode,
        string $auctionTitle,
        int $winningBidPoints,
        string $eventDate
    ): array {
        if ($winningBidPoints < 10000) {
            throw new RuntimeException("Penawaran lelang minimal adalah 10.000 PTS: {$winningBidPoints}");
        }

        return DB::transaction(function () use (
            $travelPassId,
            $auctionLotCode,
            $auctionTitle,
            $winningBidPoints

        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            if ($pass->pts_balance < $winningBidPoints) {
                throw new RuntimeException("Saldo poin pemenang lelang tidak mencukupi: Saldo {$pass->pts_balance} PTS, bid {$winningBidPoints} PTS");
            }

            // Deduct FIFO
            $batches = HtlTravelPassPointBatch::where('travel_pass_id', $pass->id)
                ->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
                ->lockForUpdate()
                ->get();

            $batchList = [];
            foreach ($batches as $b) {
                $batchList[] = [
                    'batch_id' => $b->id,
                    'remaining_pts' => (int) $b->remaining_pts,
                    'liability_per_pt_idr' => (int) $b->liability_per_pt_idr,
                    'expires_at_timestamp' => $b->expires_at->getTimestamp(),
                ];
            }

            $fifoCert = $this->fifo->allocate(
                batches: $batchList,
                ptsToRedeem: $winningBidPoints,
                currentTimestamp: now()->getTimestamp()
            );

            foreach ($fifoCert->redemptionAllocations as $alloc) {
                $bModel = $batches->firstWhere('id', $alloc['batch_id']);
                if ($bModel) {
                    $newRem = $bModel->remaining_pts - $alloc['deducted_pts'];
                    $bModel->update([
                        'remaining_pts' => $newRem,
                        'state' => $newRem === 0 ? 'FULLY_REDEEMED' : 'PARTIALLY_REDEEMED',
                    ]);
                }
            }

            $pass->decrement('pts_balance', $winningBidPoints);

            $settlementId = 'AUC-SETTLE-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));
            $valuationIdr = $winningBidPoints * 100;

            // Post to ledger: Debit Loyalty Points Liability, Credit Miscellaneous Revenue
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_REDEEMED->value,
                description: "Auction winning bid settlement: {$auctionTitle} ({$auctionLotCode}) for {$winningBidPoints} PTS",
                idempotencyKey: "HTL-AUCTION-{$settlementId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_points_liability:IDR', $valuationIdr)
                    ->credit('htl:room_revenue:IDR', $valuationIdr)
                    ->toEntries('IDR'),
                referenceType: 'POINTS_AUCTION',
                referenceId: $settlementId,
            ));

            return [
                'auction_settlement_id' => $settlementId,
                'auction_title' => $auctionTitle,
                'winning_pass_id' => $pass->id,
                'winning_bid_points' => $winningBidPoints,
                'lot_reference' => $auctionLotCode,
                'status' => 'SETTLED',
                'settled_at' => now()->toIso8601String(),
            ];
        });
    }
}
