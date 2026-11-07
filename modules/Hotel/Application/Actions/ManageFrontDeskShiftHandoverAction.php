<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelFolioLine;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;

/**
 * Tindakan Serah Terima Shift Front Desk (Shift Handover Audit & Cash Reconciliation).
 */
final class ManageFrontDeskShiftHandoverAction
{
    /**
     * @return array{shift_name: string, cash_collected_idr: int, card_collected_idr: int, pending_checkins: int, occupied_rooms: int}
     */
    public function execute(string|int $propertyId, string $shiftName, string $businessDate): array
    {
        $propertyIdStr = (string) $propertyId;

        $cash = (int) abs(HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', $propertyIdStr))
            ->whereDate('business_date', $businessDate)
            ->where('line_type', 'CASH_PAYMENT')
            ->sum('amount_idr'));

        $card = (int) abs(HotelFolioLine::whereHas('folio.reservation', fn ($q) => $q->where('property_id', $propertyIdStr))
            ->whereDate('business_date', $businessDate)
            ->where('line_type', 'CARD_PAYMENT')
            ->sum('amount_idr'));

        $pending = HotelReservation::where('property_id', $propertyIdStr)
            ->where('check_in_date', $businessDate)
            ->whereIn('status', ['confirmed', 'reconfirmed'])
            ->count();

        $occupied = HotelRoom::where('property_id', $propertyIdStr)
            ->where('status', 'occupied')
            ->count();

        return [
            'shift_name' => $shiftName,
            'cash_collected_idr' => $cash,
            'card_collected_idr' => $card,
            'pending_checkins' => $pending,
            'occupied_rooms' => $occupied,
        ];
    }
}
