<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hotel\Domain\Algorithms\ServiceRecoveryCompensator;
use Modules\Hotel\Domain\Ledger\HotelPostingHelper;
use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Penyelesaian Kompensasi Gangguan Fasilitas Tamu H5 ke Folio (D91-6)
 */
final class ResolveServiceRecoveryAction
{
    public function __construct(
        private readonly HotelPostingHelper $postingHelper
    ) {}

    /**
     * @param string $reservationId
     * @param float $excessHours
     * @param string $issueDescription
     * @param string $idempotencyKey
     * @return array{compensation_amount_idr: int, grants_free_night: bool}
     */
    public function execute(
        string $reservationId,
        float $excessHours,
        string $issueDescription,
        string $idempotencyKey
    ): array {
        return DB::transaction(function () use ($reservationId, $excessHours, $issueDescription, $idempotencyKey): array {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);
            $nightRate = (int) ($reservation->daily_rate_idr ?? 1500000);

            $comp = ServiceRecoveryCompensator::compensate($nightRate, $excessHours);

            // Jurnal Voucher Contra-Revenue
            $this->postingHelper->postServiceRecoveryVoucher(
                reservationId: (int) abs(crc32($reservation->id)),
                voucherAmountIdr: $comp['compensation_amount_idr'],
                idempotencyKey: $idempotencyKey
            );

            return [
                'compensation_amount_idr' => $comp['compensation_amount_idr'],
                'grants_free_night' => $comp['grants_free_night'],
            ];
        });
    }
}
