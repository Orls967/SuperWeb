<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationInput;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocator;
use Modules\Venue\Application\Services\VenueEconomyLedgerService;
use Modules\Venue\Domain\Models\FestivalBundleAllocation;
use Modules\Venue\Domain\Models\FestivalBundleOrder;
use Modules\Venue\Domain\StateMachines\FestivalBundleStateMachine;

final class AllocateFestivalBundleSspAction extends BaseAction
{
    public function __construct(
        private readonly BundleSspAllocator $allocator,
        private readonly VenueEconomyLedgerService $ledgerService
    ) {}

    public function execute(
        int $eventId,
        string $bundleCode,
        int $customerId,
        BundleSspAllocationInput $input
    ): FestivalBundleOrder {
        return DB::transaction(function () use ($eventId, $bundleCode, $customerId, $input) {
            // 1. Alokasikan via Algoritma V6 SSP Hamilton-Hare
            $allocationResult = $this->allocator->allocate($input);

            // 2. Validasi Sertifikat Komputasi
            $allocationResult->certificate->isValid();

            // 3. Generate Nomor Pesanan Unik Deterministic
            $hashEntropy = hash('sha256', "{$eventId}:{$bundleCode}:{$customerId}:{$input->bundlePriceIdr}");
            $orderNumber = 'BND-ORD-'.strtoupper(substr($hashEntropy, 0, 10));

            // 4. Catat Order Bundle
            $order = FestivalBundleOrder::query()->create([
                'event_id' => $eventId,
                'customer_id' => $customerId,
                'order_number' => $orderNumber,
                'bundle_code' => $bundleCode,
                'bundle_price_idr' => $input->bundlePriceIdr,
                'status' => FestivalBundleStateMachine::STATUS_PAID,
            ]);

            // 5. Simpan Rincian Komponen Alokasi Multi-Vendor
            foreach ($allocationResult->items as $item) {
                FestivalBundleAllocation::query()->create([
                    'order_id' => $order->id,
                    'component_name' => $item['name'],
                    'vendor_code' => $item['vendor_id'],
                    'ssp_idr' => $item['ssp_idr'],
                    'allocated_amount_idr' => $item['allocated_amount_idr'],
                    'platform_fee_idr' => $item['platform_fee_idr'],
                    'vendor_net_idr' => $item['vendor_net_idr'],
                    'status' => 'unearned',
                ]);
            }

            // 6. Posting Penerimaan Kas & Unearned Revenue di Subledger
            $this->ledgerService->postFestivalBundleSale(
                orderNumber: $orderNumber,
                bundlePriceIdr: $input->bundlePriceIdr
            );

            return $order->load('allocations');
        });
    }
}
