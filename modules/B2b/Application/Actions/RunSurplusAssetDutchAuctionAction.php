<?php

declare(strict_types=1);

namespace Modules\B2b\Application\Actions;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\B2b\Domain\Models\SurplusAuction;

class RunSurplusAssetDutchAuctionAction
{
    /**
     * Create a new surplus asset Dutch auction.
     *
     * @param array{
     *     lot_number: string,
     *     seller_id: string,
     *     asset_type: string,
     *     title: string,
     *     starting_bid_idr: int,
     *     reserve_price_idr: int,
     *     bid_increment_idr?: int,
     *     starts_at?: string|Carbon,
     *     ends_at?: string|Carbon,
     *     asset_reference_id?: string|null
     * } $data
     */
    public function createDutchAuction(array $data): SurplusAuction
    {
        if (empty($data['lot_number']) || empty($data['seller_id']) || empty($data['title'])) {
            throw new InvalidArgumentException('Lot number, seller ID, and title are required.');
        }

        $startingBid = (int) ($data['starting_bid_idr'] ?? 0);
        $reservePrice = (int) ($data['reserve_price_idr'] ?? 0);

        if ($startingBid <= 0 || $reservePrice <= 0) {
            throw new InvalidArgumentException('Starting bid and reserve price must be positive integers.');
        }

        if ($startingBid < $reservePrice) {
            throw new InvalidArgumentException('Dutch auction starting price must be greater than or equal to reserve price.');
        }

        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : Carbon::now();
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : Carbon::now()->addDays(2);

        if ($endsAt->lte($startsAt)) {
            throw new InvalidArgumentException('Auction end time must be after start time.');
        }

        return SurplusAuction::create([
            'lot_number' => $data['lot_number'],
            'seller_id' => $data['seller_id'],
            'asset_type' => $data['asset_type'] ?? 'surplus_inventory',
            'asset_reference_id' => $data['asset_reference_id'] ?? null,
            'title' => $data['title'],
            'auction_type' => 'DUTCH',
            'starting_bid_idr' => $startingBid,
            'reserve_price_idr' => $reservePrice,
            'bid_increment_idr' => (int) ($data['bid_increment_idr'] ?? 1000000),
            'current_highest_bid_idr' => $startingBid,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'anti_sniping_enabled' => false,
            'status' => 'active',
        ]);
    }

    /**
     * Compute current Dutch price based on elapsed time decay.
     * Price drops linearly from starting_bid_idr towards reserve_price_idr across duration.
     */
    public function calculateCurrentDutchPrice(SurplusAuction $auction, ?Carbon $asOfTime = null): int
    {
        $nowTs = ($asOfTime ?? Carbon::now())->getTimestamp();
        $startTs = Carbon::parse($auction->starts_at)->getTimestamp();
        $endTs = Carbon::parse($auction->ends_at)->getTimestamp();

        if ($nowTs <= $startTs) {
            return $auction->starting_bid_idr;
        }

        if ($nowTs >= $endTs) {
            return $auction->reserve_price_idr;
        }

        $totalSeconds = $endTs - $startTs;
        if ($totalSeconds <= 0) {
            return $auction->reserve_price_idr;
        }

        $elapsedSeconds = $nowTs - $startTs;
        $totalDrop = $auction->starting_bid_idr - $auction->reserve_price_idr;
        $dropFraction = $elapsedSeconds / $totalSeconds;
        $decayAmount = (int) round($totalDrop * $dropFraction);

        $currentPrice = $auction->starting_bid_idr - $decayAmount;

        return max($auction->reserve_price_idr, $currentPrice);
    }

    /**
     * Accept a Dutch auction at the current decayed price.
     */
    public function acceptDutchPrice(SurplusAuction $auction, string $buyerId, ?int $offeredPriceIdr = null, ?Carbon $asOfTime = null): SurplusAuction
    {
        if ($auction->status !== 'active') {
            throw new InvalidArgumentException("Auction is not active (current status: {$auction->status}).");
        }

        $currentPrice = $this->calculateCurrentDutchPrice($auction, $asOfTime);

        if ($offeredPriceIdr !== null && $offeredPriceIdr < $currentPrice) {
            throw new InvalidArgumentException("Offered price ({$offeredPriceIdr} IDR) is lower than current Dutch price ({$currentPrice} IDR).");
        }

        $finalPrice = $offeredPriceIdr ?? $currentPrice;

        $auction->update([
            'current_highest_bid_idr' => $finalPrice,
            'winning_bidder_id' => $buyerId,
            'status' => 'awarded',
        ]);

        return $auction;
    }
}
