<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Ret\Domain\Models\RetQuickCommerceOrder;
use Modules\Shared\Application\BaseAction;

class AllocateDarkStorePickPackAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): RetQuickCommerceOrder
    {
        if (empty($params['idempotency_key'])) {
            throw new InvalidArgumentException('Idempotency-Key is required to allocate pick-pack task.');
        }

        $idempotencyKey = (string) $params['idempotency_key'];

        $existing = RetQuickCommerceOrder::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $order = RetOrder::findOrFail($params['order_id']);
        $darkStoreId = (string) $params['dark_store_id'];
        $pickingDurationSeconds = (int) ($params['picking_duration_seconds'] ?? 180);
        $driverType = (string) ($params['driver_type'] ?? 'INTERNAL');
        $driverUserId = isset($params['driver_user_id']) ? (string) $params['driver_user_id'] : null;

        return DB::transaction(function () use ($order, $darkStoreId, $pickingDurationSeconds, $driverType, $driverUserId, $idempotencyKey) {
            $placedAt = Carbon::now();
            $promisedAt = $placedAt->copy()->addMinutes(30);
            $orderCode = 'QC-'.strtoupper(Str::random(10));

            $qcOrder = RetQuickCommerceOrder::create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'order_code' => $orderCode,
                'dark_store_id' => $darkStoreId,
                'customer_id' => $order->customer_id,
                'placed_at' => $placedAt,
                'promised_delivery_at' => $promisedAt,
                'picking_duration_seconds' => $pickingDurationSeconds,
                'is_sla_breached' => false,
                'compensation_credit_minor' => 0,
                'driver_type' => $driverType,
                'driver_user_id' => $driverUserId,
                'status' => 'PICKING',
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->audit(
                action: 'ret_dark_store_pick_pack_allocated',
                auditable: $qcOrder,
                newValues: ['dark_store_id' => $darkStoreId, 'order_code' => $orderCode],
                impactType: 'dark_store_fulfillment',
            );

            return $qcOrder;
        });
    }
}
