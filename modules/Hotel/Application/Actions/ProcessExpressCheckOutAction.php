<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Services\HotelSmartLockProvisioningService;
use Modules\Hotel\Domain\StateMachines\ReservationStateMachine;

/**
 * Tindakan Check-Out Kilat Mandiri Tamu (Express Mobile Check-Out).
 */
final class ProcessExpressCheckOutAction
{
    public function __construct(
        private readonly ReconcileGuestFolioCreditLimitAction $folioReconciler,
        private readonly HotelSmartLockProvisioningService $keyService
    ) {}

    /**
     * @return array{reservation_id: string, checkout_completed: bool, room_status: string}
     */
    public function execute(string|int $reservationId): array
    {
        $reservation = HotelReservation::findOrFail((string) $reservationId);
        $folio = HotelFolio::where('reservation_id', $reservation->id)->first();

        if ($folio) {
            $rec = $this->folioReconciler->execute($folio->id);
            if ($rec['current_balance_idr'] > 0) {
                abort(422, "Check-out kilat ditolak: Masih terdapat sisa tagihan sebesar Rp {$rec['current_balance_idr']}.");
            }
        }

        $sm = new ReservationStateMachine($reservation->status);
        $reservation->update([
            'status' => $sm->transitionTo(ReservationStateMachine::STATUS_CHECKED_OUT),
        ]);

        if ($reservation->room_id) {
            $room = HotelRoom::find($reservation->room_id);
            if ($room) {
                $room->update(['status' => 'dirty']);
            }
        }

        return [
            'reservation_id' => (string) $reservation->id,
            'checkout_completed' => true,
            'room_status' => 'dirty',
        ];
    }
}
