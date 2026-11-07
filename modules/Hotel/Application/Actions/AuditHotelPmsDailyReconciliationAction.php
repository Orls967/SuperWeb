<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Tindakan Audit Rekonsiliasi Dua Sumber: Ledger vs Folio Fisik PMS.
 */
final class AuditHotelPmsDailyReconciliationAction
{
    /**
     * @return array{is_balanced: bool, ledger_room_revenue_idr: int, folio_room_revenue_idr: int, discrepancy_idr: int}
     */
    public function execute(string|int $propertyId, string $businessDate): array
    {
        $propertyIdStr = (string) $propertyId;

        // Sumber 1: Agregasi Baris Folio Fisik
        $folioRoomRev = (int) HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', $propertyIdStr))
            ->whereDate('business_date', $businessDate)
            ->where('line_type', 'ROOM_CHARGE')
            ->sum('base_amount_idr');

        // Sumber 2: Snapshot Night Audit Resmi
        $snapshot = HotelDailyRevenueSnapshot::where('property_id', $propertyIdStr)
            ->whereDate('business_date', $businessDate)
            ->first();

        $snapshotRev = $snapshot ? (int) $snapshot->total_room_revenue_idr : 0;
        $discrepancy = abs($folioRoomRev - $snapshotRev);

        return [
            'is_balanced' => $discrepancy === 0,
            'ledger_room_revenue_idr' => $snapshotRev,
            'folio_room_revenue_idr' => $folioRoomRev,
            'discrepancy_idr' => $discrepancy,
        ];
    }
}
