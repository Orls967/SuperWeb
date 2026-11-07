<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Ledger\HotelPostingHelper;
use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Tindakan Pembebanan Biaya Check-Out Lebih Lambat (Late Departure Surcharge).
 */
final class ProcessLateCheckOutSurchargeAction
{
    public function __construct(
        private readonly HotelPostingHelper $postingHelper
    ) {}

    /**
     * @return array{surcharge_amount_idr: int, applied: bool}
     */
    public function execute(string|int $reservationId, string $departureTime, string $idempotencyKey): array
    {
        $reservation = HotelReservation::findOrFail((string) $reservationId);
        $depHour = (int) explode(':', $departureTime)[0];

        $surchargeAmount = 0;
        if ($depHour >= 18) {
            // Melewati jam 18:00 dikenakan biaya 1 malam penuh
            $surchargeAmount = (int) ($reservation->daily_rate_idr ?: 1000000);
        } elseif ($depHour > 13) {
            // Antara 13:00 - 18:00 dikenakan 50% tarif dasar
            $surchargeAmount = (int) round(($reservation->daily_rate_idr ?: 1000000) * 0.50);
        }

        if ($surchargeAmount > 0) {
            $this->postingHelper->postEarlyLateSurcharge(
                reservationId: (string) $reservationId,
                amountIdr: $surchargeAmount,
                type: 'LATE_CHECKOUT',
                idempotencyKey: $idempotencyKey
            );
        }

        return [
            'surcharge_amount_idr' => $surchargeAmount,
            'applied' => $surchargeAmount > 0,
        ];
    }
}
