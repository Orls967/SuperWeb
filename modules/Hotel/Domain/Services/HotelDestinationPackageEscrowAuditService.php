<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Models\HtlDestinationPackageBooking;

class HotelDestinationPackageEscrowAuditService
{
    /**
     * Menjalankan audit menyeluruh terhadap seluruh akun penampungan escrow paket destinasi multi-vendor.
     *
     * @return array{total_audited_bookings: int, balanced_bookings_count: int, discrepancy_bookings_count: int, total_escrow_holding_idr: int, total_allocated_ssp_idr: int, audit_verdict: string, audited_at: string}
     */
    public function auditAllEscrowBalances(): array
    {
        $bookings = HtlDestinationPackageBooking::with('allocations')->get();

        $totalHolding = 0;
        $totalAllocated = 0;
        $discrepancies = 0;

        foreach ($bookings as $b) {
            $totalHolding += $b->escrow_held_idr;
            $allocSum = (int) $b->allocations->sum('ssp_amount_idr');
            $totalAllocated += $allocSum;

            // Jika status HELD, total paid harus sama dengan total alokasi SSP
            if ($b->escrow_status === 'HELD' && $b->total_paid_idr !== $allocSum) {
                $discrepancies++;
            }
        }

        $verdict = ($discrepancies === 0) ? '100%_CLEAN_AUDIT' : 'DISCREPANCY_FLAGGED';

        return [
            'total_audited_bookings' => $bookings->count(),
            'balanced_bookings_count' => $bookings->count() - $discrepancies,
            'discrepancy_bookings_count' => $discrepancies,
            'total_escrow_holding_idr' => $totalHolding,
            'total_allocated_ssp_idr' => $totalAllocated,
            'audit_verdict' => $verdict,
            'audited_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
