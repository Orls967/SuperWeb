<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Modules\Party\Contracts\MemberPersonalizationProfileProvider;
use Modules\Venue\Domain\Algorithms\Personalization\PersonalizedOfferContextRouter;
use Modules\Venue\Domain\DTOs\PersonalizedPromoDispatchDTO;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\PersonalizedPromo;
use Modules\Venue\Domain\ValueObjects\PromoPersonalizationContext;

/**
 * Service to orchestrate personalized promotions to active venue guests.
 * Enforces COGS margin floor guardrails and strict deduplication cooldowns (117.5 & 117.6d).
 */
class PersonalizedPromoDistributionService
{
    public const DISPATCH_COOLDOWN_MINUTES = 30;

    public function __construct(
        protected MemberPersonalizationProfileProvider $profileProvider,
        protected PersonalizedOfferContextRouter $routerEngine
    ) {}

    /**
     * Evaluate and dispatch a targeted promotion for a guest present at a specific zone.
     */
    public function evaluateAndDispatchOffer(
        int $userId,
        EntertainmentEvent $event,
        EntertainmentZone $zone,
        string $serviceCategory = 'BEVERAGE'
    ): PersonalizedPromoDispatchDTO {
        // Step 1: Check anti-spam frequency capping (Item 117.6d: no double promo sends)
        $recentOffer = PersonalizedPromo::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::DISPATCH_COOLDOWN_MINUTES))
            ->first();

        if ($recentOffer !== null) {
            return new PersonalizedPromoDispatchDTO(
                eligible: false,
                promoCode: null,
                headline: 'Rate limited: Promotion cooldown active',
                discountPercent: 0.0,
                finalPriceIdr: 0,
                expiresAt: null,
                reasonCode: 'COOLDOWN_ACTIVE'
            );
        }

        // Step 2: Fetch personalization preferences from Member profile provider (G7-K25)
        $profile = $this->profileProvider->getMemberProfile($userId);
        $spendingTier = (string) ($profile['spending_tier'] ?? 'STANDARD');
        $pointsBalance = (int) ($profile['loyalty_points_balance'] ?? 0);
        $favoriteCategory = (string) ($profile['preferred_category'] ?? 'BEVERAGE');

        // Step 3: Define campaign parameters and floor prices
        $baseItemPriceIdr = 150000;
        $cogsUnitCostIdr = 50000;
        $minGrossMarginBps = 3000; // 30% margin floor

        // Construct algorithmic context
        $context = new PromoPersonalizationContext(
            spendingTier: $spendingTier,
            dwellTimeMinutes: 45,
            pointsBalance: $pointsBalance,
            zoneType: $zone->name,
            cogsCostIdr: $cogsUnitCostIdr,
            retailPriceIdr: $baseItemPriceIdr
        );

        $evaluation = $this->routerEngine->routePersonalizedOffer($context, $minGrossMarginBps);

        if (! $evaluation['eligible']) {
            return new PersonalizedPromoDispatchDTO(
                eligible: false,
                promoCode: null,
                headline: 'Offer rejected by business margin floor guardrail',
                discountPercent: 0.0,
                finalPriceIdr: 0,
                expiresAt: null,
                reasonCode: $evaluation['rejection_reason'] ?? 'NOT_ELIGIBLE'
            );
        }

        $discountPercent = (float) $evaluation['discount_percent'];
        $finalPriceIdr = (int) $evaluation['final_price_idr'];
        $promoCode = sprintf('VIP-%d-%s-%d', $event->id, strtoupper(substr(md5($userId.'salt'), 0, 6)), time());
        $expiresAt = Carbon::now()->addMinutes(45);

        // Record persistent promo record in database
        PersonalizedPromo::create([
            'user_id' => $userId,
            'zone_id' => $zone->id,
            'promo_dispatch_code' => $promoCode,
            'promo_title' => sprintf('%s Exclusive: %d%% OFF', $spendingTier, (int) $discountPercent),
            'discount_percent' => (int) $discountPercent,
            'discount_bps' => (int) ($discountPercent * 100),
            'floor_price_idr' => $cogsUnitCostIdr,
            'status' => 'DISPATCHED',
            'expires_at' => $expiresAt,
            'delivered_at' => Carbon::now(),
        ]);

        return new PersonalizedPromoDispatchDTO(
            eligible: true,
            promoCode: $promoCode,
            headline: sprintf('%s Exclusive: %d%% OFF on Premium %s', $spendingTier, (int) $discountPercent, $favoriteCategory),
            discountPercent: $discountPercent,
            finalPriceIdr: $finalPriceIdr,
            expiresAt: $expiresAt->toIso8601String(),
            reasonCode: 'SUCCESS'
        );
    }

    /**
     * Mark promotion as redeemed upon POS checkout.
     */
    public function redeemPromo(string $promoCode): array
    {
        $promo = PersonalizedPromo::where('promo_dispatch_code', $promoCode)->first();

        if (! $promo) {
            return [
                'success' => false,
                'message' => 'PROMO_CODE_NOT_FOUND',
            ];
        }

        if ($promo->status === 'REDEEMED') {
            return [
                'success' => false,
                'message' => 'PROMO_ALREADY_REDEEMED',
            ];
        }

        if ($promo->expires_at && Carbon::parse($promo->expires_at)->isPast()) {
            $promo->update(['status' => 'EXPIRED']);

            return [
                'success' => false,
                'message' => 'PROMO_EXPIRED',
            ];
        }

        $promo->update(['status' => 'REDEEMED']);

        return [
            'success' => true,
            'message' => 'PROMO_REDEEMED_SUCCESSFULLY',
            'discount_percent' => $promo->discount_percent,
        ];
    }
}
