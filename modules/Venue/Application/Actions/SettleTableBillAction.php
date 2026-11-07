<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Algorithms\MinimumSpendSettlement;
use Modules\Venue\Domain\Enums\TableBookingStatus;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\StateMachines\TableBookingStateMachine;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class SettleTableBillAction
{
    public function __construct(
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * @return array{
     *     booking: EntertainmentTableBooking,
     *     total_bill_idr: int,
     *     captured_deposit_idr: int,
     *     additional_charge_idr: int,
     *     shortfall_idr: int
     * }
     */
    public function execute(
        EntertainmentTableBooking $booking,
        int $actualSpendIdr,
        string $idempotencyKey
    ): array {
        return DB::transaction(function () use ($booking, $actualSpendIdr, $idempotencyKey) {
            $locked = EntertainmentTableBooking::lockForUpdate()->findOrFail($booking->id);

            if ($locked->status === TableBookingStatus::SETTLED->value) {
                return [
                    'booking' => $locked,
                    'total_bill_idr' => max((int) $locked->minimum_spend_idr, (int) $locked->actual_spend_idr),
                    'captured_deposit_idr' => (int) $locked->deposit_amount_idr,
                    'additional_charge_idr' => max(0, (int) $locked->actual_spend_idr - (int) $locked->deposit_amount_idr),
                    'shortfall_idr' => (int) $locked->shortfall_idr,
                ];
            }

            $cert = MinimumSpendSettlement::calculate(
                minimumSpendIdr: (int) $locked->minimum_spend_idr,
                depositHeldIdr: (int) $locked->deposit_amount_idr,
                actualSpendIdr: $actualSpendIdr,
                isNoShow: false
            );

            $locked->actual_spend_idr = $actualSpendIdr;
            $locked->shortfall_idr = $cert->shortfallIdr;
            TableBookingStateMachine::transition($locked, TableBookingStatus::SETTLED);

            $ledger = $this->ledger ?? app(Ledger::class);

            // Jurnal 1: Pelepasan deposit escrow ke table revenue
            if ($cert->capturedDepositIdr > 0) {
                $ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_TABLE_SETTLE->value,
                    description: "Penyelesaian deposit meja VIP {$locked->booking_code}",
                    idempotencyKey: "{$idempotencyKey}:dep",
                    entries: Posting::lines()
                        ->debit('ven:vip_unearned_rev:IDR', $cert->capturedDepositIdr)
                        ->credit('ven:table_revenue:IDR', $cert->capturedDepositIdr)
                        ->toEntries('IDR'),
                    referenceType: 'VENUE_TABLE_BOOKING',
                    referenceId: (string) $locked->id
                ));
            }

            // Jurnal 2: Tambahan tagihan konsumsi ke table revenue
            if ($cert->additionalChargeIdr > 0) {
                $ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_TABLE_SETTLE->value,
                    description: "Penagihan kelebihan belanja meja VIP {$locked->booking_code}",
                    idempotencyKey: "{$idempotencyKey}:add",
                    entries: Posting::lines()
                        ->debit('ven:ticket_clearing:IDR', $cert->additionalChargeIdr)
                        ->credit('ven:table_revenue:IDR', $cert->additionalChargeIdr)
                        ->toEntries('IDR'),
                    referenceType: 'VENUE_TABLE_BOOKING',
                    referenceId: (string) $locked->id
                ));
            }

            return [
                'booking' => $locked,
                'total_bill_idr' => $cert->totalBillIdr,
                'captured_deposit_idr' => $cert->capturedDepositIdr,
                'additional_charge_idr' => $cert->additionalChargeIdr,
                'shortfall_idr' => $cert->shortfallIdr,
            ];
        });
    }
}

