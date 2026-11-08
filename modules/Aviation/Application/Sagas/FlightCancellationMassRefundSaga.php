<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Sagas;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Actions\ProcessFareRefundAction;
use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationRefund;
use Modules\Aviation\Domain\Models\AviationSagaInstance;
use Modules\Aviation\Domain\Models\AviationTicket;

/**
 * Saga Pembatalan Penerbangan & Refund Massal Otomatis (T-4).
 * Alur: CANCEL_FLIGHT -> ITERATE_TICKETS -> EXECUTE_A4_FULL_REFUND -> JOURNAL_LEDGER.
 */
class FlightCancellationMassRefundSaga
{
    public function __construct(
        private readonly ProcessFareRefundAction $refundAction
    ) {
    }

    /**
     * @param int $flightId
     * @param string $cancellationReason
     * @param string $correlationId
     * @return array{saga_id: int, status: string, refunded_tickets: int, total_refund_minor: int}
     */
    public function run(int $flightId, string $cancellationReason, string $correlationId): array
    {
        return DB::transaction(function () use ($flightId, $cancellationReason, $correlationId) {
            $saga = AviationSagaInstance::firstOrCreate(
                ['correlation_id' => $correlationId],
                [
                    'saga_type' => 'FLIGHT_CANCELLATION_REFUND',
                    'current_step' => 'STARTED',
                    'status' => 'started',
                    'payload_json' => [
                        'flight_id' => $flightId,
                        'reason' => $cancellationReason,
                    ],
                    'log_json' => [],
                ]
            );

            $flight = AviationFlight::with('tickets')->findOrFail($flightId);

            // Step 1: Batalkan penerbangan
            $flight->transitionTo(FlightStatus::CANCELLED, 'AIRLINE_CANCELLED', null, $cancellationReason);
            $saga->current_step = 'FLIGHT_CANCELLED';
            $saga->save();

            // Step 2: Ambil semua tiket yang valid untuk refund
            $tickets = AviationTicket::where('flight_id', $flightId)
                ->whereIn('status', ['paid', 'issued', 'checked_in'])
                ->get();

            $refundedCount = 0;
            $totalRefundMinor = 0;

            foreach ($tickets as $ticket) {
                $refKey = "avi:mass_refund:{$ticket->id}:{$correlationId}";
                $refund = $this->refundAction->execute(
                    ticketId: $ticket->id,
                    reason: "Flight cancelled by airline: {$cancellationReason}",
                    idempotencyKey: $refKey,
                    hoursBeforeFlight: null,
                    isCancelledByAirline: true // 100% refund tanpa admin fee
                );

                $refundedCount++;
                $totalRefundMinor += (int) $refund->net_refund_minor;
            }

            $saga->current_step = 'COMPLETED';
            $saga->status = 'completed';
            $saga->log_json = [
                ['step' => 'COMPLETED', 'tickets_refunded' => $refundedCount, 'total_minor' => $totalRefundMinor, 'timestamp' => now()->toIso8601String()]
            ];
            $saga->save();

            return [
                'saga_id' => $saga->id,
                'status' => 'completed',
                'refunded_tickets' => $refundedCount,
                'total_refund_minor' => $totalRefundMinor,
            ];
        });
    }
}

