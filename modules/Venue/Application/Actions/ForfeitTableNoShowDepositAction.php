<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Application\Services\VenuePaymentGateway;
use Modules\Venue\Domain\Algorithms\MinimumSpendSettlement;
use Modules\Venue\Domain\Enums\TableBookingStatus;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\StateMachines\TableBookingStateMachine;
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
            $minSpend = (int) $booking->minimum_spend_idr;

            // Hitung V4 MinimumSpendSettlement
            $settlement = MinimumSpendSettlement::calculate(
                minimumSpendIdr: $minSpend,
                depositHeldIdr: $deposit,
                actualSpendIdr: 0,
                isNoShow: true,
                noShowForfeitureBps: 5000 // 50%
            );

            if ($this->paymentGateway !== null) {
                $this->paymentGateway->capture([
                    'amount' => $settlement->forfeitedDepositIdr,
                    'idempotencyKey' => $idempotencyKey,
                ]);
            }

            $booking->update([
                'status' => TableBookingStatus::NO_SHOW_FORFEITED->value,
                'forfeited_deposit_idr' => $settlement->forfeitedDepositIdr,
            ]);

            $ledger = $this->ledgerService ?? app(Ledger::class);

            // Jurnal Denda Hangus: Debit vip_unearned_rev, Kredit vip_noshow_rev
            $ledger->post(new PostingDTO(
                type: VenueTransactionTypes::VENUE_TABLE_NO_SHOW_FEE->value,
                description: "Denda no-show 50% meja VIP {$booking->booking_code}: {$reason}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ven:vip_unearned_rev:IDR', $settlement->forfeitedDepositIdr)
                    ->credit('ven:vip_noshow_rev:IDR', $settlement->forfeitedDepositIdr)
                    ->toEntries('IDR'),
                referenceType: 'VENUE_TABLE_BOOKING',
                referenceId: (string) $booking->id
            ));

            // Jurnal Refund Sisa 50%: Debit vip_unearned_rev, Kredit vip_escrow
            if ($settlement->refundedDepositIdr > 0) {
                $ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_TABLE_NO_SHOW_FEE->value,
                    description: "Refund sisa deposit 50% no-show meja VIP {$booking->booking_code}",
                    idempotencyKey: "{$idempotencyKey}:refund",
                    entries: Posting::lines()
                        ->debit('ven:vip_unearned_rev:IDR', $settlement->refundedDepositIdr)
                        ->credit('ven:vip_escrow:IDR', $settlement->refundedDepositIdr)
                        ->toEntries('IDR'),
                    referenceType: 'VENUE_TABLE_BOOKING',
                    referenceId: (string) $booking->id
                ));
            }

            return [
                'success' => true,
                'booking' => $booking,
                'forfeited_amount' => $settlement->forfeitedDepositIdr,
                'refunded_amount' => $settlement->refundedDepositIdr,
            ];
        });
    }
}
