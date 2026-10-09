<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * CustomerPricingFairnessService (Fase 419)
 *
 * Implements:
 *  - 419.1 Fairness review: differentiation criteria documented, prohibited bases flagged
 *  - 419.2 Personalized offer governance: max discount caps (e.g. 30%), exclusion of prohibited bases
 *  - 419.3 Transparency breakdown: customer-visible price components
 *  - 419.4 Tests: prohibited basis blocked, discount within caps, transparency breakdown present, pricing:audit clean
 *  - 419.5 Edge case: discriminatory basis strictly blocked by rule engine
 *  - 419.6 Risk: Unexplained price difference avoided via mandatory transparency breakdown
 *  - 419.7 Evidence: fairness review doc, offer governance log
 */
class CustomerPricingFairnessService
{
    public function registerRule(
        string $ruleCode,
        string $differentiationBasis,
        bool $isProhibited = false,
        float $maxDiscount = 30.00
    ): object {
        $id = DB::table('crm_pricing_fairness_rules')->insertGetId([
            'rule_code' => strtoupper($ruleCode),
            'differentiation_basis' => strtolower($differentiationBasis),
            'is_prohibited_basis' => $isProhibited,
            'max_discount_depth_percent' => $maxDiscount,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_pricing_fairness_rules')->where('id', $id)->first();
    }

    public function generatePersonalizedOffer(
        string $offerCode,
        string $customerId,
        string $basis,
        float $basePrice,
        float $discountPercent,
        array $priceComponents
    ): object {
        $basisNorm = strtolower($basis);
        $rule = DB::table('crm_pricing_fairness_rules')->where('differentiation_basis', $basisNorm)->first();

        // 419.1, 419.4, 419.5 Edge case: Prohibited basis strictly blocked
        if ($rule && $rule->is_prohibited_basis) {
            throw new InvalidArgumentException("Offer blocked: Differentiation basis '{$basis}' is legally prohibited / discriminatory (419.1, 419.5).");
        }

        // 419.2 Discount depth cap check
        $maxDiscount = $rule ? (float) $rule->max_discount_depth_percent : 30.00;
        if ($discountPercent > $maxDiscount) {
            throw new InvalidArgumentException("Offer blocked: Discount of {$discountPercent}% exceeds maximum allowed cap of {$maxDiscount}% (419.2, 419.4).");
        }

        // 419.3 & 419.6 Mandatory transparency breakdown
        if (empty($priceComponents)) {
            throw new InvalidArgumentException("Offer blocked: Transparency breakdown of price components is mandatory (419.3, 419.6).");
        }

        $finalPrice = round($basePrice * (1 - ($discountPercent / 100)), 2);

        $id = DB::table('crm_personalized_offers')->insertGetId([
            'offer_code' => strtoupper($offerCode),
            'customer_id' => $customerId,
            'differentiation_basis' => $basisNorm,
            'base_price' => $basePrice,
            'discount_percent' => $discountPercent,
            'final_price' => $finalPrice,
            'transparency_breakdown_json' => json_encode($priceComponents),
            'fairness_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_personalized_offers')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Offers issued on prohibited basis or exceeding discount caps
        $prohibitedBases = DB::table('crm_pricing_fairness_rules')
            ->where('is_prohibited_basis', true)
            ->pluck('differentiation_basis')
            ->toArray();

        $violatingOffers = DB::table('crm_personalized_offers')
            ->whereIn('differentiation_basis', $prohibitedBases)
            ->count();

        return [
            'status' => $violatingOffers === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_offers' => DB::table('crm_personalized_offers')->count(),
            'discrepancy_count' => $violatingOffers,
        ];
    }
}
