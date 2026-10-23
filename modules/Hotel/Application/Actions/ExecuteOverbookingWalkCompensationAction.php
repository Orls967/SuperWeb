<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Shared\Application\BaseAction;

class ExecuteOverbookingWalkCompensationAction extends BaseAction
{
    /**
     * Relocate walked overbooked guest to partner hotel with transport allowance and compensation credits.
     *
     * @return array{reservation: HotelReservation, alternate_property_name: string, transport_allowance_idr: int, goodwill_credit_idr: int, total_compensation_idr: int}
     */
    public function handle(
        string $reservationId,
        string $alternatePropertyName,
        int $transportAllowanceIdr = 350000,
        int $goodwillCreditIdr = 1000000
    ): array {
        return DB::transaction(function () use ($reservationId, $alternatePropertyName, $transportAllowanceIdr, $goodwillCreditIdr) {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);

            $reservation->status = 'RELOCATED_WALK';
            $reservation->save();

            $complimentaryNightCostIdr = (int) $reservation->daily_rate_idr;
            $totalCompensationIdr = $complimentaryNightCostIdr + $transportAllowanceIdr + $goodwillCreditIdr;

            $this->audit(
                action: 'hotel.overbooking.walk_compensated',
                auditable: $reservation,
                oldValues: ['status' => 'confirmed'],
                newValues: [
                    'reservation_number' => $reservation->reservation_number,
                    'alternate_property' => $alternatePropertyName,
                    'transport_allowance_idr' => $transportAllowanceIdr,
                    'goodwill_credit_idr' => $goodwillCreditIdr,
                    'total_compensation_idr' => $totalCompensationIdr,
                ],
                correlationId: "htl_walk_{$reservation->reservation_number}",
                impactType: 'overbooking_compensation'
            );

            $this->outbox(
                eventType: 'GuestOverbookingWalkCompensated',
                payload: [
                    'reservation_id' => $reservation->id,
                    'guest_user_id' => $reservation->guest_user_id,
                    'alternate_property' => $alternatePropertyName,
                    'total_compensation_idr' => $totalCompensationIdr,
                ],
                idempotencyKey: hash('sha256', "walk_{$reservation->id}")
            );

            return [
                'reservation' => $reservation,
                'alternate_property_name' => $alternatePropertyName,
                'transport_allowance_idr' => $transportAllowanceIdr,
                'goodwill_credit_idr' => $goodwillCreditIdr,
                'total_compensation_idr' => $totalCompensationIdr,
            ];
        });
    }
}
