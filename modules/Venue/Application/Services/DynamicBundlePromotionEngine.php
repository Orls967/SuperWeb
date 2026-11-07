<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundleInput;
use Modules\Venue\Domain\Algorithms\Bundling\DynamicBundlePricer;
use Modules\Venue\Domain\Models\DynamicBundlePackage;

final class DynamicBundlePromotionEngine
{
    public function __construct(
        private readonly DynamicBundlePricer $pricer
    ) {}

    /**
     * Hitung harga promosi dinamis paket bundle dengan kupon atau diskon loyalitas.
     *
     * @return array{
     *   base_price_idr: int,
     *   initial_discount_bps: int,
     *   additional_promo_bps: int,
     *   total_discount_bps: int,
     *   final_promotional_price_idr: int,
     *   floor_price_idr: int,
     *   is_floor_hit: bool,
     *   applied_code: ?string
     * }
     */
    public function applyPromotionalDiscount(
        DynamicBundlePackage $package,
        ?string $couponCode,
        int $buyerLoyaltyLevel = 0
    ): array {
        $baseDiscountBps = $package->discount_bps;
        $extraPromoBps = 0;
        $appliedCode = null;

        if ($couponCode !== null && trim($couponCode) !== '') {
            $code = strtoupper(trim($couponCode));
            if ($code === 'VIPFEST2026') {
                $extraPromoBps = 500; // Ekstra 5%
                $appliedCode = $code;
            } elseif ($code === 'SOUNDWAVE10') {
                $extraPromoBps = 1000; // Ekstra 10%
                $appliedCode = $code;
            }
        }

        // Loyalitas tier (maksimal 500 bps tambahan)
        $loyaltyBonusBps = min(500, $buyerLoyaltyLevel * 100);
        $totalRequestedDiscountBps = min(4000, $baseDiscountBps + $extraPromoBps + $loyaltyBonusBps);

        $input = new DynamicBundleInput(
            $package->total_base_price_idr,
            $totalRequestedDiscountBps,
            $package->floor_price_idr
        );

        $result = $this->pricer->calculate($input);

        return [
            'base_price_idr' => $package->total_base_price_idr,
            'initial_discount_bps' => $baseDiscountBps,
            'additional_promo_bps' => $extraPromoBps + $loyaltyBonusBps,
            'total_discount_bps' => $result->effectiveDiscountBps,
            'final_promotional_price_idr' => $result->bundlePriceIdr,
            'floor_price_idr' => $package->floor_price_idr,
            'is_floor_hit' => $result->isFloorTriggered,
            'applied_code' => $appliedCode,
        ];
    }
}
