<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\TimeshareInvestor;

class HotelTimeshareTaxWithholdingReportGenerator
{
    /**
     * Menerbitkan laporan e-Bupot Bukti Potong Pajak PPh atas dividen sewa vila tahunan.
     *
     * @return array{report_id: string, tax_year: int, total_investors: int, total_gross_dividends_idr: int, total_tax_withheld_idr: int, bupot_reference: string, generated_at: string}
     */
    public function generateAnnualTaxReport(int $taxYear, int $grossTotalPoolIdr): array
    {
        $investors = TimeshareInvestor::all();
        $totalTax = (int) round(($grossTotalPoolIdr * 10) / 100); // 10% PPh Final

        $reportId = 'BUPOT-TS-'.$taxYear.'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return [
            'report_id' => $reportId,
            'tax_year' => $taxYear,
            'total_investors' => $investors->count(),
            'total_gross_dividends_idr' => $grossTotalPoolIdr,
            'total_tax_withheld_idr' => $totalTax,
            'bupot_reference' => "DJP-BUPOT-FINAL-4-2-{$reportId}",
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
