<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Layanan Pengawasan Validasi Kupon Sarapan Pagi Restoran Hotel (Breakfast Governor).
 */
final class HotelBreakfastVoucherGovernorService
{
    /**
     * @return array{is_eligible: bool, entitlement_count: int, reason: string}
     */
    public function verifyBreakfastAccess(string|int $reservationId, string $date): array
    {
        $reservation = HotelReservation::findOrFail((string) $reservationId);

        if ($reservation->status !== 'checked_in') {
            return [
                'is_eligible' => false,
                'entitlement_count' => 0,
                'reason' => 'Tamu belum check-in atau telah check-out',
            ];
        }

        return [
            'is_eligible' => true,
            'entitlement_count' => 2, // 2 Pax per kamar
            'reason' => 'Sarapan pagi terdaftar dalam paket menginap',
        ];
    }
}
