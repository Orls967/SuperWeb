<?php

declare(strict_types=1);

namespace Modules\AutoServe\Application\Actions;

use Exception;
use Modules\AutoServe\Domain\Enums\BookingStatus;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Domain\Enums\PaymentIntentStatus;
use Modules\Shared\Application\BaseAction;

/**
 * Batalkan booking dan lepaskan kembali dana escrow estimasi ke dompet customer.
 */
class CancelBookingAction extends BaseAction
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway,
    ) {}

    public function execute(Booking $booking, string $reason = 'Booking dibatalkan'): Booking
    {
        if ($booking->isCancelled()) {
            throw new Exception('Booking ini sudah dibatalkan sebelumnya.');
        }

        if ($booking->isInvoiced()) {
            throw new Exception('Booking yang sudah ditagih tidak dapat dibatalkan.');
        }

        return $this->transaction(function () use ($booking, $reason) {
            $estimate = $booking->approvedEstimate();

            if ($estimate !== null) {
                $heldIntent = $estimate->paymentIntents()
                    ->where('status', PaymentIntentStatus::HELD->value)
                    ->latest()
                    ->first();

                if ($heldIntent !== null) {
                    $this->paymentGateway->release($heldIntent, 'estimate_release_'.$estimate->uuid);
                }
            }

            $booking->update([
                'mechanic_notes' => $reason,
            ]);

            $booking->transitionTo(BookingStatus::Cancelled);

            return $booking->fresh();
        });
    }
}
