<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelTimeshareTaxWithholdingCalculator
{
    /**
     * Menghitung potongan pajak dividen sewa vila (PPh Pasal 4 ayat 2 final 10% atau PPh 23 15%).
     *
     * @return array{gross_dividend_idr: int, tax_rate_pct: float, tax_withheld_idr: int, net_payable_idr: int}
     */
    public function calculateWithholdingTax(int $grossDividendIdr, bool $isCorporateTaxResident = false): array
    {
        if ($grossDividendIdr <= 0) {
            return [
                'gross_dividend_idr' => 0,
                'tax_rate_pct' => 0.0,
                'tax_withheld_idr' => 0,
                'net_payable_idr' => 0,
            ];
        }

        // PPh Final dividen WPOP 10%, Wajib Pajak Badan 15%
        $taxRatePct = $isCorporateTaxResident ? 15.0 : 10.0;
        $taxWithheld = (int) round(($grossDividendIdr * $taxRatePct) / 100);
        $netPayable = max(0, $grossDividendIdr - $taxWithheld);

        return [
            'gross_dividend_idr' => $grossDividendIdr,
            'tax_rate_pct' => $taxRatePct,
            'tax_withheld_idr' => $taxWithheld,
            'net_payable_idr' => $netPayable,
        ];
    }
}
