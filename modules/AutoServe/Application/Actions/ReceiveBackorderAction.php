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
        $estimate->loadMissing('booking');
        $order = $estimate->backorder_order_id
            ? Order::with('items.product')->find($estimate->backorder_order_id)
            : null;

        if ($order === null) {
            throw new Exception('Tidak ada backorder sparepart untuk estimasi ini.');
        }

        $this->backorderParts->receive($order);

        $booking = $estimate->booking;
        if ($booking !== null && $booking->isWaitingParts()) {
            $booking->transitionTo(BookingStatus::InProgress);
        }

        return $estimate->fresh('booking');
    }
}
