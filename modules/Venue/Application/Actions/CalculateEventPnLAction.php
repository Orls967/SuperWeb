<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Banking\Contracts\LedgerEntry;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;
use Modules\Venue\Domain\Models\EntertainmentTicket;

class CalculateEventPnLAction extends BaseAction
{
    /**
     * Menghitung Laba/Rugi (P&L) komprehensif acara tempat hiburan dari subledger dan ledger.
     *
     * @return array{
     *     event_code: string,
     *     ticket_revenue_idr: int,
     *     table_deposit_revenue_idr: int,
     *     bar_fnb_revenue_idr: int,
     *     total_gross_revenue_idr: int,
     *     entertainment_tax_idr: int,
     *     net_revenue_idr: int,
     *     insurance_expense_idr: int,
     *     operating_margin_idr: int
     * }
     */
    public function execute(EntertainmentEvent $event): array
    {
        $ticketRevenue = (int) EntertainmentTicket::where('event_id', $event->id)
            ->where('status', '!=', 'refunded')
            ->sum('price_paid_idr');

        $tableRevenue = (int) EntertainmentTableBooking::where('event_id', $event->id)
            ->whereIn('status', ['settled', 'no_show_forfeited'])
            ->sum('deposit_amount_idr');

        $barRevenue = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ven:bar_pos_clearing:IDR'))
            ->where('reference_id', (string) $event->id)
            ->sum('amount');

        $totalGross = $ticketRevenue + $tableRevenue + max(0, $barRevenue);
        $tax = (int) round($totalGross * 0.10); // 10% PBJT
        $netRev = $totalGross - $tax;

        $insuranceExpense = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'ven:event_insurance_expense:IDR'))
            ->where('reference_id', (string) $event->id)
            ->sum('amount');

        $operatingMargin = $netRev - max(0, $insuranceExpense);

        return [
            'event_code' => $event->event_code,
            'ticket_revenue_idr' => $ticketRevenue,
            'table_deposit_revenue_idr' => $tableRevenue,
            'bar_fnb_revenue_idr' => max(0, $barRevenue),
            'total_gross_revenue_idr' => $totalGross,
            'entertainment_tax_idr' => $tax,
            'net_revenue_idr' => $netRev,
            'insurance_expense_idr' => max(0, $insuranceExpense),
            'operating_margin_idr' => $operatingMargin,
        ];
    }
}
