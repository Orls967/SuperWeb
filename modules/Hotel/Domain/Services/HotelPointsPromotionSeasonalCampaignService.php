<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlPersonalizedTravelCampaign;

final class HotelPointsPromotionSeasonalCampaignService
{
    /**
     * @return array{
     *     campaign_code: string,
     *     title: string,
     *     discount_percentage: int,
     *     multiplier: float,
     *     eligible_tiers: list<string>,
     *     start_date: string,
     *     end_date: string,
     *     status: string
     * }
     */
    public function launchSeasonalFlashSale(
        string $campaignTitle,
        int $pointsDiscountPercentage = 20,
        float $earningMultiplier = 2.0,
        array $eligibleTiers = ['GOLD', 'PLATINUM', 'BLACK'],
        int $durationDays = 7
    ): array {
        $campaignCode = 'FLASH-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        $startDate = now();
        $endDate = now()->addDays($durationDays);

        // Store active flash sale definition into personalized campaigns pool
        HtlPersonalizedTravelCampaign::create([
            'travel_pass_id' => null, // Broadcast to all eligible
            'campaign_code' => $campaignCode,
            'title' => $campaignTitle,
            'description' => "Diskon {$pointsDiscountPercentage}% penukaran award room dan bonus {$earningMultiplier}x poin selama masa promo.",
            'points_discount_pct' => $pointsDiscountPercentage,
            'bonus_multiplier' => $earningMultiplier,
            'valid_from' => $startDate,
            'valid_until' => $endDate,
            'is_claimed' => false,
        ]);

        return [
            'campaign_code' => $campaignCode,
            'title' => $campaignTitle,
            'discount_percentage' => $pointsDiscountPercentage,
            'multiplier' => $earningMultiplier,
            'eligible_tiers' => $eligibleTiers,
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
            'status' => 'ACTIVE_FLASH_SALE',
        ];
    }
}
