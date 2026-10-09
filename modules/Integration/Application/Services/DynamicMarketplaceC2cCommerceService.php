<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * DynamicMarketplaceC2cCommerceService (Fase 313)
 *
 * Implements:
 *  - 313.1 C2C marketplace listings with integrated escrow protection
 *  - 313.2 C2B trade-in buyback pricing based on telematics & condition health scores
 *  - 313.3 & 313.6 Marketplace trust & safety with seller fund holding during active disputes
 *  - 313.4 Tests: Escrow release requires dispute resolution / receipt; buyback price deterministic; ret:audit clean
 *  - 313.5 Edge case: Repeated high velocity used goods sellers require mandatory verified identity before listing activation
 */
class DynamicMarketplaceC2cCommerceService
{
    /**
     * Create C2C marketplace listing with velocity & identity verification gates (313.1 & 313.5 Edge Case).
     */
    public function createListing(
        string $listingCode,
        string $sellerId,
        string $category,
        float $askingPriceUsd,
        bool $identityVerified,
        int $sellerActiveVelocityCount = 1
    ): object {
        $lCode = strtoupper($listingCode);

        // Edge case 313.5: High velocity (> 3 listings) strictly requires verified identity
        if ($sellerActiveVelocityCount > 3 && ! $identityVerified) {
            throw new InvalidArgumentException("Trust & safety violation: High velocity sellers (> 3 active listings) must complete identity verification before listing activation (313.5).");
        }

        $id = DB::table('c2c_marketplace_listings')->insertGetId([
            'listing_code' => $lCode,
            'seller_id' => strtoupper($sellerId),
            'category' => strtoupper($category),
            'asking_price_usd' => $askingPriceUsd,
            'identity_verified' => $identityVerified,
            'active_listing_velocity_count' => $sellerActiveVelocityCount,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('c2c_marketplace_listings')->find($id);
    }

    /**
     * Create and manage C2C escrow with dispute hold protection (313.1, 313.4, 313.6).
     */
    public function lockEscrowFunds(
        string $escrowCode,
        string $listingCode,
        float $amountUsd,
        string $buyerId,
        string $sellerId
    ): object {
        $eCode = strtoupper($escrowCode);

        $id = DB::table('c2c_marketplace_escrows')->insertGetId([
            'escrow_code' => $eCode,
            'listing_code' => strtoupper($listingCode),
            'escrow_amount_usd' => $amountUsd,
            'buyer_id' => strtoupper($buyerId),
            'seller_id' => strtoupper($sellerId),
            'is_disputed' => false,
            'is_dispute_resolved' => false,
            'funds_released' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('c2c_marketplace_escrows')->find($id);
    }

    /**
     * Flag escrow dispute and hold funds (313.3 & 313.6 Risk).
     */
    public function disputeEscrow(string $escrowCode): object
    {
        $eCode = strtoupper($escrowCode);
        DB::table('c2c_marketplace_escrows')
            ->where('escrow_code', $eCode)
            ->update([
                'is_disputed' => true,
                'funds_released' => false, // strictly locked
                'updated_at' => now(),
            ]);

        return (object) DB::table('c2c_marketplace_escrows')->where('escrow_code', $eCode)->first();
    }

    /**
     * Release escrow funds only if undisputed or dispute has been formally resolved (313.4).
     */
    public function releaseEscrowFunds(string $escrowCode, bool $resolveDispute = false): object
    {
        $eCode = strtoupper($escrowCode);
        $escrow = DB::table('c2c_marketplace_escrows')->where('escrow_code', $eCode)->first();
        if (! $escrow) {
            throw new InvalidArgumentException("Escrow '{$escrowCode}' not found.");
        }

        if ($escrow->is_disputed && ! $resolveDispute && ! $escrow->is_dispute_resolved) {
            throw new InvalidArgumentException("Escrow lock: Cannot release funds while dispute is active without formal resolution (313.4).");
        }

        DB::table('c2c_marketplace_escrows')
            ->where('escrow_code', $eCode)
            ->update([
                'is_dispute_resolved' => true,
                'funds_released' => true,
                'updated_at' => now(),
            ]);

        return (object) DB::table('c2c_marketplace_escrows')->where('escrow_code', $eCode)->first();
    }

    /**
     * Calculate deterministic C2B trade-in buyback price based on asset telematics health score (313.2 & 313.4).
     */
    public function calculateBuybackOffer(
        string $offerCode,
        string $assetSku,
        float $telematicsHealthScore,
        float $baseMarketPriceUsd
    ): object {
        $oCode = strtoupper($offerCode);

        // Buyback formula 313.2: Base Price * (Health Score / 100) * 0.85
        $buybackPrice = round($baseMarketPriceUsd * ($telematicsHealthScore / 100.0) * 0.85, 2);

        $id = DB::table('c2b_buyback_offers')->insertGetId([
            'offer_code' => $oCode,
            'asset_sku' => strtoupper($assetSku),
            'telematics_health_score' => $telematicsHealthScore,
            'calculated_buyback_price_usd' => $buybackPrice,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('c2b_buyback_offers')->find($id);
    }

    /**
     * Marketplace & C2C Platform Audit (`ret:audit` & `b2b:audit`) (313.4, 313.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Unverified high-velocity active listings
        $unverifiedHighVelocity = DB::table('c2c_marketplace_listings')
            ->where('active_listing_velocity_count', '>', 3)
            ->where('identity_verified', false)
            ->count();

        // Discrepancy 2: Escrows released while unresolved dispute
        $improperReleases = DB::table('c2c_marketplace_escrows')
            ->where('funds_released', true)
            ->where('is_disputed', true)
            ->where('is_dispute_resolved', false)
            ->count();

        $discrepancies = $unverifiedHighVelocity + $improperReleases;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_listings' => DB::table('c2c_marketplace_listings')->count(),
            'total_escrows' => DB::table('c2c_marketplace_escrows')->count(),
            'total_buybacks' => DB::table('c2b_buyback_offers')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
