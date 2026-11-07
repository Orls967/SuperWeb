<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HtlDestinationPackageAllocation;
use Modules\Hotel\Domain\Models\HtlDestinationPackageBooking;

class HotelDestinationPackageDisputeResolutionService
{
    /**
     * Memproses arbitrase sengketa pengembalian dana paket wisata multi-vendor.
     *
     * @return array{booking_id: string, refund_amount_idr: int, vendor_penalties: array<string, int>, resolution_status: string}
     */
    public function resolveDispute(
        string $packageBookingId,
        string $disputeType, // FORCE_MAJEURE, VENDOR_DEFAULT, GUEST_CANCELLATION
        ?string $defaultingVendor = null
    ): array {
        $booking = HtlDestinationPackageBooking::findOrFail($packageBookingId);
        $allocations = HtlDestinationPackageAllocation::where('package_booking_id', $packageBookingId)->get();

        $refundAmount = 0;
        $vendorPenalties = [];

        switch ($disputeType) {
            case 'FORCE_MAJEURE':
                // Pengembalian 100% sisa escrow tanpa penalti
                $refundAmount = $booking->escrow_held_idr;
                $booking->escrow_held_idr = 0;
                $booking->escrow_status = 'REFUNDED_FORCE_MAJEURE';
                break;

            case 'VENDOR_DEFAULT':
                // Vendor wanprestasi (misal shuttle tidak datang), porsi vendor terkait dikembalikan ke tamu + denda
                if ($defaultingVendor === null) {
                    throw new InvalidArgumentException('Nama vendor wanprestasi wajib diisi.');
                }

                $alloc = $allocations->firstWhere('vendor_type', $defaultingVendor);
                if ($alloc) {
                    $refundAmount = $alloc->ssp_amount_idr;
                    $vendorPenalties[$defaultingVendor] = (int) round($alloc->ssp_amount_idr * 0.20); // Denda 20%
                    $alloc->release_status = 'REFUNDED_DEFAULT';
                    $alloc->save();

                    $booking->escrow_held_idr = max(0, $booking->escrow_held_idr - $refundAmount);
                    $booking->escrow_status = 'PARTIAL_REFUNDED';
                }
                break;

            case 'GUEST_CANCELLATION':
                // Pembatalan sepihak oleh tamu, berlaku cancellation fee 50%
                $cancellationFee = (int) round($booking->escrow_held_idr * 0.50);
                $refundAmount = max(0, $booking->escrow_held_idr - $cancellationFee);
                $booking->escrow_held_idr = 0;
                $booking->escrow_status = 'CANCELLED_WITH_PENALTY';
                break;

            default:
                throw new InvalidArgumentException("Tipe sengketa tidak valid: {$disputeType}");
        }

        $booking->save();

        return [
            'booking_id' => $booking->id,
            'refund_amount_idr' => $refundAmount,
            'vendor_penalties' => $vendorPenalties,
            'resolution_status' => $booking->escrow_status,
        ];
    }
}
