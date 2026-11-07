<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelDestinationPackageCommissionSettlementService
{
    /**
     * Menghitung komisi platform agen perhotelan (Platform Commission Fee 10%) dari penjualan paket wisata multi-vendor.
     *
     * @param  list<array{vendor_type: string, gross_share_idr: int, commission_rate_bps: int}>  $vendorShares
     * @return array{booking_id: string, total_gross_package_idr: int, total_platform_commission_idr: int, net_vendor_disbursements: array<string, int>, settlement_status: string}
     */
    public function computePlatformCommissions(string $packageBookingId, array $vendorShares): array
    {
        $totalGross = 0;
        $totalCommission = 0;
        $disbursements = [];

        foreach ($vendorShares as $share) {
            $gross = (int) ($share['gross_share_idr'] ?? 0);
            $bps = (int) ($share['commission_rate_bps'] ?? 1000); // 10.0%
            $vendorType = (string) ($share['vendor_type'] ?? 'UNKNOWN');

            $comm = (int) round(($gross * $bps) / 10000);
            $net = max(0, $gross - $comm);

            $totalGross += $gross;
            $totalCommission += $comm;
            $disbursements[$vendorType] = $net;
        }

        return [
            'booking_id' => $packageBookingId,
            'total_gross_package_idr' => $totalGross,
            'total_platform_commission_idr' => $totalCommission,
            'net_vendor_disbursements' => $disbursements,
            'settlement_status' => 'COMMISSION_SETTLED',
        ];
    }
}
