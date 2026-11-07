<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hotel\Domain\Adapters\LogisticsShuttleAdapter;
use Modules\Hotel\Domain\Algorithms\ServiceRecoveryCompensator;
use Modules\Hotel\Domain\Ledger\HotelPostingHelper;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelSaga;
use Modules\Hotel\Domain\StateMachines\ReservationStateMachine;

/**
 * Saga Relokasi Overbooking Walk ke Properti Tetangga dengan Kompensasi (D91-2)
 */
final class RelocateOverbookingWalkSagaAction
{
    public function __construct(
        private readonly HotelPostingHelper $postingHelper
    ) {}

    /**
     * @return array{saga_id: int, status: string, target_property_id: int, compensation_voucher_idr: int, shuttle_booking_id: string}
     */
    public function execute(
        int $reservationId,
        int $targetNeighborPropertyId,
        string $idempotencyKey
    ): array {
        return DB::transaction(function () use ($reservationId, $targetNeighborPropertyId, $idempotencyKey): array {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);
            $originProperty = HotelProperty::findOrFail($reservation->property_id);
            $targetProperty = HotelProperty::findOrFail($targetNeighborPropertyId);

            // Validasi status transisi
            ReservationStateMachine::assertCanTransition($reservation->status, ReservationStateMachine::STATUS_WALKED);

            $correlationId = "SAGA-WALK-RES-{$reservationId}-".time();
            $saga = HotelSaga::create([
                'saga_type' => 'OVERBOOKING_WALK_RELOCATION',
                'correlation_id' => $correlationId,
                'current_step' => 'HOLD_NEIGHBOR_ROOM',
                'status' => 'in_progress',
                'payload_json' => [
                    'reservation_id' => $reservationId,
                    'origin_property_id' => $originProperty->id,
                    'target_property_id' => $targetProperty->id,
                    'night_rate' => (int) $reservation->room_rate,
                ],
                'step_history_json' => [
                    ['step' => 'START', 'time' => CarbonImmutable::now()->toIso8601String()],
                ],
            ]);

            // Step 1: Cari kamar fisik di properti tetangga
            $targetRoom = HotelRoom::where('property_id', $targetNeighborPropertyId)
                ->where('status', 'available')
                ->lockForUpdate()
                ->first();

            if ($targetRoom === null) {
                // Saga gagal step 1 -> Rollback & eskalasi Duty Manager
                $saga->update([
                    'status' => 'failed',
                    'error_message' => "Properti tetangga #{$targetNeighborPropertyId} tidak memiliki kamar tersedia.",
                    'step_history_json' => array_merge($saga->step_history_json, [
                        ['step' => 'HOLD_NEIGHBOR_ROOM_FAILED', 'time' => CarbonImmutable::now()->toIso8601String()],
                    ]),
                ]);
                throw new InvalidArgumentException('Relokasi overbooking gagal: kamar di properti tetangga penuh.');
            }

            // Step 2: Pesan Shuttle Transportasi (Logistics)
            $shuttle = LogisticsShuttleAdapter::bookGuestRelocationShuttle(
                $originProperty->id,
                $targetProperty->id,
                (int) ($reservation->user_id ?? 1)
            );

            // Step 3: Hitung Kompensasi Walk via H5 (Overbooking dianggap gangguan berat > 6 jam -> 50% + Free Night)
            $nightRate = (int) $reservation->room_rate;
            $comp = ServiceRecoveryCompensator::compensate($nightRate, 10.0);
            $walkCostIdr = (int) ($nightRate * 2.5); // Biaya kamar tetangga + denda

            // Step 4: Jurnal Akuntansi Beban Walk
            $this->postingHelper->postOverbookingWalkExpense(
                reservationId: $reservation->id,
                walkCostIdr: $walkCostIdr,
                idempotencyKey: "{$idempotencyKey}:WALK:EXP"
            );

            // Jurnal Voucher Contra-Revenue
            $this->postingHelper->postServiceRecoveryVoucher(
                reservationId: $reservation->id,
                voucherAmountIdr: $comp['compensation_amount_idr'],
                idempotencyKey: "{$idempotencyKey}:WALK:VOUCHER"
            );

            // Step 5: Update Status Reservasi ke WALKED
            $reservation->update([
                'status' => ReservationStateMachine::STATUS_WALKED,
                'notes' => "Walked to {$targetProperty->name}. Shuttle: {$shuttle['booking_id']}",
            ]);

            // Tandai kamar tetangga terpakai
            $targetRoom->update(['status' => 'occupied']);

            $saga->update([
                'status' => 'completed',
                'current_step' => 'COMPLETED',
                'step_history_json' => array_merge($saga->step_history_json, [
                    ['step' => 'HOLD_NEIGHBOR_SUCCESS', 'time' => CarbonImmutable::now()->toIso8601String()],
                    ['step' => 'SHUTTLE_DISPATCHED', 'booking' => $shuttle['booking_id']],
                    ['step' => 'LEDGER_POSTED', 'walk_cost' => $walkCostIdr],
                    ['step' => 'COMPLETED', 'time' => CarbonImmutable::now()->toIso8601String()],
                ]),
            ]);

            return [
                'saga_id' => $saga->id,
                'status' => 'completed',
                'target_property_id' => $targetProperty->id,
                'compensation_voucher_idr' => $comp['compensation_amount_idr'],
                'shuttle_booking_id' => $shuttle['booking_id'],
            ];
        });
    }
}
