<?php

declare(strict_types=1);

namespace Modules\Ret\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Ret\Domain\Models\RetOrder;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class ProcessClickAndCollectLockerPickupAction extends BaseAction
{
    /**
     * @return array{order_number: string, locker_id: string, pickup_status: string}
     */
    public function execute(string $orderNumber, string $lockerId, string $otpToken): array
    {
        $order = RetOrder::where('order_number', $orderNumber)->firstOrFail();

        if ($order->status === 'FULFILLED') {
            throw new RuntimeException("Order [{$order->order_number}] has already been collected.");
        }

        // Expected OTP verification (simple deterministic hash match or length check)
        if (strlen($otpToken) < 4) {
            throw new RuntimeException('Invalid OTP pickup token.');
        }

        return DB::transaction(function () use ($order, $lockerId) {
            $order->update(['status' => 'FULFILLED']);

            $this->audit(
                action: 'ret_click_and_collect_completed',
                auditable: $order,
                newValues: ['locker_id' => $lockerId, 'status' => 'FULFILLED'],
                impactType: 'store_fulfillment',
            );

            return [
                'order_number' => $order->order_number,
                'locker_id' => $lockerId,
                'pickup_status' => 'PICKED_UP',
            ];
        });
    }
}
