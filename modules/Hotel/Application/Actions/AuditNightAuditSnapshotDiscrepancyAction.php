<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Tindakan Verifikasi Integritas Snapshot Pendapatan Harian vs Transaksi Folio.
 */
final class AuditNightAuditSnapshotDiscrepancyAction
{
    /**
     * @return array{audited_snapshots_count: int, discrepancies_found: int, is_clean: bool}
     */
    public function execute(string|int $propertyId, int $lookbackDays = 14): array
    {
        $snapshots = HotelDailyRevenueSnapshot::where('property_id', (string) $propertyId)
            ->latest('business_date')
            ->take($lookbackDays)
            ->get();

        $discrepancyCount = 0;

        foreach ($snapshots as $snap) {
            $bDate = $snap->business_date->toDateString();
            $roomSum = (int) HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', (string) $propertyId))
                ->whereDate('business_date', $bDate)
                ->where('line_type', 'ROOM_CHARGE')
                ->sum('base_amount_idr');

            if ($roomSum !== (int) $snap->total_room_revenue_idr) {
                $discrepancyCount++;
            }
        }

        return [
            'audited_snapshots_count' => $snapshots->count(),
            'discrepancies_found' => $discrepancyCount,
            'is_clean' => $discrepancyCount === 0,
        ];
    }
}
