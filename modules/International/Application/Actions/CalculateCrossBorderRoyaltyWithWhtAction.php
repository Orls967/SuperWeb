<?php

declare(strict_types=1);

namespace Modules\International\Application\Actions;

use InvalidArgumentException;
use Modules\International\Domain\Models\TaxTreaty;
use Modules\International\Domain\Models\TechnologyLicense;

class CalculateCrossBorderRoyaltyWithWhtAction
{
    /**
     * @return array{
     *     license_id: int,
     *     sales_revenue_idr: int,
     *     royalty_rate_percent: float,
     *     gross_royalty_idr: int,
     *     wht_rate_percent: float,
     *     withholding_tax_idr: int,
     *     net_remittance_idr: int,
     *     treaty_applied: bool
     * }
     */
    public function execute(TechnologyLicense $license, int $salesRevenueIdr, bool $hasValidDgtForm = false): array
    {
        if ($salesRevenueIdr <= 0) {
            throw new InvalidArgumentException('Sales revenue for royalty calculation must be greater than zero.');
        }

        $grossRoyalty = (int) round($salesRevenueIdr * ($license->royalty_rate_percent / 100.0));
        if ($grossRoyalty < $license->minimum_annual_guarantee_idr && $license->minimum_annual_guarantee_idr > 0) {
            $grossRoyalty = $license->minimum_annual_guarantee_idr;
        }

        $countryCode = $license->foreignEntity?->tax_residence_country ?? 'SG';
        $treaty = TaxTreaty::where('country_code', $countryCode)->first();

        $whtRate = 20.0; // Default PPh 26 Domestic rate
        $treatyApplied = false;

        if ($treaty && $hasValidDgtForm) {
            $whtRate = (float) $treaty->treaty_royalty_rate;
            $treatyApplied = true;
        } elseif ($treaty && ! $treaty->dgt_form_required) {
            $whtRate = (float) $treaty->treaty_royalty_rate;
            $treatyApplied = true;
        }

        $whtAmount = (int) round($grossRoyalty * ($whtRate / 100.0));
        $netRemittance = $grossRoyalty - $whtAmount;

        return [
            'license_id' => $license->id,
            'sales_revenue_idr' => $salesRevenueIdr,
            'royalty_rate_percent' => (float) $license->royalty_rate_percent,
            'gross_royalty_idr' => $grossRoyalty,
            'wht_rate_percent' => $whtRate,
            'withholding_tax_idr' => $whtAmount,
            'net_remittance_idr' => $netRemittance,
            'treaty_applied' => $treatyApplied,
        ];
    }
}
