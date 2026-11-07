<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Ledger\VenueTransactionTypes;

class VenueRevenueRecognitionService
{
    public function __construct(
        private readonly ?Ledger $ledger = null
    ) {}

    /**
     * Memverifikasi keseimbangan akun akrual tiket belum diakui (unearned) terhadap subledger tiket.
     *
     * @return array{
     *     unearned_ledger_balance_idr: int,
     *     subledger_issued_unclosed_tickets_idr: int,
     *     is_balanced: bool,
     *     discrepancy_idr: int
     * }
     */
    public function verifyUnearnedAccrualEquilibrium(): array
    {
        $ledgerUnearned = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ven:ticket_unearned:IDR'))
            ->sum('amount');

        $openEventIds = EntertainmentEvent::where('status', '!=', 'closed')
            ->where('status', '!=', 'settled')
            ->pluck('id');

        $subledgerTickets = (int) EntertainmentTicket::whereIn('event_id', $openEventIds)
            ->where('status', '!=', 'refunded')
            ->sum('price_paid_idr');

        $diff = abs($ledgerUnearned - $subledgerTickets);

        return [
            'unearned_ledger_balance_idr' => $ledgerUnearned,
            'subledger_issued_unclosed_tickets_idr' => $subledgerTickets,
            'is_balanced' => ($diff === 0),
            'discrepancy_idr' => $diff,
        ];
    }

    /**
     * Mengakui sisa unearned revenue dari event tertutup ke pendapatan final.
     */
    public function recognizeEventTicketRevenue(EntertainmentEvent $event): int
    {
        return DB::transaction(function () use ($event) {
            $totalSales = (int) EntertainmentTicket::where('event_id', $event->id)
                ->where('status', '!=', 'refunded')
                ->sum('price_paid_idr');

            if ($totalSales > 0 && $this->ledger !== null) {
                $idempotencyKey = "ven:rev_rec:evt_{$event->id}";

                $this->ledger->post(new PostingDTO(
                    type: VenueTransactionTypes::VENUE_EVENT_CLOSING->value,
                    description: "Revenue recognition for event {$event->event_code} - {$event->title}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ven:ticket_unearned:IDR', $totalSales)
                        ->credit('ven:ticket_revenue:IDR', $totalSales)
                        ->toEntries('IDR'),
                    referenceType: 'VENUE_EVENT',
                    referenceId: (string) $event->id
                ));
            }

            return $totalSales;
        });
    }
}
