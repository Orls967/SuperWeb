<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

class ReserveTableWithMinimumSpendDepositAction extends BaseAction
{
    /**
     * Reserve VIP table or daybed with minimum spend deposit hold.
     *
     * @return array{booking: EntertainmentTableBooking, booking_code: string, deposit_amount_idr: int, minimum_spend_idr: int}
     */
    public function handle(
        int $eventId,
        int $userId,
        string $tableNumber,
        int $minimumSpendIdr,
        int $depositAmountIdr
    ): array {
        return DB::transaction(function () use ($eventId, $userId, $tableNumber, $minimumSpendIdr, $depositAmountIdr) {
            $event = EntertainmentEvent::lockForUpdate()->findOrFail($eventId);

            // Verify table is not already held or booked for this event
            $alreadyBooked = EntertainmentTableBooking::where('event_id', $event->id)
                ->where('table_number', $tableNumber)
                ->whereIn('status', ['HELD', 'SEATED', 'COMPLETED'])
                ->exists();

            if ($alreadyBooked) {
                throw new \RuntimeException("Table {$tableNumber} is already reserved for this event.");
            }

            $bookingCode = 'VIP-'.strtoupper($tableNumber).'-'.substr(bin2hex(random_bytes(4)), 0, 8);

            $booking = EntertainmentTableBooking::create([
                'booking_code' => $bookingCode,
                'event_id' => $event->id,
                'table_number' => $tableNumber,
                'user_id' => $userId,
                'minimum_spend_idr' => $minimumSpendIdr,
                'deposit_amount_idr' => $depositAmountIdr,
                'status' => 'HELD',
            ]);

            $this->audit(
                action: 'venue.table.reserved',
                auditable: $booking,
                oldValues: null,
                newValues: [
                    'event_id' => $event->id,
                    'table_number' => $tableNumber,
                    'user_id' => $userId,
                    'minimum_spend_idr' => $minimumSpendIdr,
                    'deposit_amount_idr' => $depositAmountIdr,
                ],
                correlationId: "ven_table_{$bookingCode}",
                impactType: 'vip_bottle_service'
            );

            $this->outbox(
                eventType: 'VipTableReserved',
                payload: [
                    'booking_id' => $booking->id,
                    'booking_code' => $bookingCode,
                    'event_id' => $event->id,
                    'table_number' => $tableNumber,
                    'deposit_amount_idr' => $depositAmountIdr,
                ],
                idempotencyKey: hash('sha256', "vip_res_{$bookingCode}")
            );

            return [
                'booking' => $booking,
                'booking_code' => $bookingCode,
                'deposit_amount_idr' => $depositAmountIdr,
                'minimum_spend_idr' => $minimumSpendIdr,
            ];
        });
    }
}
