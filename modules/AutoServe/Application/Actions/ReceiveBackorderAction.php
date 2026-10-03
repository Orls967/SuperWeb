<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use Exception;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Estimate;
use Modules\Shared\Application\BaseAction;
use Modules\Store\Domain\Models\Order;

/**
 * Sparepart backorder tiba di bengkel: stok masuk dan booking kembali dikerjakan.
 */
class ReceiveBackorderAction extends BaseAction
{
    public function __construct(
        private readonly BackorderPartsAction $backorderParts,
    ) {}

    public function execute(Estimate $estimate): Estimate
    {
        return $this->transaction(function () use ($estimate) {
            /** @var Estimate $estimate */
            $estimate = Estimate::query()->lockForUpdate()->findOrFail($estimate->id);
            $estimate->loadMissing('booking');

            $order = $estimate->backorder_order_id
                ? Order::with('items.product')->find($estimate->backorder_order_id)
                : null;

            if ($order === null) {
                throw new Exception('Tidak ada backorder sparepart untuk estimasi ini.');
            }

            // receive() locks the order and is idempotent if already completed;
            // keep the booking transition atomic with stock receipt.
            $this->backorderParts->receive($order);

            $booking = $estimate->booking;
            if ($booking !== null && $booking->isWaitingParts()) {
                $booking->transitionTo(BookingStatus::InProgress);
            }

            return $estimate->fresh('booking');
        });
    }
}
