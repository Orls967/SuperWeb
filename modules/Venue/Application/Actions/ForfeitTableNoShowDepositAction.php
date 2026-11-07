<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Application\Services\VenuePaymentGateway;
use Modules\Venue\Domain\Enums\TableBookingStatus;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Ledger\VenueTransactionTypes;

class ForfeitTableNoShowDepositAction
{
    public function __construct(
        private readonly ?Ledger $ledgerService = null,
        private readonly ?VenuePaymentGateway $paymentGateway = null,
    ) {}

    /**
     * @return array{success: bool, booking: EntertainmentTableBooking, forfeited_amount: int, refunded_amount: int}
     */
    public function handle(
        int $bookingId,
        string $reason,
        string $idempotencyKey
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        $booking = EntertainmentTableBooking::findOrFail($bookingId);

        if ($booking->status === TableBookingStatus::NO_SHOW_FORFEITED->value || $booking->status === 'NO_SHOW') {
            return [
                'success' => true,
                'booking' => $booking,
                'forfeited_amount' => (int) $booking->forfeited_deposit_idr,
                'refunded_amount' => (int) ($booking->deposit_amount_idr - $booking->forfeited_deposit_idr),
            ];
        }

        return DB::transaction(function () use ($booking, $reason, $idempotencyKey) {
            $deposit = (int) $booking->deposit_amount_idr;

            if ($this->paymentGateway !== null) {
                $this->paymentGateway->capture([
                    'amount' => $deposit,
                    'idempotencyKey' => $idempotencyKey,
                ]);
            }

            $booking->update([
                'status' => 'NO_SHOW',
                'forfeited_deposit_idr' => $deposit,
            ]);

            $ledger = $this->ledgerService ?? app(Ledger::class);

            // Jurnal Denda Hangus Legacy: Debit clearing, Kredit ven:vip_noshow_rev:IDR
            $ledger->post(new PostingDTO(
                type: VenueTransactionTypes::VENUE_TABLE_NO_SHOW_FEE->value,
                description: "Denda no-show meja VIP {$booking->booking_code}: {$reason}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ven:ticket_clearing:IDR', $deposit)
                    ->credit('ven:vip_noshow_rev:IDR', $deposit)
                    ->toEntries('IDR'),
                referenceType: 'TABLE_BOOKING',
                referenceId: (string) $booking->id
            ));

            return [
                'success' => true,
                'booking' => $booking,
                'forfeited_amount' => $deposit,
                'refunded_amount' => 0,
            ];
        });
    }
}
