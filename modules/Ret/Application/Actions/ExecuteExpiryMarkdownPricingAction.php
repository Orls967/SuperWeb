<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;

class ExecuteExpiryMarkdownPricingAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{sku: string, original_price_minor: int, discount_pct: int, discounted_price_minor: int}
     */
    public function execute(array $params): array
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required for markdown pricing.');
        }

        $sku = (string) $params['sku'];
        $originalPrice = (int) $params['original_price_minor'];
        $daysRemaining = (int) $params['days_remaining'];
        $floorPrice = (int) ($params['floor_price_minor'] ?? 0);

        $discountPct = 0;
        if ($daysRemaining <= 1) {
            $discountPct = 70;
        } elseif ($daysRemaining <= 3) {
            $discountPct = 50;
        } elseif ($daysRemaining <= 7) {
            $discountPct = 25;
        }

        $calculatedPrice = (int) round(($originalPrice * (100 - $discountPct)) / 100);
        $finalPrice = max($floorPrice, $calculatedPrice);

        $this->audit(
            action: 'ret_expiry_markdown_calculated',
            newValues: ['sku' => $sku, 'discount_pct' => $discountPct, 'discounted_price' => $finalPrice],
            impactType: 'pricing_optimization',
        );

        return [
            'sku' => $sku,
            'original_price_minor' => $originalPrice,
            'discount_pct' => $discountPct,
            'discounted_price_minor' => $finalPrice,
        ];
    }
}
