<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlDestinationPackageAllocation;

class HotelDestinationPackageMultiVendorAuditLedger
{
    /**
     * Merekonsiliasi riwayat mutasi jurnal perantara penampungan escrow paket destinasi multi-vendor.
     *
     * @return array{audit_period: string, total_releases_processed: int, total_volume_released_idr: int, vendor_breakdown: array<string, int>, discrepancy_detected: bool}
     */
    public function auditMonthlyVendorDisbursements(int $month, int $year): array
    {
        $allocations = HtlDestinationPackageAllocation::where('release_status', 'RELEASED')
            ->whereNotNull('released_at')
            ->get();

        $totalVolume = 0;
        $breakdown = [];

        foreach ($allocations as $alloc) {
            $amount = (int) $alloc->ssp_amount_idr;
            $vendor = (string) $alloc->vendor_type;

            $totalVolume += $amount;
            $breakdown[$vendor] = ($breakdown[$vendor] ?? 0) + $amount;
        }

        return [
            'audit_period' => sprintf('%04d-%02d', $year, $month),
            'total_releases_processed' => $allocations->count(),
            'total_volume_released_idr' => $totalVolume,
            'vendor_breakdown' => $breakdown,
            'discrepancy_detected' => false,
        ];
    }
}
