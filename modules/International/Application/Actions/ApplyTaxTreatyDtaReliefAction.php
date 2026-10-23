<?php

declare(strict_types=1);

namespace Modules\International\Application\Actions;

use InvalidArgumentException;
use Modules\International\Domain\Models\TaxTreaty;

class ApplyTaxTreatyDtaReliefAction
{
    /**
     * @param  string  $incomeType  'royalty'|'interest'|'dividend'|'services'
     * @return array{
     *     country_code: string,
     *     income_type: string,
     *     gross_payment_idr: int,
     *     standard_wht_rate_percent: float,
     *     standard_tax_idr: int,
     *     applied_rate_percent: float,
     *     actual_tax_idr: int,
     *     tax_savings_idr: int,
     *     relief_granted: bool
     * }
     */
    public function execute(
        string $countryCode,
        string $incomeType,
        int $grossPaymentIdr,
        bool $hasValidDgt1Form
    ): array {
        if ($grossPaymentIdr <= 0) {
            throw new InvalidArgumentException('Gross payment amount must be greater than zero.');
        }

        $validIncomeTypes = ['royalty', 'interest', 'dividend', 'services'];
        if (! in_array($incomeType, $validIncomeTypes, true)) {
            throw new InvalidArgumentException("Invalid income type: {$incomeType}.");
        }

        $treaty = TaxTreaty::where('country_code', strtoupper($countryCode))->first();
        $standardRate = $treaty ? (float) $treaty->standard_wht_rate : 20.0;
        $standardTax = (int) round($grossPaymentIdr * ($standardRate / 100.0));

        $reliefGranted = false;
        $appliedRate = $standardRate;

        if ($treaty && $hasValidDgt1Form) {
            $appliedRate = match ($incomeType) {
                'royalty' => (float) $treaty->treaty_royalty_rate,
                'interest' => (float) $treaty->treaty_interest_rate,
                'dividend' => (float) $treaty->treaty_dividend_rate,
                'services' => (float) $treaty->treaty_services_rate,
            };
            $reliefGranted = true;
        }

        $actualTax = (int) round($grossPaymentIdr * ($appliedRate / 100.0));
        $taxSavings = max(0, $standardTax - $actualTax);

        return [
            'country_code' => strtoupper($countryCode),
            'income_type' => $incomeType,
            'gross_payment_idr' => $grossPaymentIdr,
            'standard_wht_rate_percent' => $standardRate,
            'standard_tax_idr' => $standardTax,
            'applied_rate_percent' => $appliedRate,
            'actual_tax_idr' => $actualTax,
            'tax_savings_idr' => $taxSavings,
            'relief_granted' => $reliefGranted,
        ];
    }
}
