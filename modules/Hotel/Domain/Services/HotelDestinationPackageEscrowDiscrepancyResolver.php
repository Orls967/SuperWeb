<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlDestinationPackageBooking;

class HotelDestinationPackageEscrowDiscrepancyResolver
{
    /**
     * Menyelesaikan selisih pembulatan mata uang pada pemecahan Standalone Selling Price paket destinasi.
     *
     * @return array{booking_id: string, initial_discrepancy_idr: int, adjustment_vendor: string, resolved_balance_idr: int, resolution_status: string}
     */
    public function resolveRoundingDiscrepancy(string $packageBookingId, int $discrepancyIdr): array
    {
        $booking = HtlDestinationPackageBooking::findOrFail($packageBookingId);

        // Selisih pembulatan kecil (+/- Rp 100) dibebankan ke porsi hotel sebagai operator utama
        $booking->escrow_held_idr = max(0, $booking->escrow_held_idr + $discrepancyIdr);
        $booking->save();

        return [
            'booking_id' => $booking->id,
            'initial_discrepancy_idr' => $discrepancyIdr,
            'adjustment_vendor' => 'HOTEL',
            'resolved_balance_idr' => (int) $booking->escrow_held_idr,
            'resolution_status' => 'ROUNDING_RECONCILED',
        ];
    }
}
