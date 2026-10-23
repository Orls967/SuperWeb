<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;
use Modules\Shared\Application\BaseAction;

class AllocateDarkStorePickPackOrderAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetQuickCommerceOrder
    {
        return DB::transaction(function () use ($params) {
            $darkStoreId = (string) $params['dark_store_id'];
            $customerId = (string) $params['customer_id'];
            $placedAt = $params['placed_at'] ?? Carbon::now();
            $promisedAt = Carbon::parse($placedAt)->addMinutes(30);

            $orderCode = 'QC-'.strtoupper(substr(hash('sha256', "{$darkStoreId}:{$customerId}:".microtime()), 0, 10));

            $qcOrder = RetQuickCommerceOrder::create([
                'id' => (string) Str::uuid(),
                'order_code' => $orderCode,
                'dark_store_id' => $darkStoreId,
                'customer_id' => $customerId,
                'placed_at' => $placedAt,
                'promised_delivery_at' => $promisedAt,
                'picking_duration_seconds' => (int) ($params['picking_duration_seconds'] ?? 360),
                'is_sla_breached' => false,
                'status' => 'DELIVERED',
            ]);

            $this->audit(
                action: 'ret_dark_store_allocated',
                auditable: $qcOrder,
                newValues: ['dark_store_id' => $darkStoreId, 'order_code' => $orderCode],
                impactType: 'dark_store_ops',
            );

            return $qcOrder;
        });
    }
}
