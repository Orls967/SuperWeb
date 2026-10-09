<?php

namespace Modules\B2b\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\B2b\Domain\Models\B2bEscrowAccount;
use Modules\B2b\Domain\Models\B2bRfq;
use Modules\B2b\Domain\Models\SurplusAuction;
use Modules\B2b\Domain\Models\WholesaleCatalog;

class B2bService
{
    public function createWholesaleItem(array $data): WholesaleCatalog
    {
        return DB::transaction(function () use ($data) {
            return WholesaleCatalog::create([
                'sku' => $data['sku'] ?? 'SKU-B2B-'.strtoupper(Str::random(6)),
                'vendor_id' => $data['vendor_id'],
                'product_name' => $data['product_name'],
                'category' => $data['category'],
                'base_price_idr' => (int) $data['base_price_idr'],
                'min_order_qty' => (int) ($data['min_order_qty'] ?? 1),
                'tiered_pricing_matrix' => $data['tiered_pricing_matrix'] ?? [],
                'available_stock' => (int) ($data['available_stock'] ?? 100),
                'status' => 'active',
            ]);
        });
    }

    public function submitRfq(array $data): B2bRfq
    {
        return DB::transaction(function () use ($data) {
            $catalog = WholesaleCatalog::where('id', $data['catalog_id'])->firstOrFail();

            return B2bRfq::create([
                'rfq_number' => $data['rfq_number'] ?? 'RFQ-'.strtoupper(Str::random(8)),
                'buyer_id' => $data['buyer_id'],
                'vendor_id' => $catalog->vendor_id,
                'catalog_id' => $catalog->id,
                'requested_quantity' => (int) $data['requested_quantity'],
                'target_price_idr' => isset($data['target_price_idr']) ? (int) $data['target_price_idr'] : null,
                'payment_terms' => $data['payment_terms'] ?? 'TOP_30',
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function respondToRfq(string $rfqId, int $offeredPriceIdr, string $paymentTerms, ?string $vendorNotes = null): B2bRfq
    {
        return DB::transaction(function () use ($rfqId, $offeredPriceIdr, $paymentTerms, $vendorNotes) {
            $rfq = B2bRfq::where('id', $rfqId)->lockForUpdate()->firstOrFail();

            if (! in_array($rfq->status, ['open', 'negotiating'], true)) {
                throw new \InvalidArgumentException("Cannot respond to RFQ with status {$rfq->status}");
            }

            $rfq->update([
                'target_price_idr' => $offeredPriceIdr,
                'payment_terms' => $paymentTerms,
                'status' => 'quoted',
                'notes' => $vendorNotes ?? $rfq->notes,
            ]);

            return $rfq;
        });
    }

    public function acceptRfq(string $rfqId): B2bRfq
    {
        return DB::transaction(function () use ($rfqId) {
            $rfq = B2bRfq::where('id', $rfqId)->lockForUpdate()->firstOrFail();

            if ($rfq->status !== 'quoted') {
                throw new \InvalidArgumentException('RFQ can only be accepted when status is quoted.');
            }

            $rfq->update(['status' => 'accepted']);

            return $rfq;
        });
    }

    public function createAuction(array $data): SurplusAuction
    {
        return DB::transaction(function () use ($data) {
            return SurplusAuction::create([
                'lot_number' => $data['lot_number'] ?? 'LOT-'.strtoupper(Str::random(8)),
                'seller_id' => $data['seller_id'],
                'asset_type' => $data['asset_type'],
                'asset_reference_id' => $data['asset_reference_id'] ?? null,
                'title' => $data['title'],
                'auction_type' => $data['auction_type'] ?? 'ENGLISH',
                'starting_bid_idr' => (int) $data['starting_bid_idr'],
                'reserve_price_idr' => (int) ($data['reserve_price_idr'] ?? $data['starting_bid_idr']),
                'bid_increment_idr' => (int) ($data['bid_increment_idr'] ?? 1000000),
                'current_highest_bid_idr' => (int) $data['starting_bid_idr'],
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? now()->addDays(3),
                'anti_sniping_enabled' => $data['anti_sniping_enabled'] ?? true,
                'status' => 'active',
            ]);
        });
    }

    public function placeAuctionBid(string $auctionId, string $bidderId, int $bidAmountIdr): SurplusAuction
    {
        return DB::transaction(function () use ($auctionId, $bidderId, $bidAmountIdr) {
            $auction = SurplusAuction::where('id', $auctionId)->lockForUpdate()->firstOrFail();

            if ($auction->status !== 'active') {
                throw new \InvalidArgumentException('Auction is not active.');
            }

            $minNextBid = $auction->current_highest_bid_idr + $auction->bid_increment_idr;
            if ($bidAmountIdr < $minNextBid) {
                throw new \InvalidArgumentException("Bid must be at least {$minNextBid} IDR.");
            }

            // Anti-sniping: extend 5 minutes if bid placed in last 5 minutes
            if ($auction->anti_sniping_enabled && $auction->ends_at->diffInMinutes(now(), false) > -5) {
                $auction->ends_at = $auction->ends_at->addMinutes(5);
            }

            $auction->update([
                'current_highest_bid_idr' => $bidAmountIdr,
                'winning_bidder_id' => $bidderId,
            ]);

            return $auction;
        });
    }

    public function lockEscrowDeposit(array $data): B2bEscrowAccount
    {
        return DB::transaction(function () use ($data) {
            return B2bEscrowAccount::create([
                'escrow_number' => $data['escrow_number'] ?? 'ESC-'.strtoupper(Str::random(8)),
                'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'],
                'buyer_id' => $data['buyer_id'],
                'seller_id' => $data['seller_id'],
                'deposit_amount_idr' => (int) $data['deposit_amount_idr'],
                'released_amount_idr' => 0,
                'refunded_amount_idr' => 0,
                'status' => 'held',
            ]);
        });
    }

    public function releaseEscrow(string $escrowId, string $bastDocId): B2bEscrowAccount
    {
        return DB::transaction(function () use ($escrowId, $bastDocId) {
            $escrow = B2bEscrowAccount::where('id', $escrowId)->lockForUpdate()->firstOrFail();

            if ($escrow->status !== 'held') {
                throw new \InvalidArgumentException('Escrow is not in held status.');
            }

            $escrow->update([
                'released_amount_idr' => $escrow->deposit_amount_idr,
                'status' => 'released',
                'bast_document_id' => $bastDocId,
                'released_at' => now(),
            ]);

            return $escrow;
        });
    }

    public function auditB2b(): array
    {
        $discrepancies = [];

        // Check 1: Escrow balancing (released + refunded <= deposit)
        $escrows = B2bEscrowAccount::all();
        foreach ($escrows as $escrow) {
            if (($escrow->released_amount_idr + $escrow->refunded_amount_idr) > $escrow->deposit_amount_idr) {
                $discrepancies[] = "Escrow {$escrow->escrow_number} over-released: deposit {$escrow->deposit_amount_idr}, total out ".($escrow->released_amount_idr + $escrow->refunded_amount_idr);
            }
        }

        // Check 2: Active auctions must have valid ends_at >= starts_at
        $auctions = SurplusAuction::all();
        foreach ($auctions as $auc) {
            if ($auc->ends_at < $auc->starts_at) {
                $discrepancies[] = "Auction lot {$auc->lot_number} has invalid timeline: starts {$auc->starts_at}, ends {$auc->ends_at}";
            }
        }

        return [
            'status' => count($discrepancies) === 0 ? 'HEALTHY' : 'DISCREPANCY',
            'catalog_items_count' => WholesaleCatalog::count(),
            'active_rfqs_count' => B2bRfq::where('status', 'open')->count(),
            'active_auctions_count' => SurplusAuction::where('status', 'active')->count(),
            'total_escrow_held_idr' => (int) B2bEscrowAccount::where('status', 'held')->sum('deposit_amount_idr'),
            'discrepancies' => $discrepancies,
        ];
    }
}
