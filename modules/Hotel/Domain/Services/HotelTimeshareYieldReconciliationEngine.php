<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HtlTimeshareSchedule;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelTimeshareYieldReconciliationEngine
{
    /**
     * Melakukan rekonsiliasi bulanan pendapatan sewa kamar vila yang terdaftar dalam rental pool.
     *
     * @return array{unit_code: string, total_pool_nights_available: int, nights_rented: int, occupancy_rate_pct: float, gross_revenue_idr: int, maintenance_fee_idr: int, net_distributable_idr: int, reconciled_at: string}
     */
    public function reconcileMonthlyRentalPool(string $timeshareUnitId, int $targetMonth, int $targetYear): array
    {
        $unit = TimeshareUnit::findOrFail($timeshareUnitId);
        $schedules = HtlTimeshareSchedule::where('timeshare_unit_id', $timeshareUnitId)
            ->where('status', 'RENTAL_POOL')
            ->get();

        $poolWeeksCount = $schedules->count();
        $totalPoolNightsAvailable = $poolWeeksCount * 7;

        // Simulasi rata-rata okupansi sewa kamar vila 75%
        $occupancyRatePct = 75.0;
        $nightsRented = (int) round(($totalPoolNightsAvailable * $occupancyRatePct) / 100);

        $dailyRate = (int) $unit->daily_rental_rate_idr;
        $grossRevenue = $nightsRented * $dailyRate;

        // Fee pengelolaan 15%
        $maintenanceFee = (int) round(($grossRevenue * 1500) / 10000);
        $netDistributable = max(0, $grossRevenue - $maintenanceFee);

        return [
            'unit_code' => $unit->unit_code,
            'total_pool_nights_available' => $totalPoolNightsAvailable,
            'nights_rented' => $nightsRented,
            'occupancy_rate_pct' => $occupancyRatePct,
            'gross_revenue_idr' => $grossRevenue,
            'maintenance_fee_idr' => $maintenanceFee,
            'net_distributable_idr' => $netDistributable,
            'reconciled_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
