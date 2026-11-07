<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Settlement;

use InvalidArgumentException;

final class BundleSspVerifier
{
    private const SECRET = 'shared-bundle-ssp-allocator-secret-v1';

    /**
     * @param  array<int, array{name: string, vendor_id: string, ssp_idr: int, allocated_amount_idr: int, platform_fee_idr: int, vendor_net_idr: int}>  $items
     */
    public static function sign(
        int $bundlePriceIdr,
        int $totalSspIdr,
        int $platformFeeBps,
        array $items
    ): string {
        $itemTokens = [];
        foreach ($items as $item) {
            $itemTokens[] = implode('|', [
                $item['name'],
                $item['vendor_id'],
                $item['ssp_idr'],
                $item['allocated_amount_idr'],
                $item['platform_fee_idr'],
                $item['vendor_net_idr'],
            ]);
        }

        $payload = implode('::', [
            $bundlePriceIdr,
            $totalSspIdr,
            $platformFeeBps,
            implode(';', $itemTokens),
        ]);

        return hash_hmac('sha256', $payload, self::SECRET);
    }

    public static function verify(BundleSspCertificate $certificate): bool
    {
        $expectedSignature = self::sign(
            $certificate->bundlePriceIdr,
            $certificate->totalSspIdr,
            $certificate->platformFeeBps,
            $certificate->items
        );

        if (! hash_equals($expectedSignature, $certificate->hmacSignature)) {
            throw new InvalidArgumentException('BundleSspCertificate HMAC signature is invalid or tampered.');
        }

        // Invarian 1: Total Alokasi Komponen Wajib Sama Persis dengan Bundle Price
        $totalAllocated = 0;
        $totalVendorNet = 0;
        $totalFee = 0;
        $sumSsp = 0;

        foreach ($certificate->items as $item) {
            $sumSsp += $item['ssp_idr'];
            $totalAllocated += $item['allocated_amount_idr'];
            $totalFee += $item['platform_fee_idr'];
            $totalVendorNet += $item['vendor_net_idr'];

            if ($item['allocated_amount_idr'] !== ($item['vendor_net_idr'] + $item['platform_fee_idr'])) {
                throw new InvalidArgumentException("Item {$item['name']} allocation sum mismatch with vendor net and fee");
            }
        }

        if ($sumSsp !== $certificate->totalSspIdr) {
            throw new InvalidArgumentException('Total SSP mismatch.');
        }

        if ($totalAllocated !== $certificate->bundlePriceIdr) {
            throw new InvalidArgumentException("Total allocated ({$totalAllocated}) does not equal bundle price ({$certificate->bundlePriceIdr}).");
        }

        if (($totalVendorNet + $totalFee) !== $certificate->bundlePriceIdr) {
            throw new InvalidArgumentException('Conservation of money violated: vendorNet + platformFee != bundlePrice.');
        }

        return true;
    }
}
