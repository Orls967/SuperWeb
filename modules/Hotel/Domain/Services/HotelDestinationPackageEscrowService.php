<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\DestinationPackage;

/**
 * Layanan Domain: Pengelolaan Rekonsiliasi & Tata Kelola Escrow Paket Destinasi Multi-Vendor.
 */
final class HotelDestinationPackageEscrowService
{
    /**
     * @return array{package_code: string, total_price_idr: int, total_vendor_count: int, platform_retention_idr: int, status: string}
     */
    public function getEscrowAuditSummary(string $packageId): array
    {
        $package = DestinationPackage::findOrFail($packageId);
        $shares = (array) $package->vendor_shares;

        $platformRetention = (int) round($package->total_price_idr * 0.05); // 5% fee

        return [
            'package_code' => $package->package_code,
            'total_price_idr' => (int) $package->total_price_idr,
            'total_vendor_count' => count($shares),
            'platform_retention_idr' => $platformRetention,
            'status' => 'ESCROW_ACTIVE',
        ];
    }
}
