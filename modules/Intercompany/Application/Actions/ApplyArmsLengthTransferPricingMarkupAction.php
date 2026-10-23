<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Models\TransferPricingRule;

class ApplyArmsLengthTransferPricingMarkupAction
{
    /**
     * @return array{
     *     rule_code: string,
     *     tp_method: string,
     *     base_cost_idr: int,
     *     min_allowed_margin: float,
     *     max_allowed_margin: float,
     *     applied_margin_percent: float,
     *     is_within_arms_length: bool,
     *     markup_amount_idr: int,
     *     transfer_price_idr: int
     * }
     */
    public function execute(
        TransferPricingRule $rule,
        int $baseCostIdr,
        float $requestedMarginPercent
    ): array {
        if ($baseCostIdr <= 0) {
            throw new InvalidArgumentException('Base cost for transfer pricing must be greater than zero.');
        }

        $min = (float) $rule->min_arms_length_margin_percent;
        $max = (float) $rule->max_arms_length_margin_percent;

        if ($requestedMarginPercent < $min || $requestedMarginPercent > $max) {
            throw new InvalidArgumentException("Requested margin {$requestedMarginPercent}% violates arm's-length range [{$min}%, {$max}%] under rule {$rule->rule_code}.");
        }

        $markupAmount = (int) round($baseCostIdr * ($requestedMarginPercent / 100.0));
        $transferPrice = $baseCostIdr + $markupAmount;

        return [
            'rule_code' => $rule->rule_code,
            'tp_method' => $rule->tp_method,
            'base_cost_idr' => $baseCostIdr,
            'min_allowed_margin' => $min,
            'max_allowed_margin' => $max,
            'applied_margin_percent' => $requestedMarginPercent,
            'is_within_arms_length' => true,
            'markup_amount_idr' => $markupAmount,
            'transfer_price_idr' => $transferPrice,
        ];
    }
}
