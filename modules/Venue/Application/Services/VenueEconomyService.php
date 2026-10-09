<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Venue\Domain\Models\ArtistContract;
use Modules\Venue\Domain\Models\BundleOrder;
use Modules\Venue\Domain\Models\FestivalBundle;
use Modules\Venue\Domain\Models\VenueMembership;
use RuntimeException;

class VenueEconomyService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Settle artist door share:
     * door_share_gross = total_door_sales * (door_share_percentage / 100)
     * net_payout = max(0, door_share_gross - advance_amount)
     */
    public function settleArtistContract(string $contractId, int $totalDoorSales): ArtistContract
    {
        $contract = ArtistContract::findOrFail($contractId);

        $doorShareGross = (int) round(($totalDoorSales * (float) $contract->door_share_percentage) / 100);
        $netPayout = max(0, $doorShareGross - $contract->advance_amount);

        $contract->update([
            'total_door_sales' => $totalDoorSales,
            'door_share_gross' => $doorShareGross,
            'net_payout_amount' => $netPayout,
            'status' => 'settled',
        ]);

        if ($this->ledger && $netPayout > 0) {
            $this->ledger->post(new PostingDTO(
                type: 'VENUE_ARTIST_SETTLEMENT',
                description: "Artist settlement for {$contract->artist_name} ({$contract->contract_number})",
                idempotencyKey: "ven_artist_settle:{$contract->id}",
                entries: [
                    PostingEntryDTO::forCode('expense:venue_artist_fee:IDR', 'IDR', -$netPayout),
                    PostingEntryDTO::forCode("artist:payable:{$contract->id}:IDR", 'IDR', $netPayout),
                ],
                referenceType: 'ven_artist_contracts',
                referenceId: $contract->id,
            ));
        }

        return $contract;
    }

    /**
     * Award loyalty points on consumption
     */
    public function earnPoints(string $membershipId, int $spendAmountIdr): VenueMembership
    {
        $membership = VenueMembership::findOrFail($membershipId);
        // Tier multiplier: Sun: 1x, Moon: 1.5x, Infinity: 2x
        $multiplier = match ($membership->tier) {
            'Infinity' => 2.0,
            'Moon' => 1.5,
            default => 1.0,
        };

        $pointsEarned = (int) floor(($spendAmountIdr / 10000) * $multiplier); // 1 pt per 10k IDR spend
        $membership->increment('loyalty_points', $pointsEarned);

        return $membership->fresh();
    }

    /**
     * Redeem loyalty points
     */
    public function redeemPoints(string $membershipId, int $pointsToRedeem): VenueMembership
    {
        $membership = VenueMembership::findOrFail($membershipId);
        if ($membership->loyalty_points < $pointsToRedeem) {
            throw new RuntimeException("Insufficient loyalty points: has {$membership->loyalty_points}, requested {$pointsToRedeem}");
        }

        $membership->decrement('loyalty_points', $pointsToRedeem);

        return $membership->fresh();
    }

    /**
     * Settle multi-vendor festival bundle package via Ledger escrow
     */
    public function settleFestivalBundleOrder(string $bundleOrderId): BundleOrder
    {
        $order = BundleOrder::findOrFail($bundleOrderId);
        $bundle = FestivalBundle::findOrFail($order->festival_bundle_id);

        if ($order->status === 'settled') {
            return $order;
        }

        $allocations = $bundle->vendor_allocations;
        $totalAllocated = array_sum(array_column($allocations, 'amount'));

        if ($totalAllocated !== $order->total_paid) {
            throw new RuntimeException("Festival bundle sum invariant breach: total allocated ({$totalAllocated}) != total paid ({$order->total_paid})");
        }

        if ($this->ledger) {
            $entries = [];
            // Debit escrow deposit for total paid
            $entries[] = PostingEntryDTO::forCode('escrow:venue_bundle:IDR', 'IDR', -$order->total_paid);

            // Credit individual vendor accounts
            foreach ($allocations as $alloc) {
                $vendorAccount = $alloc['account'] ?? "vendor:settlement:{$alloc['vendor_code']}:IDR";
                $entries[] = PostingEntryDTO::forCode($vendorAccount, 'IDR', (int) $alloc['amount']);
            }

            $this->ledger->post(new PostingDTO(
                type: 'VENUE_BUNDLE_SETTLEMENT',
                description: "Multi-vendor settlement for bundle order {$order->order_number}",
                idempotencyKey: "ven_bundle_settle:{$order->id}",
                entries: $entries,
                referenceType: 'ven_bundle_orders',
                referenceId: $order->id,
            ));
        }

        $order->update([
            'status' => 'settled',
            'settled_at' => now(),
        ]);

        return $order->fresh();
    }
}
