<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlCorporateAccount;

class HotelCityLedgerInterestCalculator
{
    /**
     * Menghitung bunga denda keterlambatan pembayaran tagihan korporat (Late Payment Interest 1.5% per bulan).
     *
     * @return array{corporate_id: string, overdue_principal_idr: int, days_overdue: int, late_interest_rate_monthly_pct: float, calculated_interest_idr: int, total_demand_amount_idr: int}
     */
    public function computeLateInterestPenalty(
        string $corporateAccountId,
        int $overduePrincipalIdr,
        int $daysOverdue,
        float $monthlyInterestRatePct = 1.5
    ): array {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);

        if ($overduePrincipalIdr <= 0 || $daysOverdue <= 0) {
            return [
                'corporate_id' => $corp->id,
                'overdue_principal_idr' => $overduePrincipalIdr,
                'days_overdue' => $daysOverdue,
                'late_interest_rate_monthly_pct' => $monthlyInterestRatePct,
                'calculated_interest_idr' => 0,
                'total_demand_amount_idr' => $overduePrincipalIdr,
            ];
        }

        // Bunga harian = (Bunga Bulanan / 30) * Hari Keterlambatan
        $dailyRateDecimal = ($monthlyInterestRatePct / 100.0) / 30.0;
        $calculatedInterest = (int) round($overduePrincipalIdr * $dailyRateDecimal * $daysOverdue);
        $totalDemand = $overduePrincipalIdr + $calculatedInterest;

        return [
            'corporate_id' => $corp->id,
            'overdue_principal_idr' => $overduePrincipalIdr,
            'days_overdue' => $daysOverdue,
            'late_interest_rate_monthly_pct' => $monthlyInterestRatePct,
            'calculated_interest_idr' => $calculatedInterest,
            'total_demand_amount_idr' => $totalDemand,
        ];
    }
}
