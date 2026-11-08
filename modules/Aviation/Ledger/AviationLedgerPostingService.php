<?php

declare(strict_types=1);

namespace Modules\Aviation\Ledger;

use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationMaintenanceVisit;
use Modules\Aviation\Domain\Models\AviationRefund;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;

class AviationLedgerPostingService
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {
    }

    /**
     * Jurnal Penjualan Tiket Dibayar:
     * Debit: clearing:external:IDR
     * Kredit: avi:passenger_unearned:IDR
     */
    public function postTicketPayment(AviationTicket $ticket, string $idempotencyKey): void
    {
        $amount = (int) $ticket->total_fare_minor;
        if ($amount <= 0) {
            return;
        }

        $entries = Posting::lines()
            ->debit('clearing:external:IDR', $amount)
            ->credit(AviationAccounts::PASSENGER_UNEARNED, $amount)
            ->toEntries('IDR');

        $dto = new PostingDTO(
            type: AviationTransactionTypes::AVI_TICKET_SALE->value,
            description: "Pembayaran tiket pesawat {$ticket->ticket_number}",
            idempotencyKey: $idempotencyKey,
            entries: $entries,
            referenceType: 'AVI_TICKET',
            referenceId: (string) $ticket->id
        );

        $this->ledgerService->post($dto);
    }

    /**
     * Jurnal Pengakuan Pendapatan Penerbangan Terbang (Flown):
     * Debit: avi:passenger_unearned:IDR
     * Kredit: avi:ticket_revenue:IDR (base fare)
     * Kredit: avi:tax_payable:IDR (tax)
     */
    public function postFlightRevenue(AviationFlight $flight, int $baseFareMinor, int $taxMinor, string $idempotencyKey): void
    {
        $total = $baseFareMinor + $taxMinor;
        if ($total <= 0) {
            return;
        }

        $posting = Posting::lines()->debit(AviationAccounts::PASSENGER_UNEARNED, $total);
        if ($baseFareMinor > 0) {
            $posting = $posting->credit(AviationAccounts::TICKET_REVENUE, $baseFareMinor);
        }
        if ($taxMinor > 0) {
            $posting = $posting->credit(AviationAccounts::TAX_PAYABLE, $taxMinor);
        }

        $dto = new PostingDTO(
            type: AviationTransactionTypes::AVI_FLIGHT_REVENUE->value,
            description: "Pengakuan pendapatan penerbangan {$flight->flight_number}",
            idempotencyKey: $idempotencyKey,
            entries: $posting->toEntries('IDR'),
            referenceType: 'AVI_FLIGHT',
            referenceId: (string) $flight->id
        );

        $this->ledgerService->post($dto);
    }

    /**
     * Jurnal Refund Tiket:
     * Debit: avi:passenger_unearned:IDR
     * Kredit: avi:refund_payable:IDR
     */
    public function postTicketRefund(AviationRefund $refund, string $idempotencyKey): void
    {
        $netRefund = (int) $refund->net_refund_minor;
        if ($netRefund <= 0) {
            return;
        }

        $entries = Posting::lines()
            ->debit(AviationAccounts::PASSENGER_UNEARNED, $netRefund)
            ->credit(AviationAccounts::REFUND_PAYABLE, $netRefund)
            ->toEntries('IDR');

        $dto = new PostingDTO(
            type: AviationTransactionTypes::AVI_TICKET_REFUND->value,
            description: "Refund tiket penerbangan #{$refund->ticket_id}",
            idempotencyKey: $idempotencyKey,
            entries: $entries,
            referenceType: 'AVI_REFUND',
            referenceId: (string) $refund->id
        );

        $this->ledgerService->post($dto);
    }

    /**
     * Jurnal Pendapatan Kargo Udara:
     * Debit: avi:cargo_receivable:IDR
     * Kredit: avi:cargo_revenue:IDR
     */
    public function postCargoRevenue(AviationCargoConsignment $consignment, int $revenueMinor, string $idempotencyKey): void
    {
        if ($revenueMinor <= 0) {
            return;
        }

        $entries = Posting::lines()
            ->debit(AviationAccounts::CARGO_RECEIVABLE, $revenueMinor)
            ->credit(AviationAccounts::CARGO_REVENUE, $revenueMinor)
            ->toEntries('IDR');

        $dto = new PostingDTO(
            type: AviationTransactionTypes::AVI_CARGO_REVENUE->value,
            description: "Pendapatan kargo udara AWB {$consignment->consignment_number}",
            idempotencyKey: $idempotencyKey,
            entries: $entries,
            referenceType: 'AVI_CARGO',
            referenceId: (string) $consignment->id
        );

        $this->ledgerService->post($dto);
    }

    /**
     * Jurnal Biaya Pemeliharaan Pesawat:
     * Debit: avi:maintenance_expense:IDR
     * Kredit: avi:maintenance_payable:IDR
     */
    public function postMaintenanceExpense(AviationMaintenanceVisit $visit, int $expenseMinor, string $idempotencyKey): void
    {
        if ($expenseMinor <= 0) {
            return;
        }

        $entries = Posting::lines()
            ->debit(AviationAccounts::MAINTENANCE_EXPENSE, $expenseMinor)
            ->credit(AviationAccounts::MAINTENANCE_PAYABLE, $expenseMinor)
            ->toEntries('IDR');

        $dto = new PostingDTO(
            type: AviationTransactionTypes::AVI_MAINT_EXPENSE->value,
            description: "Biaya perawatan visit #{$visit->id} pesawat #{$visit->aircraft_id}",
            idempotencyKey: $idempotencyKey,
            entries: $entries,
            referenceType: 'AVI_MAINTENANCE',
            referenceId: (string) $visit->id
        );

        $this->ledgerService->post($dto);
    }
}

