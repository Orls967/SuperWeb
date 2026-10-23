<?php

declare(strict_types=1);

namespace Modules\Partner\Application\Actions;

use InvalidArgumentException;
use Modules\Partner\Domain\Models\Partner;
use Modules\Partner\Domain\Models\RevenueShare;

class CalculateJvEquityProfitShareAction
{
    /**
     * Calculate and record JV equity profit share for partner.
     *
     * @return array{revenue_share: RevenueShare, net_base_idr: int, share_amount_idr: int}
     */
    public function execute(
        Partner $partner,
        string $period,
        int $grossRevenueIdr,
        int $deductibleCostIdr,
        float $shareRatePercent
    ): array {
        if ($grossRevenueIdr < 0 || $deductibleCostIdr < 0) {
            throw new InvalidArgumentException('Pendapatan dan biaya tidak boleh bernilai negatif.');
        }

        if ($shareRatePercent <= 0.0 || $shareRatePercent > 100.0) {
            throw new InvalidArgumentException('Persentase bagi hasil harus berada di antara 0.1% dan 100.0%.');
        }

        $netBaseIdr = max(0, $grossRevenueIdr - $deductibleCostIdr);
        $shareAmountIdr = (int) round(($netBaseIdr * $shareRatePercent) / 100.0);

        $share = RevenueShare::create([
            'partner_id' => (string) $partner->id,
            'period' => $period,
            'gross_revenue_idr' => $grossRevenueIdr,
            'deductible_cost_idr' => $deductibleCostIdr,
            'net_base_idr' => $netBaseIdr,
            'share_rate_percent' => $shareRatePercent,
            'share_amount_idr' => $shareAmountIdr,
            'status' => 'accrued',
        ]);

        return [
            'revenue_share' => $share,
            'net_base_idr' => $netBaseIdr,
            'share_amount_idr' => $shareAmountIdr,
        ];
    }
}
