<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Exception;
use Modules\Hotel\Domain\Models\HtlDestinationPackageAllocation;
use Modules\Hotel\Domain\Models\HtlDestinationPackageBooking;

class HotelEscrowDisbursementSaga
{
    /**
     * @var list<string>
     */
    private array $compensatedSteps = [];

    /**
     * Mengeksekusi saga pencairan bertahap dengan aksi kompensasi otomatis bila salah satu jurnal ledger gagal.
     *
     * @return array{booking_id: string, saga_status: string, disbursed_amount_idr: int, compensated_steps: list<string>}
     */
    public function executeDisbursementSaga(string $packageBookingId): array
    {
        $booking = HtlDestinationPackageBooking::findOrFail($packageBookingId);
        $allocations = HtlDestinationPackageAllocation::where('package_booking_id', $packageBookingId)
            ->where('release_status', 'PENDING')
            ->get();

        $disbursedTotal = 0;
        $this->compensatedSteps = [];

        try {
            foreach ($allocations as $alloc) {
                // Step forward: rilis alokasi
                $alloc->markReleased();
                $disbursedTotal += $alloc->ssp_amount_idr;
                $this->compensatedSteps[] = "RELEASED_{$alloc->vendor_type}";
            }

            $booking->escrow_held_idr = max(0, $booking->escrow_held_idr - $disbursedTotal);
            if ($booking->escrow_held_idr === 0) {
                $booking->escrow_status = 'FULLY_RELEASED';
            }
            $booking->save();

            return [
                'booking_id' => $booking->id,
                'saga_status' => 'COMPLETED',
                'disbursed_amount_idr' => $disbursedTotal,
                'compensated_steps' => [],
            ];
        } catch (Exception $e) {
            // Jalankan aksi kompensasi (rollback allocations)
            foreach ($allocations as $alloc) {
                $alloc->release_status = 'PENDING';
                $alloc->released_at = null;
                $alloc->save();
            }

            return [
                'booking_id' => $booking->id,
                'saga_status' => 'COMPENSATED_FAILED',
                'disbursed_amount_idr' => 0,
                'compensated_steps' => $this->compensatedSteps,
            ];
        }
    }
}
