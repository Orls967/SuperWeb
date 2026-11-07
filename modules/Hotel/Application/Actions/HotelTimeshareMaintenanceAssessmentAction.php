<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\TimeshareInvestor;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelTimeshareMaintenanceAssessmentAction
{
    /**
     * Menghitung dan menerbitkan tagihan iuran pemeliharaan tahunan (Sinking Fund) per pemilik token vila.
     *
     * @return array{unit_id: string, total_annual_assessment_idr: int, investor_invoices: list<array{investor_id: string, share_pct: float, bill_amount_idr: int}>}
     */
    public function execute(string $timeshareUnitId, int $annualSinkingFundBudgetPerUnitIdr = 50000000): array
    {
        $unit = TimeshareUnit::findOrFail($timeshareUnitId);
        $investors = TimeshareInvestor::where('timeshare_unit_id', $timeshareUnitId)->get();

        if ($investors->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada investor terdaftar pada unit timeshare ini.');
        }

        $invoices = [];
        $totalShares = (int) ($unit->total_token_shares ?? 100);

        foreach ($investors as $inv) {
            $shareCount = (int) $inv->token_shares;
            $sharePct = round(($shareCount / $totalShares) * 100, 2);
            $billAmount = (int) round(($annualSinkingFundBudgetPerUnitIdr * $shareCount) / $totalShares);

            $invoices[] = [
                'investor_id' => (string) $inv->investor_user_id,
                'share_pct' => $sharePct,
                'bill_amount_idr' => $billAmount,
            ];
        }

        return [
            'unit_id' => $unit->id,
            'total_annual_assessment_idr' => $annualSinkingFundBudgetPerUnitIdr,
            'investor_invoices' => $invoices,
        ];
    }
}
