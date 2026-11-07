<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Algorithms\ServiceRecoveryCompensator;
use Modules\Hotel\Domain\Ledger\HotelPostingHelper;
use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Layanan Resolusi Kompensasi Tamu & Pemulihan Layanan (H5).
 */
final class HotelServiceRecoveryManagerService
{
    public function __construct(
        private readonly HotelPostingHelper $postingHelper
    ) {}

    /**
     * @return array{compensation_amount_idr: int, free_night_granted: bool, ledger_posted: bool}
     */
    public function resolveIncident(
        string|int $reservationId,
        float $serviceDisruptionHours,
        string $incidentCategory,
        string $idempotencyKey
    ): array {
        $reservation = HotelReservation::findOrFail((string) $reservationId);
        $dailyCharge = (int) ($reservation->daily_rate_idr ?: $reservation->total_room_charge_idr ?: 1000000);

        $recovery = ServiceRecoveryCompensator::compensate(
            dailyRoomChargeIdr: $dailyCharge,
            interruptionHours: $serviceDisruptionHours
        );

        $amount = (int) $recovery['compensation_idr'];
        $freeNight = (bool) $recovery['grants_free_night'];

        $this->postingHelper->postServiceRecoveryVoucher(
            reservationId: (string) $reservationId,
            voucherAmountIdr: $amount,
            idempotencyKey: $idempotencyKey
        );

        return [
            'compensation_amount_idr' => $amount,
            'free_night_granted' => $freeNight,
            'ledger_posted' => true,
        ];
    }
}
