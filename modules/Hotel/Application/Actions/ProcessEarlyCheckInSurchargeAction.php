<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Ledger\HotelPostingHelper;
use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Tindakan Pembebanan Biaya Check-In Lebih Awal (Early Arrival Surcharge).
 */
final class ProcessEarlyCheckInSurchargeAction
{
    public function __construct(
        private readonly HotelPostingHelper $postingHelper
    ) {}

    /**
     * @return array{surcharge_amount_idr: int, applied: bool}
     */
    public function execute(string|int $reservationId, string $arrivalTime, string $idempotencyKey): array
    {
        $reservation = HotelReservation::findOrFail((string) $reservationId);
        $arrivalHour = (int) explode(':', $arrivalTime)[0];

        $surchargeAmount = 0;
        if ($arrivalHour < 10) {
            // Sebelum jam 10:00 dikenakan 50% tarif dasar
            $surchargeAmount = (int) round(($reservation->daily_rate_idr ?: 1000000) * 0.50);
        } elseif ($arrivalHour < 13) {
            // Antara jam 10:00 - 13:00 dikenakan 25% tarif dasar
            $surchargeAmount = (int) round(($reservation->daily_rate_idr ?: 1000000) * 0.25);
        }

        if ($surchargeAmount > 0) {
            $this->postingHelper->postEarlyLateSurcharge(
                reservationId: (string) $reservationId,
                amountIdr: $surchargeAmount,
                type: 'EARLY_CHECKIN',
                idempotencyKey: $idempotencyKey
            );
        }

        return [
            'surcharge_amount_idr' => $surchargeAmount,
            'applied' => $surchargeAmount > 0,
        ];
    }
}
