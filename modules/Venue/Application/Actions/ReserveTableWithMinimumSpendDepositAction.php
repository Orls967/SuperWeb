<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\VenuePaymentGateway;
use Modules\Venue\Domain\Enums\TableBookingStatus;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\StateMachines\TableBookingStateMachine;
use Modules\Venue\Ledger\VenueTransactionTypes;

class ReserveTableWithMinimumSpendDepositAction extends BaseAction
{
    public function __construct(
        private readonly ?VenuePaymentGateway $paymentGateway = null,
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * @return array{booking: EntertainmentTableBooking, booking_code: string, deposit_amount_idr: int, minimum_spend_idr: int}
     */
    public function handle(
        int $eventId,
        int $userId,
        string $tableNumber,
        int $minimumSpendIdr,
        int $depositAmountIdr,
        string $idempotencyKey = ''
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        return DB::transaction(function () use ($eventId, $userId, $tableNumber, $minimumSpendIdr, $depositAmountIdr, $idempotencyKey) {
            $event = EntertainmentEvent::lockForUpdate()->findOrFail($eventId);

            // Check idempotency
            $existing = EntertainmentTableBooking::where('event_id', $event->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return [
                    'booking' => $existing,
                    'booking_code' => (string) $existing->booking_code,
                    'deposit_amount_idr' => (int) $existing->deposit_amount_idr,
                    'minimum_spend_idr' => (int) $existing->minimum_spend_idr,
                ];
            }

            // Verify table is not already held or booked for this event
            $alreadyBooked = EntertainmentTableBooking::where('event_id', $event->id)
                ->where('table_number', $tableNumber)
                ->whereIn('status', [
                    TableBookingStatus::DEPOSIT_HELD->value,
                    TableBookingStatus::CONFIRMED->value,
                    TableBookingStatus::SEATED->value,
                    TableBookingStatus::SETTLED->value,
                    'HELD', 'SEATED', 'COMPLETED',
                ])
                ->exists();

            if ($alreadyBooked) {
                throw new \RuntimeException("Meja {$tableNumber} sudah dipesan untuk event ini");
            }

            $count = EntertainmentTableBooking::where('event_id', $event->id)
                ->where('table_number', $tableNumber)
                ->count();

            $bookingCode = sprintf('VIP-%s-%04d', strtoupper($tableNumber), $count + 1);

            // Authorize deposit via payment gateway
            if ($this->paymentGateway !== null) {
                $this->paymentGateway->authorize([
                    'amount' => $depositAmountIdr,
                    'idempotencyKey' => $idempotencyKey,
                    'user_id' => $userId,
                    'booking_code' => $bookingCode,
                ]);
            }

            $booking = EntertainmentTableBooking::create([
                'booking_code' => $bookingCode,
                'event_id' => $event->id,
                'table_number' => $tableNumber,
                'user_id' => $userId,
                'minimum_spend_idr' => $minimumSpendIdr,
                'deposit_amount_idr' => $depositAmountIdr,
                'idempotency_key' => $idempotencyKey,
                'status' => TableBookingStatus::CONFIRMED->value,
            ]);

            // Posting ledger: Debit vip_escrow, Kredit vip_unearned_rev
            $ledger = $this->ledger ?? app(Ledger::class);
            $ledger->post(new PostingDTO(
                type: VenueTransactionTypes::VENUE_VIP_TABLE_ESCROW->value,
                description: "Hold deposit meja VIP {$bookingCode} event {$event->event_code}",
                idempotencyKey: "ven:ledger:vip_escrow:{$bookingCode}",
                entries: Posting::lines()
                    ->debit('ven:vip_escrow:IDR', $depositAmountIdr)
                    ->credit('ven:vip_unearned_rev:IDR', $depositAmountIdr)
                    ->toEntries('IDR'),
                referenceType: 'VENUE_TABLE_BOOKING',
                referenceId: (string) $booking->id
            ));

            return [
                'booking' => $booking,
                'booking_code' => $bookingCode,
                'deposit_amount_idr' => $depositAmountIdr,
                'minimum_spend_idr' => $minimumSpendIdr,
            ];
        });
    }
}
