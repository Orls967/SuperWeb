<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;

/**
 * Layanan Kompilasi Laporan Kilat Harian General Manager (GM Daily Flash Report).
 */
final class HotelDailyFlashReportAggregatorService
{
    /**
     * @return array{report_date: string, total_revenue_idr: int, occupancy_percent: float, adr_idr: int, revpar_idr: int}
     */
    public function compileFlashReport(string|int $propertyId, string $businessDate): array
    {
        $snapshot = HotelDailyRevenueSnapshot::where('property_id', (string) $propertyId)
            ->whereDate('business_date', $businessDate)
            ->first();

        if (!$snapshot) {
            return [
                'report_date' => $businessDate,
                'total_revenue_idr' => 0,
                'occupancy_percent' => 0.0,
                'adr_idr' => 0,
                'revpar_idr' => 0,
            ];
        }

        $occ = round(((int) $snapshot->occupancy_rate_bps) / 100, 1);

        return [
            'report_date' => $businessDate,
            'total_revenue_idr' => (int) $snapshot->total_room_revenue_idr,
            'occupancy_percent' => $occ,
            'adr_idr' => (int) $snapshot->adr_idr,
            'revpar_idr' => (int) $snapshot->revpar_idr,
        ];
    }
}
