<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Shared\Application\BaseAction;

class SettleMultiVendorDestinationPackageAction extends BaseAction
{
    /**
     * Settle multi-vendor destination holiday package escrow allocations.
     *
     * @param  array<int, array{vendor: string, amount: int, account: string}>  $vendorShares
     * @return array{package: DestinationPackage, total_price_idr: int, vendor_count: int}
     */
    public function handle(
        string $packageCode,
        string $title,
        int $totalPriceIdr,
        array $vendorShares
    ): array {
        return DB::transaction(function () use ($packageCode, $title, $totalPriceIdr, $vendorShares) {
            // Escrow invariant check: sum of vendor allocations must equal total package price
            $sumAllocations = array_sum(array_column($vendorShares, 'amount'));

            if ($sumAllocations !== $totalPriceIdr) {
                throw new \InvalidArgumentException(
                    "Vendor allocation sum (IDR {$sumAllocations}) does not match package total price (IDR {$totalPriceIdr})."
                );
            }

            $package = DestinationPackage::create([
                'id' => (string) Str::uuid(),
                'package_code' => $packageCode,
                'title' => $title,
                'total_price_idr' => $totalPriceIdr,
                'vendor_shares' => json_encode($vendorShares),
            ]);

            $this->audit(
                action: 'hotel.package.settled',
                auditable: $package,
                oldValues: null,
                newValues: [
                    'package_code' => $packageCode,
                    'total_price_idr' => $totalPriceIdr,
                    'vendor_count' => count($vendorShares),
                ],
                correlationId: "htl_pkg_{$packageCode}",
                impactType: 'destination_package_escrow'
            );

            $this->outbox(
                eventType: 'DestinationPackageSettled',
                payload: [
                    'package_id' => $package->id,
                    'package_code' => $packageCode,
                    'total_price_idr' => $totalPriceIdr,
                    'vendor_count' => count($vendorShares),
                ],
                idempotencyKey: hash('sha256', "dest_pkg_{$packageCode}")
            );

            return [
                'package' => $package,
                'total_price_idr' => $totalPriceIdr,
                'vendor_count' => count($vendorShares),
            ];
        });
    }
}
