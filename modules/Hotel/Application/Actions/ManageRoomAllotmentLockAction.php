<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HotelRoomNight;

/**
 * Tindakan Penguncian & Pelepasan Blok Allotment Kamar untuk Agen Perjalanan & Korporat.
 */
final class ManageRoomAllotmentLockAction
{
    /**
     * @return array{property_id: string, room_type_id: string, nights_locked: int, status: string}
     */
    public function lockAllotment(
        string|int $propertyId,
        string|int $roomTypeId,
        string $startDate,
        string $endDate,
        int $quantity,
        string $allotmentAgencyCode
    ): array {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);
        $nightsLocked = 0;

        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            $dateStr = $date->toDateString();
            for ($i = 0; $i < $quantity; $i++) {
                HotelRoomNight::create([
                    'property_id' => (string) $propertyId,
                    'room_type_id' => (string) $roomTypeId,
                    'night_date' => $dateStr,
                    'status' => 'ALLOTMENT_HELD',
                    'notes' => "Agency Allotment: {$allotmentAgencyCode}",
                ]);
                $nightsLocked++;
            }
        }

        return [
            'property_id' => (string) $propertyId,
            'room_type_id' => (string) $roomTypeId,
            'nights_locked' => $nightsLocked,
            'status' => 'HELD',
        ];
    }

    /**
     * @return array{nights_released: int, status: string}
     */
    public function releaseAllotment(string|int $propertyId, string $allotmentAgencyCode): array
    {
        $deleted = HotelRoomNight::where('property_id', (string) $propertyId)
            ->where('status', 'ALLOTMENT_HELD')
            ->where('notes', "Agency Allotment: {$allotmentAgencyCode}")
            ->delete();

        return [
            'nights_released' => $deleted,
            'status' => 'RELEASED',
        ];
    }
}
