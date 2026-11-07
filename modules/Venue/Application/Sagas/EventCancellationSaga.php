<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Sagas;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Insurance\Contracts\EventCoverBinder;
use Modules\Venue\Application\Actions\RefundVenueTicketAction;
use Modules\Venue\Domain\Enums\EventStatus;
use Modules\Venue\Domain\Enums\TableBookingStatus;
use Modules\Venue\Domain\Enums\TicketStatus;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\VenueSaga;
use Modules\Venue\Domain\StateMachines\EventStateMachine;
use Modules\Venue\Domain\StateMachines\TableBookingStateMachine;
use Modules\Venue\Ledger\VenueTransactionTypes;

final class EventCancellationSaga
{
    public function __construct(
        private readonly RefundVenueTicketAction $refundAction,
        private readonly ?EventCoverBinder $insuranceBinder = null,
        private readonly ?Ledger $ledger = null,
    ) {}

    /**
     * @return array{saga_id: int, status: string, refunded_tickets: int, refunded_tables: int, claim_id: ?int}
     */
    public function execute(
        EntertainmentEvent $event,
        string $reason,
        string $idempotencyKey,
        ?int $insurancePolicyId = null
    ): array {
        return DB::transaction(function () use ($event, $reason, $idempotencyKey, $insurancePolicyId) {
            $saga = VenueSaga::firstOrCreate(
                ['saga_reference' => $idempotencyKey],
                [
                    'saga_type' => 'EVENT_CANCELLATION_SAGA',
                    'current_step' => 'STARTED',
                    'status' => 'STARTED',
                    'payload_json' => [
                        'event_id' => $event->id,
                        'reason' => $reason,
                    ],
                ]
            );

            // Step 1: Batalkan Event
            $saga->update(['current_step' => 'CANCEL_EVENT']);
            EventStateMachine::transition($event, EventStatus::CANCELLED, $reason);

            // Step 2: Refund Seluruh Tiket Terbit
            $saga->update(['current_step' => 'REFUND_TICKETS']);
            $tickets = EntertainmentTicket::where('event_id', $event->id)
                ->whereNotIn('status', [TicketStatus::REFUNDED->value, TicketStatus::VOID->value])
                ->get();

            $refundedTicketCount = 0;
            $totalTicketLoss = 0;
            foreach ($tickets as $t) {
                $this->refundAction->execute(
                    ticket: $t,
                    reason: "Pembatalan acara: {$reason}",
                    idempotencyKey: "ven:refund:evt_{$event->id}:tck_{$t->id}"
                );
                $totalTicketLoss += (int) $t->price_paid_idr;
                $refundedTicketCount++;
            }

            // Step 3: Refund Deposit Meja VIP
            $saga->update(['current_step' => 'REFUND_TABLES']);
            $tables = EntertainmentTableBooking::where('event_id', $event->id)
                ->whereNotIn('status', [TableBookingStatus::SETTLED->value, TableBookingStatus::NO_SHOW_FORFEITED->value, TableBookingStatus::CANCELLED_REFUNDED->value])
                ->get();

            $refundedTableCount = 0;
            $ledger = $this->ledger ?? app(Ledger::class);
            foreach ($tables as $tbl) {
                $dep = (int) $tbl->deposit_amount_idr;
                if ($dep > 0) {
                    $ledger->post(new PostingDTO(
                        type: VenueTransactionTypes::VENUE_TABLE_NO_SHOW_FEE->value,
                        description: "Pengembalian deposit meja {$tbl->booking_code} akibat pembatalan acara",
                        idempotencyKey: "ven:table_refund:evt_{$event->id}:tbl_{$tbl->id}",
                        entries: Posting::lines()
                            ->debit('ven:vip_unearned_rev:IDR', $dep)
                            ->credit('ven:vip_escrow:IDR', $dep)
                            ->toEntries('IDR'),
                        referenceType: 'VENUE_TABLE_BOOKING',
                        referenceId: (string) $tbl->id
                    ));
                }
                TableBookingStateMachine::transition($tbl, TableBookingStatus::CANCELLED_REFUNDED, $reason);
                $refundedTableCount++;
            }

            // Step 4: Klaim Asuransi Pembatalan G7-K15
            $claimId = null;
            if ($insurancePolicyId !== null && $this->insuranceBinder !== null) {
                $saga->update(['current_step' => 'CLAIM_INSURANCE']);
                $claimId = $this->insuranceBinder->claimCancellation(
                    policyId: $insurancePolicyId,
                    reasonCode: 'FORCE_MAJEURE_WEATHER',
                    lossMinor: max(1_000_000, $totalTicketLoss),
                    idempotencyKey: "ven:ins_claim:evt_{$event->id}"
                );
            }

            // Selesai
            $saga->update([
                'current_step' => 'COMPLETED',
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ]);

            return [
                'saga_id' => $saga->id,
                'status' => 'COMPLETED',
                'refunded_tickets' => $refundedTicketCount,
                'refunded_tables' => $refundedTableCount,
                'claim_id' => $claimId,
            ];
        });
    }
}
