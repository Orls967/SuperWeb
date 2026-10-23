<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Modules\Shared\Application\BaseAction;

class TenantRevenueShareTierAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $tenantId = (int) $params['tenant_id'];
        $grossSalesIdr = (int) $params['gross_sales_idr'];
        $baseRentIdr = (int) ($params['base_rent_idr'] ?? 0);
        $tiers = $params['tiers'] ?? [
            ['up_to_idr' => 100000000, 'percent' => 5.0],
            ['up_to_idr' => 300000000, 'percent' => 7.0],
            ['up_to_idr' => null, 'percent' => 10.0],
        ];

        $remainingSales = $grossSalesIdr;
        $prevThreshold = 0;
        $tierBreakdown = [];
        $totalVariableRentIdr = 0;

        foreach ($tiers as $tier) {
            $upTo = $tier['up_to_idr'];
            $pct = (float) $tier['percent'];

            if ($remainingSales <= 0) {
                break;
            }

            if ($upTo !== null) {
                $tierBand = $upTo - $prevThreshold;
                $taxableInTier = min($remainingSales, $tierBand);
                $prevThreshold = $upTo;
            } else {
                $taxableInTier = $remainingSales;
            }

            $tierAmount = (int) round(($taxableInTier * $pct) / 100);
            $totalVariableRentIdr += $tierAmount;
            $remainingSales -= $taxableInTier;

            $tierBreakdown[] = [
                'tier_ceiling_idr' => $upTo,
                'rate_percent' => $pct,
                'taxable_amount_idr' => $taxableInTier,
                'variable_rent_idr' => $tierAmount,
            ];
        }

        // Standard mall rule: Higher of Base Rent vs Variable Turnover Rent
        $effectiveBilledRentIdr = max($baseRentIdr, $totalVariableRentIdr);
        $ruleApplied = $totalVariableRentIdr > $baseRentIdr ? 'turnover_percentage_applied' : 'guaranteed_base_rent_applied';

        $result = [
            'tenant_id' => $tenantId,
            'gross_sales_idr' => $grossSalesIdr,
            'base_rent_idr' => $baseRentIdr,
            'total_variable_rent_idr' => $totalVariableRentIdr,
            'effective_billed_rent_idr' => $effectiveBilledRentIdr,
            'rule_applied' => $ruleApplied,
            'tier_breakdown' => $tierBreakdown,
        ];

        $this->audit(
            action: 'tenant_revenue_share_computed',
            newValues: ['tenant_id' => $tenantId, 'billed_rent' => $effectiveBilledRentIdr, 'rule' => $ruleApplied],
            impactType: 'mall_billing',
        );

        return $result;
    }
}
