<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\VenueEconomyLedgerService;
use Modules\Venue\Domain\Models\FestivalBundleAllocation;

final class RedeemFestivalBundleComponentAction extends BaseAction
{
    public function __construct(
        private readonly VenueEconomyLedgerService $ledgerService
    ) {}

    public function execute(
        int $allocationId
    ): FestivalBundleAllocation {
        return DB::transaction(function () use ($allocationId) {
            $allocation = FestivalBundleAllocation::query()->with('order')->lockForUpdate()->findOrFail($allocationId);

            if ($allocation->status === 'settled') {
                throw new InvalidArgumentException("Komponen alokasi {$allocation->component_name} sudah berstatus settled.");
            }

            $order = $allocation->order;

            // 1. Posting Buku Besar Amortisasi Unearned ke Vendor Payable & Fee
            $this->ledgerService->postFestivalBundleRedemption(
                orderNumber: $order->order_number,
                componentName: $allocation->component_name,
                allocatedAmountIdr: $allocation->allocated_amount_idr,
                platformFeeIdr: $allocation->platform_fee_idr,
                vendorNetIdr: $allocation->vendor_net_idr
            );

            // 2. Perbarui Status Alokasi
            $allocation->update(['status' => 'settled']);

            // 3. Periksa bila seluruh komponen telah lunas / terealisasi
            $unsettledCount = FestivalBundleAllocation::query()
                ->where('order_id', $order->id)
                ->where('status', '!=', 'settled')
                ->count();

            if ($unsettledCount === 0) {
                $order->update(['status' => 'settled']);
            } else {
                $order->update(['status' => 'fulfilled_partial']);
            }

            return $allocation;
        });
    }
}
