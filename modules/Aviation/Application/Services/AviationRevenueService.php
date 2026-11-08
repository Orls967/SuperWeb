<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\TicketStatus;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Ledger\AviationAccounts;
use Modules\Aviation\Ledger\AviationLedgerPostingService;
use Modules\Aviation\Ledger\AviationTransactionTypes;

class AviationRevenueService
{
    public function __construct(
        private readonly AviationLedgerPostingService $ledgerPostingService
    ) {}

    /**
     * Pengakuan pendapatan earned saat penerbangan telah departed / flown.
     * Mengubah unearned passenger revenue menjadi earned ticket revenue.
     *
     * @param int $flightId
     * @return array{recognized_count: int, total_amount_minor: int, journal_reference: string}
     */
    public function recognizeFlownRevenue(int $flightId): array
    {
        $flight = AviationFlight::findOrFail($flightId);

        $eligibleTickets = AviationTicket::where('flight_id', $flight->id)
            ->whereIn('status', [TicketStatus::ISSUED->value, TicketStatus::CHECKED_IN->value, TicketStatus::BOARDED->value])
            ->get();

        if ($eligibleTickets->isEmpty()) {
            return [
                'recognized_count' => 0,
                'total_amount_minor' => 0,
                'journal_reference' => '',
            ];
        }

        $totalEarnedMinor = (int) $eligibleTickets->sum('base_fare_minor');
        $journalRef = 'REV-FLOWN-'.$flight->flight_number.'-'.$flight->id;

        DB::transaction(function () use ($eligibleTickets, $flight, $totalEarnedMinor, $journalRef) {
            foreach ($eligibleTickets as $t) {
                $t->transitionTo(TicketStatus::FLOWN, 'FLIGHT_DEPARTED');
            }

            // Pindah unearned -> earned
            $this->ledgerPostingService->post(
                journalReference: $journalRef,
                transactionType: AviationTransactionTypes::FLIGHT_DEPARTURE_REVENUE_RECOGNIZED->value,
                amountMinor: $totalEarnedMinor,
                debitAccount: AviationAccounts::PASSENGER_UNEARNED,
                creditAccount: AviationAccounts::TICKET_REVENUE,
                description: "Pengakuan pendapatan penerbangan terlaksana {$flight->flight_number}"
            );
        });

        return [
            'recognized_count' => $eligibleTickets->count(),
            'total_amount_minor' => $totalEarnedMinor,
            'journal_reference' => $journalRef,
        ];
    }
}

