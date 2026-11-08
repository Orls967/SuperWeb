<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\TicketStatus;
use Modules\Aviation\Domain\Exceptions\IdempotencyConflictException;
use Modules\Aviation\Domain\Models\AviationRefund;
use Modules\Aviation\Domain\Models\AviationSeatInventory;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Ledger\AviationLedgerPostingService;
use Modules\Shared\Domain\Algorithms\Aviation\FareRefundCalculator;

class ProcessFareRefundAction
{
    public function __construct(
        private readonly FareRefundCalculator $calculator,
        private readonly AviationLedgerPostingService $ledgerService
    ) {
    }

    public function execute(
        int $ticketId,
        string $reason,
        string $idempotencyKey,
        ?int $hoursBeforeFlight = null,
        bool $isCancelledByAirline = false
    ): AviationRefund {
        return DB::transaction(function () use ($ticketId, $reason, $idempotencyKey, $hoursBeforeFlight, $isCancelledByAirline) {
            $existing = AviationRefund::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->ticket_id !== $ticketId) {
                    throw IdempotencyConflictException::forAction('REFUND', $idempotencyKey);
                }
                return $existing;
            }

            $ticket = AviationTicket::with('flight')->findOrFail($ticketId);

            $hours = $hoursBeforeFlight;
            if ($hours === null && $ticket->flight) {
                $now = CarbonImmutable::now();
                $depTime = CarbonImmutable::parse($ticket->flight->departure_time);
                $hours = $now->diffInHours($depTime, false);
                if ($hours < 0) {
                    $hours = null; // No-show
                }
            }

            $calcResult = $this->calculator->calculate(
                fareClass: $ticket->fare_basis,
                baseFareMinor: (int) $ticket->base_fare_minor,
                taxMinor: (int) $ticket->tax_minor,
                hoursBeforeFlight: $hours,
                isCancelledByAirline: $isCancelledByAirline
            );

            // Transisi tiket ke status refunded
            $ticket->transitionTo(TicketStatus::REFUNDED, 'REFUND_PROCESSED', null, $reason);

            // Kembalikan kuota booked_seats pada inventori
            $inventory = AviationSeatInventory::where('flight_id', $ticket->flight_id)
                ->where('cabin', $ticket->cabin)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                $inventory->booked_seats = max(0, $inventory->booked_seats - 1);
                $inventory->save();
            }

            $refund = AviationRefund::create([
                'ticket_id' => $ticket->id,
                'original_fare_minor' => $ticket->total_fare_minor,
                'penalty_minor' => (int) ($ticket->base_fare_minor - $calcResult->baseRefundMinor - $calcResult->adminFeeMinor),
                'admin_fee_minor' => $calcResult->adminFeeMinor,
                'tax_refund_minor' => $calcResult->taxRefundMinor,
                'net_refund_minor' => $calcResult->totalRefundMinor,
                'currency' => $ticket->currency,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'processed_by' => null,
            ]);

            // Jurnal pengembalian dana ke buku besar
            $ledgerKey = "avi:refund:post:{$refund->id}:{$idempotencyKey}";
            $this->ledgerService->postTicketRefund($refund, $ledgerKey);

            return $refund;
        });
    }
}

