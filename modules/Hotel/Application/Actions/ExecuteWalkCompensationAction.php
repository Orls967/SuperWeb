<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class ExecuteWalkCompensationAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * @return array{
     *     reservation: HotelReservation,
     *     alternate_property_name: string,
     *     total_compensation_idr: int
     * }
     */
    public function execute(
        string $reservationId,
        string $alternatePropertyName,
        string $idempotencyKey,
        ?int $compensationAmountIdr = null,
        int $transportAllowanceIdr = 350000,
        int $goodwillCreditIdr = 1000000,
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency-Key diperlukan untuk walk compensation.');
        }

        return DB::transaction(function () use (
            $reservationId,
            $alternatePropertyName,
            $idempotencyKey,
            $compensationAmountIdr,
            $transportAllowanceIdr,
            $goodwillCreditIdr,
        ) {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);

            $complimentaryNight = (int) $reservation->daily_rate_idr;
            if ($complimentaryNight <= 0) {
                $complimentaryNight = 1000000;
            }

            $totalCompensation = $compensationAmountIdr ?? ($complimentaryNight + $transportAllowanceIdr + $goodwillCreditIdr);

            // Double-entry ledger: beban kompensasi walk seimbang dengan clearing
            if ($this->ledger) {
                $this->ledger->post(new PostingDTO(
                    type: HotelTransactionTypes::HOTEL_OVERBOOKING_WALK->value,
                    description: "Kompensasi walk overbooking reservasi {$reservation->reservation_number} ke {$alternatePropertyName}",
                    idempotencyKey: "htl:walk:{$idempotencyKey}",
                    entries: [
                        PostingEntryDTO::forCode('htl:overbooking_expense:IDR', 'IDR', -$totalCompensation),
                        PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $totalCompensation),
                    ],
                    referenceType: 'htl_reservations',
                    referenceId: $reservation->id,
                ));
            }

            // Lepaskan room nights yang mungkin teralokasi
            HotelRoomNight::where('reservation_id', $reservation->id)->delete();

            $reservation->update(['status' => 'relocated_walk']);

            return [
                'reservation' => $reservation,
                'alternate_property_name' => $alternatePropertyName,
                'total_compensation_idr' => $totalCompensation,
            ];
        });
    }

    public function handle(
        string $reservationId,
        string $alternatePropertyName,
        int $transportAllowanceIdr = 350000,
        int $goodwillCreditIdr = 1000000,
        ?string $idempotencyKey = null,
    ): array {
        $key = $idempotencyKey ?? "walk_legacy_{$reservationId}";

        return $this->execute(
            reservationId: $reservationId,
            alternatePropertyName: $alternatePropertyName,
            idempotencyKey: $key,
            transportAllowanceIdr: $transportAllowanceIdr,
            goodwillCreditIdr: $goodwillCreditIdr,
        );
    }
}
