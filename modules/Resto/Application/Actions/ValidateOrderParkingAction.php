<?php

declare(strict_types=1);

namespace Modules\Resto\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mall\Contracts\ParkingValidationResult;
use Modules\Mall\Contracts\ParkingValidator;
use Modules\Resto\Domain\Exceptions\InvalidOrderOperationException;
use Modules\Resto\Domain\Models\Order;

class ValidateOrderParkingAction
{
    public function __construct(
        protected ParkingValidator $parkingValidator,
    ) {}

    public function handle(Order $order, string $ticketNumber): ParkingValidationResult
    {
        $outletCode = $order->outlet?->code ?: 'DM-01';
        $spendAmount = (int) ($order->subtotal > 0 ? $order->subtotal : $order->grand_total);

        if ($spendAmount <= 0) {
            throw new InvalidOrderOperationException('Pesanan harus memiliki nominal belanja sebelum tiket parkir dapat divalidasi.');
        }

        // Both steps are plain DB writes on this connection, reached through the
        // Modules\Mall\Contracts\ParkingValidator contract (no other module's Domain is
        // imported), so they can share one transaction: a ticket is never consumed without
        // the order being updated, and a failure is fully retryable.
        return DB::transaction(function () use ($order, $ticketNumber, $outletCode, $spendAmount) {
            $result = $this->parkingValidator->validateTicket(
                ticketNumber: $ticketNumber,
                tenantExternalRef: $outletCode,
                spendAmount: $spendAmount
            );

            $order->update([
                'parking_ticket_number' => $result->ticketNumber,
                'parking_validation_hours' => $result->freeHours,
            ]);

            return $result;
        });
    }
}
