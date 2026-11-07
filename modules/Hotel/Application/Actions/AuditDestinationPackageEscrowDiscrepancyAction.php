<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\DestinationPackage;

/**
 * Tindakan Audit Rekonsiliasi Dua Sumber: Dana Escrow Paket vs Komponen Vendor Terdistribusi.
 */
final class AuditDestinationPackageEscrowDiscrepancyAction
{
    /**
     * @return array{is_balanced: bool, total_price_idr: int, total_vendor_allocated_idr: int, discrepancy_idr: int}
     */
    public function execute(string $packageId): array
    {
        $package = DestinationPackage::findOrFail($packageId);

        $totalPrice = (int) $package->total_price_idr;
        $vendorShares = (array) $package->vendor_shares;

        $allocatedSum = 0;
        foreach ($vendorShares as $share) {
            $allocatedSum += (int) ($share['amount'] ?? 0);
        }

        $discrepancy = abs($totalPrice - $allocatedSum);

        return [
            'is_balanced' => $discrepancy === 0,
            'total_price_idr' => $totalPrice,
            'total_vendor_allocated_idr' => $allocatedSum,
            'discrepancy_idr' => $discrepancy,
        ];
    }
}
