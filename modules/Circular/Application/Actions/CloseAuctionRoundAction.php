<?php

declare(strict_types=1);

namespace Modules\Circular\Application\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Circular\Domain\Models\CirAuctionRound;
use Modules\Circular\Domain\Models\CirBid;
use Modules\Circular\Domain\Models\CirListing;
use Modules\Core\Contracts\ClockInterface;
use Modules\Shared\Domain\Algorithms\Market\CallAuctionClearing;

final class CloseAuctionRoundAction
{
    public function __construct(
        protected PlaceOrderAction $placeOrder,
        protected ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? app(ClockInterface::class);
    }

    public function execute(
        CirAuctionRound|int $round,
        string $idempotencyKey,
        ?User $actor = null
    ): CirAuctionRound {
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency-Key wajib diisi untuk penutupan ronde lelang.',
            ]);
        }

        $roundModel = $round instanceof CirAuctionRound ? $round : CirAuctionRound::findOrFail($round);

        return DB::transaction(function () use ($roundModel, $key, $actor): CirAuctionRound {
            $locked = CirAuctionRound::where('id', $roundModel->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'closed' || $locked->status === 'settled') {
                return $locked;
            }

            $listings = CirListing::where('auction_round_id', $locked->id)
                ->where('mode', 'auction')
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            $bids = CirBid::where('auction_round_id', $locked->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            $algoBids = [];
            $bidsById = [];
            foreach ($bids as $bid) {
                $bidsById[$bid->id] = $bid;
                $algoBids[] = [
                    'id' => $bid->id,
                    'quantity' => (int) $bid->quantity_grams,
                    'price' => (int) $bid->max_price_per_kg_idr,
                    'created_at' => $bid->created_at ? (int) $bid->created_at->getTimestamp() : (int) $bid->id,
                ];
            }

            $algoAsks = [];
            $listingsById = [];
            foreach ($listings as $listing) {
                $listingsById[$listing->id] = $listing;
                $algoAsks[] = [
                    'id' => $listing->id,
                    'quantity' => (int) $listing->available_grams,
                    'price' => (int) $listing->unit_price_per_kg_idr,
                    'created_at' => $listing->created_at ? (int) $listing->created_at->getTimestamp() : (int) $listing->id,
                ];
            }

            $clearingResult = (new CallAuctionClearing)->solve($algoBids, $algoAsks);

            // Perbarui alokasi pada tiap bid
            foreach ($bids as $bid) {
                $allocated = (int) ($clearingResult->bidAllocations[$bid->id] ?? 0);
                $bid->update([
                    'allocated_grams' => $allocated,
                    'status' => $allocated > 0 ? 'allocated' : 'unfilled',
                ]);
            }

            // Buat order untuk setiap trade hasil kliring
            foreach ($clearingResult->trades as $idx => $trade) {
                $tradeQty = (int) $trade['quantity'];
                if ($tradeQty <= 0) {
                    continue;
                }

                $bid = $bidsById[$trade['bid_id']] ?? null;
                $listing = $listingsById[$trade['ask_id']] ?? null;

                if ($bid === null || $listing === null) {
                    continue;
                }

                $this->placeOrder->execute([
                    'listing_id' => $listing->id,
                    'buyer_party_id' => $bid->buyer_party_id,
                    'ordered_grams' => $tradeQty,
                    'destination_facility_id' => $locked->destination_facility_id,
                    'auction_round_id' => $locked->id,
                    'override_unit_price_per_kg_idr' => $clearingResult->clearingPrice,
                    'idempotency_key' => "cir:auc:trade:{$locked->id}:{$idx}:{$key}",
                ], $actor);
            }

            // Tandai listing lelang yang tidak terjual habis sebagai closed
            CirListing::where('auction_round_id', $locked->id)
                ->where('status', 'active')
                ->update(['status' => 'closed']);

            $now = $this->clock ? $this->clock->now() : CarbonImmutable::now();

            $locked->update([
                'status' => 'closed',
                'clearing_price_per_kg_idr' => $clearingResult->clearingPrice,
                'cleared_volume_grams' => $clearingResult->clearingVolume,
                'idempotency_key' => $key,
                'closed_at' => $now,
            ]);

            return $locked;
        });
    }
}
